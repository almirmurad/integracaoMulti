<?php
namespace src\middlewares;


use src\handlers\LoginHandler;

class AuthenticateMiddleware extends Middleware {

    public function handle($args, $next) {
        // Verifica se o usuário está autenticado (exemplo usando uma variável de sessão)
        $loggedUser = LoginHandler::checkLogin();
        
        if(!$loggedUser){
            http_response_code(401);
            echo json_encode(['error' => 'Usuário não autenticado']);
            exit;
        }
        
        $args['loggedUser'] = $loggedUser;

        // Se o usuário estiver autenticado, chama o próximo middleware ou controlador
        return $next($args);
    }
}
