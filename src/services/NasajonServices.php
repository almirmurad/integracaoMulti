<?php

namespace src\services;

use phpseclib3\Math\BigInteger\Engines\PHP;
use src\contracts\ErpManagerInterface;
use src\exceptions\WebhookReadErrorException;

class NasajonServices implements ErpManagerInterface
{
    public string $accessToken;
    public DatabaseServices $databaseServices;

    public function __construct($erpBases){

        $this->databaseServices = new DatabaseServices();
        $this->accessToken = $this->authentication($erpBases);

    }

    private function isAccessTokenExpired($expiresIn): bool
    {

        $expirationTime = strtotime($expiresIn);
        $currentTime = time();

        return $currentTime >= $expirationTime;

    }

    private function refreshToken($erpBases)
    {
        
        //$uri = 'https://auth.nasajon.com.br/auth/realms/master/protocol/openid-connect/token';
        $credentials = [];
        $credentials['client_id'] = $erpBases[0]['client_id'];
        $credentials['client_secret']=$erpBases[0]['client_secret'];
        $credentials['grant_type'] = 'refresh_token';
        $credentials['refresh_token'] = $erpBases[0]['refresh_token'];
    
        // $data = http_build_query($credentials);

        //POST https://auth.nasajon.com.br/auth/realms/master/protocol/openid-connect/token HTTP/1.1
        //content-type: application/x-www-form-urlencoded

        //client_id=<CIENT_ID>&client_secret=<CLIENT_SECRET>&grant_type=refresh_token&refresh_token=<REFRESH_TOKEN>

        // $curl = curl_init();

        // curl_setopt_array($curl, array(
        //     CURLOPT_URL => $uri,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_ENCODING => '',
        //     CURLOPT_MAXREDIRS => 10,
        //     CURLOPT_TIMEOUT => 10,
        //     CURLOPT_FOLLOWLOCATION => true,
        //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //     CURLOPT_POST => true,
        //     CURLOPT_CUSTOMREQUEST => 'POST',
        //     CURLOPT_POSTFIELDS => $data,
        //     CURLOPT_HTTPHEADER => array(
        //         'Content-Type: application/x-www-form-urlencoded',
        //         'Content-Length: ' . strlen($data)
        //     ),
        // ));

        // $response = curl_exec($curl);

        // curl_close($curl);

        // HTTP/1.1 200 OK 
        // Content-Type: application/json

       $response ='{ 
                        "access_token": "eyJhbGciOiJSUzUxMiIsInR5cCIgOiExLtIz...",
                        "expires_in": 86400, 
                        "refresh_expires_in": 0, 
                        "refresh_token": "eyJhbGciOiJSUzUxMiIsInR5cCIgOiExLtIz...", 
                        "token_type": "bearer", 
                        "not-before-policy": 1611009366, 
                        "session_state": "4fe8e300-d5af-4f28-9879-35efd5ab50e5", 
                        "scope": "profile email offline_access"
                    }';

        $authentication = json_decode($response, true);

        $erpBases[0]['access_token'] = $authentication['access_token'];
        $erpBases[0]['refresh_token'] = $authentication['refresh_token'];
        $erpBases[0]['expires_in'] = date('Y-m-d H:i:s', $authentication['expires_in'] + time());
        $erpBases[0]['auth_time'] = date('Y-m-d H:i:s', time());
        
