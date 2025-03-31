<?php
namespace src\handlers;

class InteractionHandler{

    public static function createPloomesIteraction($json, $baseApi, $apiKey){

       
        //CABEÇALHO DA REQUISIÇÃO
        $headers = [
            'User-Key:' . $apiKey,
            'Content-Type: application/json',
        ];
        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $baseApi . '/InteractionRecords',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers
        ));

        $response = curl_exec($curl);
        $decoded = json_decode($response, true);
        $idIntegration = $decoded['value'][0]['Id']??Null;

        // echo"IdInteraction?: ";
        // print_r($idIntegration);
        // exit;
        
        curl_close($curl);
        return ($idIntegration !== null)?true:false;
       
        
    }

}