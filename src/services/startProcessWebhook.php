<?php
// require '../../vendor/autoload.php';
// use GuzzleHttp\Client;
// use GuzzleHttp\Handler\CurlFactory;
// use GuzzleHttp\Handler\CurlHandler;
// $client = new Client();

// // $response = $client->request('GET','https://fiel.dev-webmurad.com.br/public/processWinDeal');
//  $response = $client->post('http://localhost/integracao/public/processWinDeal',[
//     'headers' => [
//         'Authorization' => 'Bearer your-token',
//         'Accept'        => 'application/json',
//     ],
//     'json' => [
//         'status'=>'1',
//         'entity'=>'Deals'
//     ]

//  ]);

// $code = $response->getStatusCode();
// // $body = json_decode($response->getBody(),true); 
// $body = $response->getBody(); 

// echo 'body: '.$body;
// $server = $_SERVER;
// print '<pre>';
// print_r($server);
// print '</pre>';
// exit;
$headers = [
    
    'Content-Type: application/json',
];
$array = [
    'status'=>'1',
    'entity'=>'Deals'
];
$json = json_encode($array);
$uri = 'https://gamatermic.bicorp.online/public/processWinDeal';

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
