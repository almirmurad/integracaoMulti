<?php

namespace src\services;

use src\contracts\MktManagerInterface;
use src\exceptions\WebhookReadErrorException;

class RDStationServices implements MktManagerInterface
{
    public string $accessToken;
    public DatabaseServices $databaseServices;

    public function __construct(){

        $this->databaseServices = new DatabaseServices();

    }

    public function authenticate(array $args): string
    {
        $this->accessToken = $this->authentication($args);
        return $this->accessToken;
    }

    private function isAccessTokenExpired($expiresIn): bool
    {

        $expirationTime = strtotime($expiresIn);
        $currentTime = time();

        return $currentTime >= $expirationTime;

    }

    private function refreshToken($credentials){
        
        $uri = 'https://api.rd.services/auth/token';

        $data = [];
        $data['client_id'] = $credentials['client_id'];
        $data['client_secret'] = $credentials['client_secret'];
        $data['refresh_token'] = $credentials['refresh_token'];
    
        $json = json_encode($data);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'accept: application/json',
                'content-type: application/json'
                
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $authentication = json_decode($response, true);

        $revokedToken['access_token'] = $authentication['access_token'];
        $revokedToken['refresh_token'] = $authentication['refresh_token'];
        $revokedToken['expires_in'] = date('Y-m-d H:i:s', $authentication['expires_in'] + time());
        $revokedToken['auth_time'] = date('Y-m-d H:i:s', time());
        $revokedToken['id-mkt_platform'] = $credentials['id-mkt_platform'];
        
        $this->databaseServices->setRdStationAuthInfo($revokedToken);

        return $authentication['access_token'];

    }


    public function getRDStationCredentials($args)
    {
         
        $credentials = [];
        $code = $args['query']['code'] ?? null;
        $decoded = $args['body'] ?? null;

        if($code !== null ){
                    
            $state = $args['query']['state'] ?? null;

            if($state !== null){
                $explode = explode('-', $state);
                $tenancyIdQueryString = $explode[0];
                $mktPlatformNameQueryString = urldecode($explode[1]);
            }

            if($args['Tenancy']['tenancies']['id'] === intval($tenancyIdQueryString)){

                $mkt_platforms = $args['Tenancy']['mkt_platform'];
                foreach($mkt_platforms as $mkt){
                    if($mkt['app_name'] === $mktPlatformNameQueryString){

                        $credentials['client_secret'] = $mkt['client_secret'];
                        $credentials['client_id'] = $mkt['client_id'];
                        $credentials['id-mkt_platform'] = $mkt['Id'];
                        $credentials['access_token'] = $mkt['access_token'] ?? null;
                        $credentials['expires_in'] = $mkt['expires_in'] ?? null;
                        $credentials['code'] = $code;
                    }
                }

            }

        }elseif($decoded !== null){
            
            $nomeFunilRd = $decoded['contact']['cf_funil'] ?? null;
            
            if($nomeFunilRd === null){
                throw new WebhookReadErrorException('O funil do RD não foi informado no Webhook', 500);
            }

            foreach($args['Tenancy']['mkt_platform'] as $appData){
                
                if(mb_strpos(mb_strtolower($appData['app_name']), mb_strtolower($nomeFunilRd)))
                {                   
                    $credentials['client_secret'] = $appData['client_secret'];
                    $credentials['client_id'] = $appData['client_id'];
                    $credentials['id-mkt_platform'] = $appData['Id'];
                    $credentials['access_token'] = $appData['access_token'] ?? null;
                    $credentials['refresh_token'] = $appData['refresh_token'] ?? null;
                    $credentials['expires_in'] = $appData['expires_in'] ?? null;
                    $credentials['code'] = $code;
                }
                
            }
            
        }else{
            throw new WebhookReadErrorException('não foi encontrado RDStation CODE nem os dados do app na base dados', 500);
        }

        return $credentials;

    }

    private function authentication(array $args)
    {
        
        if(!isset($args['query']['code']) || empty($args['query']['code'])){

            $credentials = $this->getRDStationCredentials($args);
            
            //se tem token 
            if($credentials['access_token'] != null){
                //verifica se está expirado
                if($this->isAccessTokenExpired($credentials['expires_in']))
                {
                    //refresh_token
                    return $this->refreshToken($credentials);
                }else{
                    //retorna o token que já existe e ainda nã epirou
                    return $credentials['access_token'];
                }
            }
            
        }

        $mkt_data = $this->getRDStationCredentials($args);
       
        $idMktPlatform = $mkt_data['id-mkt_platform'];

        unset($mkt_data['id-mkt_platform']);

        $credentials = json_encode($mkt_data);
        
        $uri = 'https://api.rd.services/auth/token?token_by=code';

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $credentials,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json',)
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $authentication = json_decode($response, true); 

        if(isset($authentication['errors'])){
            foreach($authentication['errors'] as $error){
                
                throw new WebhookReadErrorException("Erro ao autenticar API RDStation: Tipo do erro: {$error['error_type']} Mensagem: {$error['error_message']}", 401);
            }
        }else{
            
            $authInfo['access_token'] = $authentication['access_token'];
            $authInfo['id-mkt_platform'] = $idMktPlatform;
            $authInfo['refresh_token'] = $authentication['refresh_token'];
            $authInfo['expires_in'] = date('Y-m-d H:i:s', $authentication['expires_in'] + time());
            $authInfo['auth_time'] = date('Y-m-d H:i:s', time());
            
            $this->databaseServices->setRdStationAuthInfo($authInfo);
            
            return $authentication['access_token'];

        }

    }

    private function decrypt($mkt_platforms){
        
        $cpf_cnpj = $mkt_platforms[0]['key']; // A mesma URL usada antes
        $key = hash('sha256', $cpf_cnpj, true); // Gera a mesma chave de 32 bytes
        $encryptedData = base64_decode($mkt_platforms[0]['password']); // Recupera do banco
        $iv = substr($encryptedData, 0, 16); // Extrai IV
        $encryptedPass = substr($encryptedData, 16);
        $decryptedPass = openssl_decrypt($encryptedPass, 'aes-256-cbc', $key, 0, $iv);

        return $decryptedPass;
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
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);

        curl_close($curl);

        if ($curlErrno) {
            return [
                'success' => false,
                'error' => "Erro cURL: {$curlError}",
                'http_code' => $httpCode,
                'response' => null
            ];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'success' => false,
                'error' => "Erro HTTP: código {$httpCode}",
                'http_code' => $httpCode,
                'response' => $response
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'http_code' => $httpCode,
            'response' => $response
        ];

    }

    public function editaClienteERP(string $json, string $id)
    {
        $url = "https://api.nasajon.app/dados-mestre/erp3/2531/clientes/{$id}";
   
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer '.$this->accessToken,
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);

        curl_close($curl);

        if ($curlErrno) {
            return [
                'success' => false,
                'error' => "Erro cURL: {$curlError}",
                'http_code' => $httpCode,
                'response' => null
            ];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'success' => false,
                'error' => "Erro HTTP: código {$httpCode}",
                'http_code' => $httpCode,
                'response' => $response
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'http_code' => $httpCode,
            'response' => $response
        ];


    }

    public function vendedorIdErp(object $omie, string $mailVendedor)
    {
        
    }

    public function buscaIdProductErp(object $omie, string $idItem)
    {
        
    }

    public function criaPedidoErp(string $json, string $url):array
    {
        return [];   
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