<?php

namespace src\controllers;

use core\Controller;
use GuzzleHttp\Psr7\Response;
use src\exceptions\WebhookReadErrorException;
use src\factories\ErpFormatterFactory;
use src\functions\DiverseFunctions;
use src\handlers\ClientHandler;
use src\handlers\LoginHandler;
use src\handlers\RDStationHandler;
use src\services\DatabaseServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;
use src\services\RDStationServices;

class RDStationController extends Controller
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
        // $this->rabbitMQServices = new RabbitMQServices($vhost);
    }

    private function getRDStationHandler(): RDStationHandler
    {   

        $RDStationHandler = new RDStationHandler($this->ploomesServices, $this->databaseServices, $this->rdstationServices);

        return $RDStationHandler;
    }

    public function loginRdStation($args){

        $response = [];       
        header('Content-Type: application/json');
        try{ 
            $token = $this->rdstationServices->authenticate($args);
            // $app = $this->rdstationServices->getRDStationCredentials($args);
        }catch(WebhookReadErrorException $e){
        }finally{
            if(isset($e)){

                $response['status'] = 500;
                $response['is_logged'] = false; 
                $response['message'] = $e->getMessage();

                print json_encode($response);
                exit;
            }else{
                if($token){

                    $response['status'] = 200;
                    $response['is_logged'] = true; 
                    $response['message'] = 'Usuário autenticado no RDStation com sucesso!';

                    print json_encode($response);
                    exit;
                }
            }
        }


        
    }


    //processa contatos e clientes do ploomes ou do Erp
    public function processNewOpportunity($args)
    {
        header('Content-Type: application/json');
        $message = [];
        // processa o webhook 
        try {         

            $RDStationHandler = $this->getRDStationHandler($args);
            $response = $RDStationHandler->startProcess($args);

            $message = [
                'status_code' => 200,
                'status_message' => $response['success'],
            ];

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
                $RDStationHandler = $this->getRDStationHandler($args);
                $response = $RDStationHandler->saveWebhook($json, $idUser);

                //$rk = origem.entidade.ação
                $rk = array('RDStation', 'Opportunity');
                // $this->rabbitMQServices->publicarMensagem('opportunity_exc', $rk, 'rdstation_opportunity',  $json);

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

    public function rdReturnData($args)
    {
        header('Content-Type: application/json');

        $message = [];
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $json = json_encode($args['body']);

        try{
            //autentica o rdstation
            // $token = $this->rdstationServices->authenticate($args);
            // if($token){
                $RDStationHandler = $this->getRDStationHandler($args);
                $response = $RDStationHandler->saveWebhook($json, $idUser);

                //$rk = origem.entidade.ação
                $rk = array('Ploomes', 'ReturnRDStation');
                // $this->rabbitMQServices->publicarMensagem('returnrd_exc', $rk, 'ploomes_returnrd',  $json);

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


}
