<?php

namespace Pterodactyl\Console\Commands\Server;

use Exception;
use Pterodactyl\Models\Server;
use Illuminate\Console\Command;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

class SecurityCheckCommand extends Command
{
    protected $signature = 'p:server:check';

    protected $description = 'Checks for servers not running Paper and purges them from the system.';

    /**
     * SecurityCheckCommand constructor.
     */
    public function __construct(private ServerDeletionService $deletion, private DaemonServerRepository $serverRepository)
    {
        parent::__construct();
    }

    /**
     * Handle the recovery service request.
     *
     * @throws Exception
     */
    public function handle()
    {
        $servers = Server::where('pro', false)->get();

        $this->output->writeln('Total server list: ' . $servers->count());

        foreach ($servers as $server) {

            $details = $this->serverRepository->setServer($server)->getDetails();
            
            if ($details['state'] === 'starting') {
                $server->update(['checks' => $server->checks + 1]);

                $this->output->writeln($server->id . ' is \'starting\', added to list. Total checks is at ' . $server->checks);

                if ($server->checks >= 3) {
                    try {
                        $this->deletion->handle($server)->withForce(true);
                        $this->output->writeln($server->id . ' has been deleted due to abuse of service');
                    } catch (Exception $ex) {
                        throw new Exception('Unable to delete server: ' . $ex->getMessage());
                    };
                }
            } else {
                $this->output->writeln($server->id . ' has passed checks');
            }

            // Give the daemon a break :(
            sleep(0.5);
        }
    }
}
