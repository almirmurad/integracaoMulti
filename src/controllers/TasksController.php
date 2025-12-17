<?php

namespace src\controllers;

use core\Controller;
use GuzzleHttp\Psr7\Response;
use src\exceptions\WebhookReadErrorException;
use src\factories\ErpFormatterFactory;
use src\functions\DiverseFunctions;
use src\handlers\ClientHandler;
use src\handlers\LoginHandler;
use src\handlers\TasksHandler;
use src\services\DatabaseServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;
use src\services\RDStationServices;

class TasksController extends Controller
{
    private $ploomesServices;
    private $databaseServices;
    private $rdstationServices;
    private $rabbitMQServices;


    public function __construct($args)
    {
        $ploomesBase = $args['Tenancy']['ploomes_bases'][0];
        $args['Tenancy']['vhost'][0]['key'] = $args['Tenancy']['tenancies']['cpf_cnpj'];
        $vhost = $args['Tenancy']['vhost'][0];
        $this->ploomesServices = new PloomesServices($ploomesBase);
        $this->databaseServices = new DatabaseServices();
        $this->rdstationServices = new RDStationServices();
        //$this->rabbitMQServices = new RabbitMQServices($vhost);
    }

    private function getTasksHandler(): TasksHandler
    {   

        $tasksHandler = new TasksHandler($this->ploomesServices, $this->databaseServices);

        return $tasksHandler;
    }

    // public function loginRdStation($args){

    //     $response = [];       
    //     header('Content-Type: application/json');
    //     try{ 
    //         $token = $this->rdstationServices->authenticate($args);
    //         // $app = $this->rdstationServices->getRDStationCredentials($args);
  
    //     }catch(WebhookReadErrorException $e){
    //     }finally{
    //         if(isset($e)){

    //             $response['status'] = 500;
    //             $response['is_logged'] = false; 
    //             $response['message'] = $e->getMessage();

    //             print json_encode($response);
    //             exit;
    //         }else{
    //             if($token != null){

    //                 $response['status'] = 200;
    //                 $response['is_logged'] = true; 
    //                 $response['message'] = 'Usuário autenticado no RDStation com sucesso!';

    //                 print json_encode($response);
    //                 exit;
    //             }
    //         }
    //     }


        
    // }

    //Ploomes
    //recebe webhook de cliente criado, alterado e excluído do PLOOMES CRM
    public function ploomesTasks($args)
    {
        $message = [];
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $json = json_encode($args['body']);
        
        try {

            // $action = DiverseFunctions::findAction($args);
       
            // if($action['type'] === 'pessoa' && $args['body']['New']['CompanyId'] === null){
            //     throw new WebhookReadErrorException('Cadastro de pessoa sem empresa referenciada', 500);
            // }

            // if($action['action'] === "update"){

            //     $ignorar = ['LastUpdateDate', 'UpdaterId'];
            //     $diferencas = DiverseFunctions::compareArrays($args['body']['Old'], $args['body']['New'], $ignorar);
    
            //     if(empty($diferencas)){
            //         throw new WebhookReadErrorException('Não houve alteração no array', 500);
            //     }
            // }
            
            $tasksHandler = $this->getTasksHandler($args);
            $response = $tasksHandler->saveWebhook($json, $idUser);

            // $rk = origem.entidade.ação
            $rk = array('Ploomes', 'Contacts');
            //$this->rabbitMQServices->publicarMensagem('contacts_exc', $rk, 'ploomes_contacts',  $json);

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
                file_put_contents('./assets/logClient', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                return print 'ERROR:' . $message['status_code'] . '. MESSAGE: ' . $message['status_message'];
            }

            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logClient', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
            return print $message['status_message'];
        }
    }

    //processa contatos e clientes do ploomes ou do Erp
    public function processNewOpportunity($args)
    {
        
        header('Content-Type: application/json');
        $message = [];
        // processa o webhook 
        try {

            $token = $this->rdstationServices->authenticate($args);
            // $token ='abc123';

            if($token){

                $tasksHandler = $this->getTasksHandler($args);
                $response = $tasksHandler->startProcess($args);

                
                $message = [
                    'status_code' => 200,
                    'status_message' => $response['success'],
                ];
            }

        } catch (WebhookReadErrorException $e) {
        } finally {
            http_response_code(200);
            ob_start();
            if (isset($e)) {
                http_response_code(500);

                $message = [
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];

                var_dump($message);
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/logOpportunity.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);

                print json_encode($message);
                exit;
            }

            var_dump($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logOpportunity.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
            
            print json_encode($message);
            exit;
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

            $tasksHandler = $this->getClientHandler($args);
            $tasksHandler->detectLoop($args);
            $response = $tasksHandler->saveClientHook($json, $idUser);

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
                file_put_contents('./assets/logClient', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                $m = json_encode($message);
                return print_r($m);
            }
            var_dump($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logClient', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
            $m = json_encode($message);
            return print_r($m);
        }
    }

    public function rdNewOpportunity($args)
    {
        header('Content-Type: application/json');

        $message = [];
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $json = json_encode($args['body']);

        try{
            //autentica o rdstation
            // $token = $this->rdstationServices->authenticate($args);
            // if($token){
                $tasksHandler = $this->getTasksHandler($args);
                $response = $tasksHandler->saveWebhook($json, $idUser);

                //$rk = origem.entidade.ação
                //$rk = array('RDStation', 'Opportunity');
                //$this->rabbitMQServices->publicarMensagem('opportunity_exc', $rk, 'rdstation_opportunity',  $json);

                if ($response > 0) {
                    $message = [
                        'status_code' => 200,
                        'status_message' => 'SUCCESS: ' . $response['msg'],
                    ];
                }
            // }
        }catch(WebhookReadErrorException $e){
        }finally{
            
            ob_start(); 
            if(isset($e)){
                // Define o código de status como 200 OK
                http_response_code(500);
                $message = [
                    'status_code' => 500,
                    'status_message' => 'error: ' . $e->getMessage(),
                ];
                print json_encode($message);
                exit; 
            }

            // Define o código de status como 200 OK
            http_response_code(200);
            var_dump($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logOpportunity.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);

            print json_encode($message);
            exit;
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
