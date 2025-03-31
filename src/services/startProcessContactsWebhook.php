<?php
// require '../../vendor/autoload.php';
// use GuzzleHttp\Client;

// $client = new Client();

// // $response = $client->request('GET','https://fiel.dev-webmurad.com.br/public/processWinDeal');
//  $response = $client->post('http://localhost/integracao/public/processNewContact',[
//     'headers' => [
//         'Authorization' => 'Bearer your-token',
//         'Accept'        => 'application/json',
//     ],
//     'json' => [
//         'status'=>'1',
//         'entity'=>'Contacts'
//     ]

//  ]);

// $code = $response->getStatusCode();
// // $body = json_decode($response->getBody(),true); 
// $body = $response->getBody(); 

$headers = [
    
    'Content-Type: application/json',
];
$array = [
    'status'=>'1',
    'entity'=>'Contacts'
];
$json = json_encode($array);
$uri = 'https://gamatermic.bicorp.online/public/processNewContact';

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

       print_r($response);