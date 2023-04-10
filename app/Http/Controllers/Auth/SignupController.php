<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Pterodactyl\Models\Egg;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\Services\Servers\ServerCreationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Jexactyl\Exceptions\Service\Deployment\NoViableAllocationException;

class SignupController extends AbstractLoginController
{
    /**
     * SignupController constructor.
     */
    public function __construct(
        private UserCreationService $userCreation,
        private ServerCreationService $serverCreation
    )
    {
        parent::__construct();
    }

    /**
     * Handle a signup request to the application.
     *
     * @throws DisplayException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function signup(Request $request): JsonResponse
    {
        try {
            $user = $this->userCreation->handle([
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'name_first' => 'CTRL',
                'name_last' => 'User',
                'password' => $request->input('password'),
            ]);
        } catch (ModelNotFoundException) {
            $this->sendFailedLoginResponse($request);
        }

        try {
            $server = $this->serverCreation->handle($this->getModel($request, $user));
        } catch (DisplayException) {
            throw new DisplayException('
                The account was created, however a server has not been deployed.
                Please inform an administrator referencing ID ' . $user->id);
        }

        $this->auth->guard()->login($user, true);

        return new JsonResponse([
            'data' => [
                'complete' => true,
                'intended' => '/server/' . $server->uuidShort,
                'user' => $user->toVueObject(),
            ],
        ]);
    }

    /**
     * Creates a server model for use with the new user.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getModel(Request $request, User $user): array
    {
        $node = null;
        $egg = Egg::findOrFail(3);

        foreach (Node::inRandomOrder()->get() as $n) {
            if ($n->isViable(3072, 20480) && $n->public) {
                $node = $n->id;
                break;
            }
        }

        if (!$node) throw new DisplayException('Unable to find a viable node.');

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
            'start_on_completion' => true,
        ];

        foreach (EggVariable::where('egg_id', $egg->id)->get() as $var) {
            $key = "v1-{$egg->id}-{$var->env_variable}";
            $data['environment'][$var->env_variable] = $request->get($key, $var->default_value);
        }

        return $data;
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
