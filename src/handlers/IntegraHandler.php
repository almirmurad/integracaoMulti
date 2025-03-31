<?php

namespace src\handlers;

class IntegraHandler
{
    //CRIA UM NOVO WEBHOOK RETORNA MENSAGEM FLASH NA VIEW
    public static function createWebhook($entityId, $actionId, $callbackUrl, $validationKey, $apiKey, $baseApi)
    {
        //ARRAY COM ITENS PARA CRIAR O WEBHOOK
        $data = [
            'EntityId' => $entityId, //ID DA ENTIDADE(DEAL)
            'ActionId' => $actionId, //ID DA AÇÃO(WIN)
            'CallbackUrl' => $callbackUrl, //URL QUE VAI RECEBER O WEBHOOK 
            'ValidationKey' => $validationKey //CHAVE VALIDAÇÃO DO PLOOMES
        ];

        //TRANSFORMA O ARRAY DE ITENS EM JSON
        $json = json_encode($data);
        //CABEÇALHO DA REQUISIÇÃO
        $headers = [
            'User-Key:' . $apiKey,
            'Content-Type: application/json',
        ];
        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $baseApi . '/Webhooks',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        if ($response) {
            return true;
        }
        return false;
    }
    //PEGA TODOS OS WEBHOOKS CADASTRADOS LISAT TODOS NA VIEW
    public static function getAll($baseApi, $method, $apiKey)
    {
        $headers = [
            'User-Key:' . $apiKey,
            'Content-Type: application/json',
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $baseApi . '/Webhooks',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $array = json_decode($response, true);

        if($array !== null){
            $return = [];
            foreach ($array['value'] as $item) {
                $key['Id'] = $item['Id'];
                $key['EntityId'] = $item['EntityId'];
                $key['ActionId'] = $item['ActionId'];
                $key['CallbackUrl'] = $item['CallbackUrl'];
                $key['CreatorId'] = $item['CreatorId'];
                $key['CreateDate'] = $item['CreateDate'];
                $key['Active'] = $item['Active'];
                $return[] = $key;
            }
    
            return $return;
        }
        return null;
    }
    //DELETA O WEBHOOK
    public static function delHook($id, $baseApi, $method, $apiKey)
    {
        $headers = [
            'User-Key:' . $apiKey,
            'Content-Type: application/json',
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $baseApi . '/Webhooks(' . $id . ')',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers

        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }
   
}