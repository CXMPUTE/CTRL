<?php

namespace Pterodactyl\Console\Commands\Server;

use Exception;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Illuminate\Console\Command;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Services\Servers\ServerCreationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Pterodactyl\Exceptions\Service\Deployment\NoViableAllocationException;

class AutoDeployRetryCommand extends Command
{
    protected $signature = 'p:server:auto-deploy';

    protected $description = 'Attempts to automatically deploy servers if they are missing from a users\' account.';

    /**
     * AutoDeployRetryCommand constructor.
     */
    public function __construct()
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
        foreach (User::all() as $user) {
            if ($user->servers->count() <= 0) {
                try {
                    $this->deploy($user);
                } catch (Exception $ex) {
                    throw new Exception('Unable to deploy server: ' . $ex->getMessage());
                }
            }
        }
    }

    /**
     * Deploys an instance for a user if no server exists for them.
     */
    public function deploy(User $user): void
    {
        
        $node = null;
        $egg = Egg::findOrFail(3);

        foreach (Node::inRandomOrder()->get() as $n) {
            if ($n->isViable(3072, 20480) && $n->public) {
                $node = $n->id;
                break;
            }
        }

        if (!$node) {
            throw new DisplayException('Unable to find a viable node.');
        }

        $data = [
            'name' => $user->username . '\'s Server',
            'description' => 'Automatically deployed via CXMPUTE.com',
            'owner_id' => $user->id,
            'egg_id' => $egg->id,
            'nest_id' => 1,
            'node_id' => $node,
            'allocation_id' => $this->getAllocation($node),
            'allocation_limit' => 3,
            'backup_limit' => 3,
            'database_limit' => 3,
            'environment' => [],
            'memory' => 3072,
            'disk' => 20480,
            'cpu' => 200,
            'swap' => 0,
            'io' => 500,
            'image' => array_values($egg->docker_images)[0],
            'startup' => $egg->startup,
            'start_on_completion' => false,
        ];

        foreach (EggVariable::where('egg_id', $egg->id)->get() as $var) {
            $key = "v1-{$egg->id}-{$var->env_variable}";
            $data['environment'][$var->env_variable] = $var->default_value;
        }

        $this->creation->handle($data);
    }
    


    /**
     * Gets an allocation for server deployment.
     *
     * @throws NoViableAllocationException
     */
    protected function getAllocation(int $node): int
    {
        $allocation = Allocation::where('node_id', $node)->where('server_id', null)->first();

        if (!$allocation) {
            throw new NoViableAllocationException('No allocations are available for deployment.');
        }

        return $allocation->id;
    }
}