        $this->databaseServices->setNasajonInfo($erpBases[0]);
        
        
        return $authentication['access_token'];

    }

    private function authentication($erpBases): string
    {
        //se tem token 
        if($erpBases[0]['access_token'] != null){
            //verifica se está expirado
            if($this->isAccessTokenExpired($erpBases[0]['expires_in']))
            {
                //refresh_token
                return $this->refreshToken($erpBases);
            }else{
                //retorna o token que já existe e ainda nã epirou
                return $erpBases[0]['access_token'];
            }
        }

        //$uri = 'https://auth.nasajon.com.br/auth/realms/master/protocol/openid-connect/token';
        $credentials = [];
        $credentials['client_id'] = $erpBases[0]['client_id'];
        $credentials['client_secret']=$erpBases[0]['client_secret'];
        $credentials['username'] = $erpBases[0]['email'];
        $credentials['password'] = $erpBases[0]['password'];
        $credentials['scope'] = 'offline_access';
        $credentials['grant_type'] = 'password';
        

        //$data = http_build_query($credentials);

        //POST https://auth.nasajon.com.br/auth/realms/master/protocol/openid-connect/token HTTP/1.1
        //content-type: application/x-www-form-urlencoded

        //client_id=<CLIENT_ID>&client_secret=<CLIENT_SECRET>&scope=offline_access&grant_type=password&username=<EMAIL LOGIN USUARIO>&password=<SENHA>

        // $curl = curl_init();

        // curl_setopt_array($curl, array(
        //     CURLOPT_URL => $uri,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_ENCODING => '',
        //     CURLOPT_MAXREDIRS => 10,
        //     CURLOPT_TIMEOUT => 10,
        //     CURLOPT_FOLLOWLOCATION => true,
        //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //     CURLOPT_POST => true,
        //     CURLOPT_CUSTOMREQUEST => 'POST',
        //     CURLOPT_POSTFIELDS => $data,
        //     CURLOPT_HTTPHEADER => array(
        //         'Content-Type: application/x-www-form-urlencoded',
        //         'Content-Length: ' . strlen($data)
        //     ),
        // ));

        // $response = curl_exec($curl);

        // curl_close($curl);

        $response = '{ 
                        "access_token": "eyJhbGciOiJSUzUxMiIsInR5cCIgOiAiSldU...",
                        "expires_in": 86400, 
                        "refresh_expires_in": 0, 
                        "refresh_token": "eyJhbGciOiJSUzUxMiIsInR5cCIgOiAiSldU...", 
                        "token_type": "bearer", 
                        "not-before-policy": 1611009366, 
                        "session_state": "4fe8e300-d5af-4f28-9879-35efd5ab50e5", 
                        "scope": "profile email offline_access"
                    }';

        $authentication = json_decode($response, true);

        if(isset($authentication['error'])){
            throw new WebhookReadErrorException("Erro ao autenticar API Nasajon: {$authentication['error']}", 401);
        }else{
            
            $erpBases[0]['access_token'] = $authentication['access_token'];
            $erpBases[0]['refresh_token'] = $authentication['refresh_token'];
            $erpBases[0]['expires_in'] = date('Y-m-d H:i:s', $authentication['expires_in'] + time());
            $erpBases[0]['auth_time'] = date('Y-m-d H:i:s', time());
            
            $this->databaseServices->setNasajonInfo($erpBases[0]);
            
            return $authentication['access_token'];

        }

        
        

        // HTTP/1.1 200 OK 
        // Content-Type: application/json

        // { 
        //     "access_token": "eyJhbGciOiJSUzUxMiIsInR5cCIgOiAiSldU...",
        //     "expires_in": 86400, 
        //     "refresh_expires_in": 0, 
        //     "refresh_token": "eyJhbGciOiJSUzUxMiIsInR5cCIgOiAiSldU...", 
        //     "token_type": "bearer", 
        //     "not-before-policy": 1611009366, 
        //     "session_state": "4fe8e300-d5af-4f28-9879-35efd5ab50e5", 
        //     "scope": "profile email offline_access"
        // }


        return $json;
    }



    public function clientIdErp(object $omie, string $contactCnpj)
    {
        return '1234';
    }

    public function criaClienteERP(string $json)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.nasajon.app/dados-mestre/erp3/2531/clientes',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer '.$this->accessToken,
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $cliente = json_decode($response, true);

        return $cliente;


    }

    public function vendedorIdErp(object $omie, string $mailVendedor)
    {
        
    }

    public function buscaIdProductErp(object $omie, string $idItem)
    {
        
    }

    public function criaPedidoErp(string $json)
    {
        
    }

    public function criaOSErp(object $omie, object $os, array $structureOS)
    {
        
    }

    public function clienteCnpjErp(object $omie)
    {
        
    }

    public function consultaPedidoErp(object $omie, int $idPedido)
    {
        
    }

    public function consultaNotaErp(object $omie, int $idPedido)
    {
        
    }



}