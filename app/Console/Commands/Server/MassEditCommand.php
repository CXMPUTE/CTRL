<?php

namespace Pterodactyl\Console\Commands\Server;

use Exception;
use Pterodactyl\Models\Server;
use Illuminate\Console\Command;

class MassEditCommand extends Command
{
    protected $signature = 'p:server:mass-edit';

    protected $description = 'Attempts to automatically deploy servers if they are missing from a users\' account.';

    /**
     * MassEditCommand constructor.
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
        foreach (Server::where('pro', false)->get() as $server) {
            try {
                $server->update([
                    'cpu' => 150,
                    'memory' => 2048,
                ]);
            } catch (Exception $ex) {
                throw new Exception($server->id . ' threw an error: ' . $ex->getMessage());
            }

            // Give the daemon a moment to sync changes.
            sleep(1);
        };
    }
}
