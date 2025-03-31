<?php
namespace src\middlewares;

abstract class Middleware {
    abstract public function handle($args, $next);
}
