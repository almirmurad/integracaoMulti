<?php
namespace src\middlewares;

class MiddlewareManager {
    private $middlewares = [];

    public function __construct(array $middlewares) {
        foreach ($middlewares as $middleware) {
            if (is_subclass_of($middleware, Middleware::class)) {
                $this->middlewares[] = new $middleware();
            } else {
                throw new \Exception("O middleware {$middleware} deve estender a classe Middleware.");
            }
        }
    }

    public function handle() {
        foreach ($this->middlewares as $middleware) {
            $middleware->process();
        }
    }
}
