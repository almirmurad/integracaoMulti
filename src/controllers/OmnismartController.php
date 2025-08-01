<?php

namespace src\controllers;

use core\Controller;
use src\exceptions\WebhookReadErrorException;
use src\factories\ErpFormatterFactory;
use src\functions\DiverseFunctions;
use src\handlers\LoginHandler;
use src\handlers\OmnismartHandler;
use src\services\DatabaseServices;
use src\services\OmnismartServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class OmnismartController extends Controller
{

    private $loggedUser;
    private $ploomesServices;
    private $databaseServices;
    private $rabbitMQServices;
    private $omnismartServices;


    public function __construct($args)
    {
        $ploomesBase = $args['Tenancy']['ploomes_bases'][0];
        $omnismart = $args['Tenancy']['omnichannel'][0];

        $args['Tenancy']['vhost'][0]['key'] = $args['Tenancy']['tenancies']['cpf_cnpj'];
        $vhost = $args['Tenancy']['vhost'][0];
        $this->ploomesServices = new PloomesServices($ploomesBase);
        $this->databaseServices = new DatabaseServices();
        $this->omnismartServices = new OmnismartServices($omnismart);
        $this->rabbitMQServices = new RabbitMQServices($vhost);
    }

    private function getOmnismartHandler(): OmnismartHandler
    {   
        $omnismartHandler = new OmnismartHandler($this->ploomesServices, $this->databaseServices, $this->omnismartServices);
        return $omnismartHandler;
    }

    public function transferChat($args)
    {
        $message = [];
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $json = json_encode($args['body']);
        
        try {
            
            $omnismartHandler = $this->getOmnismartHandler();            
            $action = DiverseFunctions::findAction($args);            
             if ($action['type'] !== 'ASSIGN') {
                throw new WebhookReadErrorException('Não havia uma atribuição do chat ao agente do omnismart', 500);       
            }
            $response = $omnismartHandler->saveClientHook($json, $idUser);

            // $rk = origem.entidade.ação
            $rk = array('Omnismart', 'Contacts');
            $this->rabbitMQServices->publicarMensagem('contacts_exc', $rk, 'omnismart_contacts',  $json);
            

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
                file_put_contents('./assets/logOmni.txt', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                return print 'ERROR:' . $message['status_code'] . '. MESSAGE: ' . $message['status_message'];
            }

            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logOmni.txt', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
            return print $message['status_message'];
        }
    }

    public function processNewTransferChat($args)
    {
       
        $message = [];
        // processa o webhook 
        try 
        {
            $omnismartHandler = $this->getOmnismartHandler();
            $response = $omnismartHandler->startProcess($args);

            $message = [
                'status_code' => 200,
                'status_message' => $response['success'],
            ];

        } 
        catch (WebhookReadErrorException $e){
        } 
        finally 
        {
            ob_start();
            if (isset($e)) {

                $message = [
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
                var_dump($message);
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/logOmni.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                $m = json_encode($message);
                return print_r($m);
            }
            var_dump($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logOmni.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
            $m = json_encode($message);
            return print_r($m);
        }
    }

}
