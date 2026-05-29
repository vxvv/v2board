<?php
namespace App\Http\Routes\V1;

use Illuminate\Contracts\Routing\Registrar;

class ServerRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'server'
        ], function ($router) {
            $allowedControllers = [
                'uniProxy'       => 'UniProxy',
                'deepbwork'      => 'Deepbwork',
                'trojanTidalab'  => 'TrojanTidalab',
                'shadowsocksTidalab' => 'ShadowsocksTidalab',
            ];

            $router->any('/{class}/{action}', function ($class, $action) use ($allowedControllers) {
                if (!isset($allowedControllers[$class])) {
                    abort(404);
                }
                $controllerName = $allowedControllers[$class];

                $allowedActions = ['user', 'push', 'config', 'alive', 'alivelist', 'submit'];
                if (!in_array($action, $allowedActions, true)) {
                    abort(404);
                }

                $ctrl = \App::make("\\App\\Http\\Controllers\\V1\\Server\\{$controllerName}Controller");
                if (!method_exists($ctrl, $action)) {
                    abort(404);
                }
                return \App::call([$ctrl, $action]);
            });
        });
    }
}
