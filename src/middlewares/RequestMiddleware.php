<?php
namespace src\middlewares;

use src\handlers\ClientHandler;
use core\Request;
use Exception;
use src\handlers\TenancyHandler;

class RequestMiddleware extends Middleware {

    public function handle($args, $next) {
        // Captura os dados da requisição
        $request = new Request();
        $headers = $request->getJsonHeaders();
        $idUser = $headers['Id-User'] ?? null;

        // Obtém o IP do servidor que enviou a requisição
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
        try{
            if($idUser){

                // Busca as credenciais do usuário no banco atrvés do Id que veio no header (Quando vem do rabbitMq ao process)
                $user = ClientHandler::getClientById($idUser);
                
            }else{
                // Obtém o subdomínio da requisição
                $host = $_SERVER['HTTP_HOST'] ?? '';
                $subdomain = explode('.', $host)[0]; // Supondo que seja algo como "cliente.dominio.com"
                // Busca o cliente no banco de dados pelo subdominio, quando vem dos erps/crms
                $subdomain = strtolower('energy');
                $user = ClientHandler::getClientBySubdomain($subdomain);    
            }
            // Busca as credenciais do usuário no banco
            $tenancy = TenancyHandler::getAllInfoUserAPi($user['id'], $user['erp_name']);
            $tenancy['tenancies']['erp_name'] = $user['erp_name'];
            
            if (!$tenancy) {
                http_response_code(404);
                echo json_encode(['error' => 'Tenancy não encontrado para o usuário de id: '. $user['id']]);
                exit;
            }
        }catch(Exception $e){
            http_response_code(401);
            echo json_encode(['error' => 'Cliente não encontrado - '.$e->getMessage()]);
            exit;
        }

        // Adiciona os dados ao array de argumentos para o próximo middleware/controlador
        $args['body'] = $request->getJsonBody();
        $args['user'] = $user;
        $args['Tenancy'] = $tenancy;
        $args['clientIp'] = $clientIp; // Adiciona o IP à requisição
        
        return $next($args);
    }
}
