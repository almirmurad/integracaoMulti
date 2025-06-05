<?php

namespace src\controllers;

use core\Controller;
use src\exceptions\WebhookReadErrorException;
use src\factories\ErpFormatterFactory;
use src\functions\DiverseFunctions;
use src\handlers\FinancialHandler;
use src\handlers\LoginHandler;
use src\services\DatabaseServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class FinancialController extends Controller
{

    private $loggedUser;
    private $ploomesServices;
    private $databaseServices;
    private $rabbitMQServices;


    public function __construct($args)
    {
        $ploomesBase = $args['Tenancy']['ploomes_bases'][0];

        $args['Tenancy']['vhost'][0]['key'] = $args['Tenancy']['tenancies']['cpf_cnpj'];
        $vhost = $args['Tenancy']['vhost'][0];
        $this->ploomesServices = new PloomesServices($ploomesBase);
        $this->databaseServices = new DatabaseServices();
        //$this->rabbitMQServices = new RabbitMQServices($vhost);
    }

    private function getFinancialHandler($args): FinancialHandler
    {   
        $formatter = ErpFormatterFactory::create($args);
        $financialHandler = new FinancialHandler($this->ploomesServices, $this->databaseServices, $formatter);

        return $financialHandler;
    }

    //Ploomes
    //recebe webhook de cliente criado, alterado e excluído do PLOOMES CRM
    public function ploomesFinancial($args)
    {
        $message = [];
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $json = json_encode($args['body']);
        
        try {

            $action = DiverseFunctions::findAction($args);
       
            if($action['type'] === 'pessoa' && $args['body']['New']['CompanyId'] === null){
                throw new WebhookReadErrorException('Cadastro de pessoa sem empresa referenciada', 500);
            }

            if($action['action'] === "update"){

                $ignorar = ['LastUpdateDate', 'UpdaterId'];
                $diferencas = DiverseFunctions::compareArrays($args['body']['Old'], $args['body']['New'], $ignorar);
    
                if(empty($diferencas)){
                    throw new WebhookReadErrorException('Não houve alteração no array', 500);
                }
            }
            
            $financialHandler = $this->getFinancialHandler($args);
            $response = $financialHandler->saveFinancialHook($json, $idUser);

            // $rk = origem.entidade.ação
            $rk = array('Ploomes', 'Financial');
            //$this->rabbitMQServices->publicarMensagem('financial_exc', $rk, 'ploomes_financial',  $json);

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

    //processa contatos e clientes do ploomes ou do ERP
    public function processNewFinancial($args)
    {
        $message = [];
        // processa o webhook 
        try {
            $financialHandler = $this->getFinancialHandler($args);
            $response = $financialHandler->startProcess($args);

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
                file_put_contents('./assets/logClient.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                $m = json_encode($message);
                return print_r($m);
            }
            var_dump($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logClient.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
            $m = json_encode($message);
            return print_r($m);
        }
    }

    //ERP
    //recebe webhook de cliente criado, alterado e excluído do ERP ERP
    public function financialIssued($args)
    {
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $json = json_encode($args['body']);

        $message = [];

        try {

            $financialHandler = $this->getFinancialHandler($args);
            $response = $financialHandler->saveFinancialHook($json, $idUser);

            // $rk = origem.entidade.ação
            $rk = array('Erp', 'Financial');
            //$this->rabbitMQServices->publicarMensagem('financial_exc', $rk, 'erp_financial',  $json);

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
