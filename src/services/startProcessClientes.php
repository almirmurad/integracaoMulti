<?php
$headers = [
    
    'Content-Type: application/json',
];
$array = [
    'status'=>'1',
];
$json = json_encode($array);
$uri = 'http://localhost/integracao/public/proccessAlterClientOmie';
 //$uri = 'https://https://fiel.bicorp.online/public/proccessAlterClientOmie';

$curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>$json,
            CURLOPT_HTTPHEADER => $headers

        ));

        $response = curl_exec($curl);

        curl_close($curl);

       var_dump($response);
