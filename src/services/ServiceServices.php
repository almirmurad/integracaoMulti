<?php

namespace src\services;

use src\functions\ServicesFunctions;
use src\services\OmieServices;
use src\services\PloomesServices;

class ServiceServices
{

    public static function createServiceFromERPToCRM($service)
    {
        $omieServices = new OmieServices();
        $ploomesServices = new PloomesServices();
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
   
        $current = date('d/m/Y H:i:s');
        $json = ServicesFunctions::createPloomesServiceFromOmieObject($service, $ploomesServices, $omieServices);
        $sPloomes = $ploomesServices->getProductByCode($service->codigo);
        // print_r($sPloomes);
        // exit;
        if($sPloomes === null)
        {
        
            if(!$ploomesServices->createPloomesProduct($json)){
                $messages['error'] = 'Erro ao cadastrar o serviço ('.$service->descricao.') Data:' .$current;
            }else{
                //aqui poderia enviar ao omie o codigo do serviço de integração (id serviço no ploomes)
                $messages['success'] = 'Serviço ('.$service->descricao.') Cadastrado no Ploomes CRM com sucesso! Data: '.$current;
            }

        }else{
            $service->idPloomes = $sPloomes['Id'];
            if(!$ploomesServices->updatePloomesProduct($json, $service->idPloomes)){
                $messages['error'] = 'Erro ao cadastrar/alterar o serviço ('.$service->descricao.') Data:' .$current;
            }else{
                //aqui poderia enviar ao omie o codigo do serviço de integração (id serviço no ploomes)
                $messages['success'] = 'Serviço ('.$service->descricao.') Cadastrado/alterado no Ploomes CRM com sucesso! Data: '.$current;
            }
        }
        return $messages;
    }

    public static function updateServiceFromERPToCRM($json, $service, $ploomesServices)
    {
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        $current = date('d/m/Y H:i:s');
        $ploomesProduct = $ploomesServices->getProductByCode($service->codigo);//product e services são iguais no ploomes

        if(!$ploomesProduct){
            $messages['error'] = 'Erro: Serviço '.$service->descricao.' não foi encontrado no Ploomes CRM - '.$current;
        }else{
            $ploomesServices->updatePloomesProduct($json, $ploomesProduct['Id']);
            $messages['success'] = 'Serviço '.$service->descricao.' alterado no Ploomes CRM com sucesso! - '.$current;
        }

        return $messages;
    }

    public static function deleteServiceFromERPToCRM($service, $ploomesServices)
    {
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
   
        $current = date('d/m/Y H:i:s');

        //verificar se existe um serviço cadastrado no ploomes com o Codigo e se existir deleta
        $pProduct = $ploomesServices->getProductByCode($service->codigo);
       
        if($pProduct !== null){
            $ploomesServices->deletePloomesProduct($pProduct['Id']);
            $messages['success'] = 'Serviço '.$service->descricao.' excluído do Omie ERP e do Ploomes CRM. Data: '.$current;
        }else{
         
            $messages['error'] = 'Erro ao exluir o serviço '.$service->descricao.' Não foi encontrado no Ploomes. Data: '.$current ;
        }

        return $messages;
    }
}