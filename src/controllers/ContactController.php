<?php

namespace src\controllers;

use core\Controller;
use src\exceptions\WebhookReadErrorException;
use src\factories\ErpFormatterFactory;
use src\functions\DiverseFunctions;
use src\handlers\ClientHandler;
use src\handlers\LoginHandler;
use src\services\DatabaseServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class ContactController extends Controller
{

    private $loggedUser;
    private $ploomesServices;
    private $databaseServices;
    // private $rabbitMQServices;


    public function __construct($args)
    {

        $ploomesBase = $args['Tenancy']['ploomes_bases'][0];

        $args['Tenancy']['vhost'][0]['key'] = $args['Tenancy']['tenancies']['cpf_cnpj'];
        $vhost = $args['Tenancy']['vhost'][0];
        $this->ploomesServices = new PloomesServices($ploomesBase);
          
        $this->databaseServices = new DatabaseServices();
        // $this->rabbitMQServices = new RabbitMQServices($vhost);
    }

    private function getClientHandler($args): ClientHandler
    {   
        $formatter = ErpFormatterFactory::create($args);
        $clienteHandler = new ClientHandler($this->ploomesServices, $this->databaseServices, $formatter);

        return $clienteHandler;
    }

    //Ploomes
    //recebe webhook de cliente criado, alterado e excluído do PLOOMES CRM
    public function ploomesContacts($args)
    {
        
        $message = [];
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $json = json_encode($args['body']);
        
        try {

            $action = DiverseFunctions::findAction($args);
           
            if($action['action'] === "update"){

                $ignorar = ['LastUpdateDate', 'UpdaterId', 'Key'];
                $diferencas = DiverseFunctions::compareArrays($args['body']['Old'], $args['body']['New'], $ignorar);
         
                if(empty($diferencas)){
                    throw new WebhookReadErrorException('Não houve alteração no array', 500);
                }
            }
            
            $clienteHandler = $this->getClientHandler($args);
            $response = $clienteHandler->saveClientHook($json, $idUser);
        
            // $rk = origem.entidade.ação
            // $rk = array('Ploomes', 'Contacts');
            // $this->rabbitMQServices->publicarMensagem('contacts_exc', $rk, 'ploomes_contacts',  $json);

            if ($response > 0) {
                $message = [
                    'status_code' => 200,
                    'status_message' => 'SUCCESS: ' . $response['msg'],
                ];
            }
        } catch (WebhookReadErrorException $e) {
        } finally {
            ob_start();
            var_dump($message);

            if (isset($e)) {
                $message = [
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];

                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/client_err.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                return print 'ERROR:' . $message['status_code'] . '. MESSAGE: ' . $message['status_message'];
            }

            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/client_log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
            return print $message['status_message'];
        }
    }

    //processa contatos e clientes do ploomes ou do Erp
    public function processNewContact($args)
    {
        // print_r($args);
        // exit;
        $message = [];
        // processa o webhook 
        try {
            // print'aqui';
            // exit;
            $clienteHandler = $this->getClientHandler($args);
            $response = $clienteHandler->startProcess($args);

            $message = [
                'status_code' => 200,
                'status_message' => $response['success'],
            ];
        } catch (WebhookReadErrorException $e) {
        } finally {
            ob_start();
            
            if (isset($e)) {

                $message = [
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
                var_dump($message);
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/client_err.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                $m = json_encode($message);
                return print_r($m);
            }
            var_dump($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/client_log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
            $m = json_encode($message);
            return print_r($m);
        }
    }

    //Erp
    //recebe webhook de cliente criado, alterado e excluído do ERP
    public function erpClients($args)
    {
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $json = json_encode($args['body']);

        $message = [];

        try {

            $clienteHandler = $this->getClientHandler($args);
            $clienteHandler->detectLoop($args);
            $response = $clienteHandler->saveClientHook($json, $idUser);

            // $rk = origem.entidade.ação
            $rk = array('Erp', 'Clientes');
            $this->rabbitMQServices->publicarMensagem('contacts_exc', $rk, 'erp_clientes',  $json);

            if ($response > 0) {

                $message = [
                    'status_code' => 200,
                    'status_message' => 'Success: ' . $response['msg'],
                ];
            }
        } catch (WebhookReadErrorException $e) {
        } finally {
            ob_start();
            
            if (isset($e)) {
                
                $message = [
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
                var_dump($message);
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/client_err.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                $m = json_encode($message);
                return print_r($m);
            }
            var_dump($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/client_log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
            $m = json_encode($message);
            return print_r($m);
        }
    }

    public function nasajonClients()
    {

        // Definir cabeçalhos CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Content-Type: application/json");

        // Se for uma requisição OPTIONS (preflight), apenas retornar status 200 e encerrar
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        // Receber JSON normalmente
        $json = file_get_contents('php://input');

        ob_start();
        print_r($json);
        $input = ob_get_contents();
        ob_end_clean();
        file_put_contents('./assets/nasajon.log', $input . PHP_EOL, FILE_APPEND);

        $r = [
            'pong' => true
        ];
        $jsonR = json_encode($r);

        return print $jsonR;
    }
}
