<?php

namespace Pterodactyl\Console\Commands\Node;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Illuminate\Console\Command;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\ServerTransfer;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Factory as ValidatorFactory;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonTransferRepository;
use Pterodactyl\Contracts\Repository\AllocationRepositoryInterface;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Exceptions\Service\Deployment\NoViableAllocationException;

class MassTransferCommand extends Command
{
    protected $signature = 'p:node:transfer
                            {--to= : The node you wish to transfer to.}
                            {--from= : The node you wish to transfer from.}';

    protected $description = 'Transfer multiple servers at once.';

    /**
     * MassTransferCommand constructor.
     */
    public function __construct(
        private DaemonTransferRepository $daemonTransferRepository,
        private NodeJWTService $nodeJWTService,
        private ConnectionInterface $connection,
        private DaemonPowerRepository $powerRepository,
        private ValidatorFactory $validator,
        private AllocationRepositoryInterface $allocationRepository
    )
    {

        parent::__construct();
    }

    /**
     * Handle the bulk power request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle()
    {
        $from = Node::findOrFail($this->argument('from'));
        $to = Node::findOrFail($this->argument('to'));

        $servers = Server::where('node_id', $from->id)->get();

        $validator = $this->validator->make([
            'from' => $from,
            'to' => $to,
        ], [
            'from' => 'integer|required',
            'to' => 'integer|required',
        ]);

        if ($validator->fails()) {
            foreach ($validator->getMessageBag()->all() as $message) {
                $this->output->error($message);
            }

            throw new ValidationException($validator);
        }

        $bar = $this->output->createProgressBar($servers->count());
        $powerRepository = $this->powerRepository;

       foreach ($servers as $server) {
            $bar->clear();

            $this->output($server->id . ' - sending kill power command');

            try {
                $powerRepository->setServer($server)->send('kill');
            } catch (DaemonConnectionException $exception) {
                $this->output->error(trans('command/messages.server.power.action_failed', [
                    'name' => $server->name,
                    'id' => $server->id,
                    'node' => $server->node->name,
                    'message' => $exception->getMessage(),
                ]));
            }

            $this->output($server->id . ' - initiating transfer');

            
            try {
                $allocation_id = $this->findViableAllocation($from);

                $server->validateTransferState();

                $this->connection->transaction(function () use ($server, $to, $allocation_id) {
                    $transfer = new ServerTransfer();

                    $transfer->new_node = $to->id;
                    $transfer->server_id = $server->id;
                    $transfer->old_node = $server->node_id;
                    $transfer->new_allocation = $allocation_id;
                    $transfer->old_allocation = $server->allocation_id;

                    $transfer->save();

                    $this->assignAllocationToServer($server, $to->id, $allocation_id);

                    // Generate a token for the destination node that the source node can use to authenticate with.
                    $token = $this->nodeJWTService
                        ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
                        ->setSubject($server->uuid)
                        ->handle($transfer->newNode, $server->uuid, 'sha256');

                    // Notify the source node of the pending outgoing transfer.
                    $this->daemonTransferRepository->setServer($server)->notify($transfer->newNode, $token);

                    return $transfer;
                });
            }

            $this->output($server->id . ' - waiting 60s for transfer to complete');

            sleep(60);

            $this->output($server->id . ' - complete');

            $bar->advance();
            $bar->display();
        };

        $this->line('');
    }

    /**
     * Gets an allocation for server deployment.
     *
     * @throws NoViableAllocationException
     */
    protected function findViableAllocation(Node $from): int
    {
        $allocation = Allocation::where('node_id', $from->id)->where('server_id', null)->first();

        if (!$allocation) {
            $this->output->error('No allocations are available for deployment.');

            throw new NoViableAllocationException('No allocations are available for deployment.');
        }

        return $allocation->id;
    }

    /**
     * Assigns the specified allocations to the specified server.
     */
    private function assignAllocationToServer(Server $server, int $node_id, int $allocation_id)
    {
        $unassigned = $this->allocationRepository->getUnassignedAllocationIds($node_id);

        if (in_array($allocation_id, $unassigned)) {
            $this->allocationRepository->updateWhereIn('id', [$allocation_id], ['server_id' => $server->id]);
        }
    }
}
