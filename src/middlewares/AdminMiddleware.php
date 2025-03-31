<?php 
namespace middleware;

class AdminMiddleware {
    public function handle($request) {
        if ($_SESSION['user']['role'] !== 'admin') {
            echo "Acesso negado!";
            return false;
        }
        return true;
    }
}
