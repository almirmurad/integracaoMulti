<?php
namespace src\controllers;

use core\Controller;
use src\exceptions\WebhookReadErrorException;
use src\factories\ErpFormatterFactory;
use src\functions\CustomFieldsFunction;
use src\handlers\ClientHandler;
use src\handlers\LoginHandler;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class ContactController extends Controller {
    
    private $loggedUser;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;
    //private $rabbitMQServices;


    public function __construct($args)
    {
        $ploomesBase = $args['Tenancy']['ploomes_bases'][0];
        
        $args['Tenancy']['vhost'][0]['key'] = $args['Tenancy']['tenancies']['cpf_cnpj'];
        $vhost = $args['Tenancy']['vhost'][0];        
        $this->ploomesServices = new PloomesServices($ploomesBase);
        $this->databaseServices = new DatabaseServices();
        //$this->rabbitMQServices = new RabbitMQServices($vhost);
 
    }

    private function getContactHandler($args): ClientHandler
    {
        $formatter = ErpFormatterFactory::create($args);
        $clienteHandler = new ClientHandler($this->ploomesServices, $this->databaseServices, $formatter);

        return $clienteHandler;
    }


    //Ploomes
    //recebe webhook de cliente criado, alterado e excluído do PLOOMES CRM
    public function ploomesContacts($args)
    {
        $idUser = $args['tenancy']['tenancies']['user_id'];
        $body = $args['body'];
        $json = json_encode($body);

        try{
            
            $erp = $args['user']['erp_name'];
            $clienteHandler = $this->getContactHandler($erp);

            $response = $clienteHandler->saveClientHook($json, $idUser);

            // $rk = origem.entidade.ação
            $rk = array('Ploomes','Contacts');
            $this->rabbitMQServices->publicarMensagem('contacts_exc', $rk, 'ploomes_contacts',  $json);

            if ($response > 0) {
                
                $message =[
                    'status_code' => 200,
                    'status_message' => 'SUCCESS: '. $response['msg'],
                ];
                
            }

        }catch(WebhookReadErrorException $e){        
        }
        finally
        {
            if(isset($e)){
               
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];

                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);

                return print 'ERROR:'. $message['status_code'].'. MESSAGE: ' .$message['status_message'];
            }           
             return print $message['status_message'];         
        }        
    }

    //processa contatos e clientes do ploomes ou do Omie
    public function processNewContact($args)
    {
        
        $message = [];
        // processa o webhook 
        try{
            
            $clienteHandler = $this->getContactHandler($args);
            
            $response = $clienteHandler->startProcess($args);


            $message =[
                'status_code' => 200,
                'status_message' => $response['success'],
            ];
        
            //grava log
            ob_start();
            print_r($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logClient.log', $input . PHP_EOL, FILE_APPEND);
        
        }catch(WebhookReadErrorException $e){
                
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/logClient.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                //print $e->getMessage();
                
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
               
                // return print 'ERROR: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
                 $m = json_encode($message);
                 return print_r($m);
            }
            
            //return print 'SUCCESS: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
            $m = json_encode($message);
            return print_r($m);
               
        }

    } 

    //Omie
    //recebe webhook de cliente criado, alterado e excluído do OMIE ERP
    public function omieClients($args){
       
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $erp = $args['user']['erp_name'];
        $body = $args['body'];
        $json = json_encode($body);
        
        $message = [];
       
        try{

            $clienteHandler = $this->getContactHandler($erp);
            
            $response = $clienteHandler->saveClientHook($json, $idUser);
          
            // $rk = origem.entidade.ação
            $rk = array('Omie','Clientes');
            $this->rabbitMQServices->publicarMensagem('contacts_exc', $rk, 'omie_clientes',  $json);
            
            if ($response > 0) {
                
                $message =[
                    'status_code' => 200,
                    'status_message' => 'Success: '. $response['msg'],
                ];
                
            }

        }catch(WebhookReadErrorException $e){        
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
               
                // return print 'ERROR: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
                 $m = json_encode($message);
                 return print_r($m);
            }
            
            //return print 'SUCCESS: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
            $m = json_encode($message);
            return print_r($m);
           
        }

    }

    public function nasajonClients(){

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
                'pong'=>true
            ];
            $jsonR = json_encode($r);

        return print $jsonR;
    }


}