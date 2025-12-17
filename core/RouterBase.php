<?php
namespace core;

use Exception;
use src\Config;
use src\middlewares\AuthenticateMiddleware;


class RouterBase {

    public function run($routes) {
        $method = Request::getMethod();
        $url = Request::getUrl();

        $controller = $_ENV['ERROR_CONTROLLER'];
        $action = $_ENV['DEFAULT_ACTION'];
        $args = [];
    
        if (isset($routes[$method])) {
            foreach ($routes[$method] as $route => $callback) {
                $pattern = preg_replace('(\{[a-z0-9]{1,}\})', '([a-z0-9-]{1,})', $route);
    
                if (preg_match('#^(' . $pattern . ')*$#i', $url, $matches) === 1) {
                    array_shift($matches);
                    array_shift($matches);
    
                    $itens = [];
                    if (preg_match_all('(\{[a-z0-9]{1,}\})', $route, $m)) {
                        $itens = preg_replace('(\{|\})', '', $m[0]);
                    }
    
                    $args = [];
                    foreach ($matches as $key => $match) {
                        $args[$itens[$key]] = $match;
                    }
    
                    if (is_array($callback)) {
                        $middlewares = $callback['middlewares'] ?? [];
                        $controllerAction = $callback['callback'];
                    } else {
                        $middlewares = []; // Nenhum middleware para esta rota
                        $controllerAction = $callback;
                    }
    
                    $callbackSplit = explode('@', $controllerAction);
                    $controller = $callbackSplit[0];
                    $action = $callbackSplit[1] ?? $_ENV['DEFAULT_ACTION'];
    
                    // Executar middlewares, se existirem
                    if (!empty($middlewares)) {
                        $this->executeMiddlewares($middlewares, $args, function ($args) use ($controller, $action) {
                            $this->callController($controller, $action, $args);
                        });
                    } else {
                        // Se não houver middlewares, chamar diretamente o controller
                        $this->callController($controller, $action, $args);
                    }
    
                    return;
                }
            }
        }
    
        // Se a rota não for encontrada
        http_response_code(404);
        echo "404 - Not Found";
    }
    
    private function executeMiddlewares($middlewares, $args, $next) {
        $middlewareQueue = array_reverse($middlewares);
    
        $nextMiddleware = function ($args) use (&$middlewareQueue, $next) {
            if (empty($middlewareQueue)) {
                return $next($args);
            }
    
            $middlewareClass = array_pop($middlewareQueue);
            
            if (!class_exists($middlewareClass)) {
                throw new Exception("Middleware '$middlewareClass' não encontrado.");
            }
    
            $middlewareInstance = new $middlewareClass();
    
            if (!method_exists($middlewareInstance, 'handle')) {
                throw new Exception("O middleware '$middlewareClass' não possui um método 'handle'.");
            }
    
            return $middlewareInstance->handle($args, function ($args) use (&$middlewareQueue, $next) {
                if (empty($middlewareQueue)) {
                    return $next($args);
                }
    
                $nextMiddleware = array_pop($middlewareQueue);
                $middlewareInstance = new $nextMiddleware();
                return $middlewareInstance->handle($args, function ($args) use (&$middlewareQueue, $next) {
                    return $this->executeMiddlewares($middlewareQueue, $args, $next);
                });
            });
        };
    
        return $nextMiddleware($args);
    }
    
    
    private function callController($controller, $action, $args) {
        $controller = "\src\controllers\\$controller";
        if (!class_exists($controller)) {
            http_response_code(500);
            echo "Erro: Controller não encontrado.";
            return;
        }
    
        $definedController = new $controller($args);
        if (!method_exists($definedController, $action)) {
            http_response_code(500);
            echo "Erro: Método do controller não encontrado.";
            return;
        }
    
        $definedController->$action($args);
    }
    
}
