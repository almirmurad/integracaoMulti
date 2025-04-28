<?php
namespace src\functions;

use src\exceptions\WebhookReadErrorException;
use src\models\Contact;
use stdClass;


class ServicesFunctions{

    public static function processServiceErpToCrm(array $args, object $ploomesServices, object $formatter, array $action):array
    {
        $message = [];
        $current = date('d/m/Y H:i:s');
        
        $service = $formatter->createObjectCrmServiceFromErpData($args, $ploomesServices);
        $json = $formatter->createCrmServiceFromErpObject($service, $ploomesServices);
        $pService = $ploomesServices->getProductByCode($service->codigo);
        
        if(isset($pService['Id']) || $action['action'] === 'update')
        {
            if($ploomesServices->updatePloomesProduct($json, $pService['Id']))
            {
                $message['success'] = 'Integração concluída com sucesso! Serviço Ploomes id: '.$pService['Id'].' alterado no Ploomes CRM com sucesso em: '.$current;
                return $message;
            }

            throw new WebhookReadErrorException('Erro ao alterar o serviço Ploomes id: '.$pService['Id'].' em: '.$current, 500);  
            
        }else{
            if($ploomesServices->createPloomesProduct($json))
            {
                $message['success'] = 'Serviço '.$service->descricao.' Cadastrado no Ploomes CRM com sucesso! Data: '.$current;
                return $message;
            }
            
            throw new WebhookReadErrorException('Erro ao cadastrar o serviço no Ploomes id: '.$pService['Id'].' em: '.$current, 500);
        }

        return $message;

    }

    

 
    

}