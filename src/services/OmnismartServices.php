<?php

namespace src\services;

use GuzzleHttp\Psr7\Response;
use Dotenv\Dotenv;
use src\contracts\OmnichannelManagerInterface;

class OmnismartServices implements OmnichannelManagerInterface{

    private $baseApi;
    private $apiKey;
    private $method;
    private $headers;

    public function __construct($omnismart){ 
         
        $this->apiKey = "Bearer {$omnismart['api_key']}";
        $this->baseApi = $omnismart['base_api'];
        $this->method = array('get','post','patch','update','delete');
        $this->headers = array('Accept: application/json',
                                'Authorization: ' .$this->apiKey);
    }

    //ENCONTRA A PROPOSTA NO PLOOMES
    public function requestQuote(object $deal):array|null
    {
        /**
         * Quotes?$expand=Installments,OtherProperties,Products($select=Id,Discount),Approvals($select=Id),ExternalComments($select=Id),Comments($select=Id),Template,Deal($expand=Pipeline($expand=Icon,Gender,WinButton,WinVerb,LoseButton,LoseVerb),Stage,Contact($expand=Phones;$select=Name,TypeId,Phones),Person($expand=Phones;$select=Name,TypeId,Phones),OtherProperties),Pages&$filter=Id+eq+'.$deal->lastQuoteId.'&preload=true
         */
        $query = 'Quotes?$expand=Installments,OtherProperties,Products($select=Id,ProductId,ProductName,Quantity,Discount,UnitPrice,Total,Ordination),Products($expand=Product($select=Code,MeasurementUnit))&$filter=Id+eq+'.$deal->lastQuoteId.'&preload=true';

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . $query,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $quote = json_decode($response, true);     

        return $quote['value'][0];

    }

    //ENCONTRA O CNPJ DO CLIENTE NO PLOOMES
    public function contactCnpj(object $deal):string
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Contacts?$filter=Id+eq+' . $deal->contactId . '&$expand=OtherProperties',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $responseCnpj = curl_exec($curl);

        curl_close($curl);

        $responseCnpj = json_decode($responseCnpj, true);
        
        $response = (!empty($responseCnpj['value'][0]['CNPJ'])) ? $responseCnpj['value'][0]['CNPJ'] : false;
       
        return $response;
    }

    //ENCONTRA O EMAIL DO VENDEDOR NO PLOOMES
    public function ownerMail(object $deal):string
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Users?$filter=Id+eq+' . $deal->ownerId . '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $responseMail = curl_exec($curl);

        curl_close($curl);

        $responseMail = json_decode($responseMail, true);

        $response = $responseMail['value'][0]['Email'] ?? false;
        
        return $response;
    }
    //ENCONTRA O ID DO VENDEDOR NO PLOOMES
    public function ownerId(object $deal):string
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Users?$filter=Email+eq+'. "'$deal->mailVendedor'",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $responseMail = curl_exec($curl);

        curl_close($curl);

        $responseMail = json_decode($responseMail, true);

        $response = $responseMail['value'][0]['Id'] ?? false;

        return $response;
    }

    //encontra a venda no ploomes
    public function requestOrder(object $order):array|null
    {
        $id = $order->id ?? $order->lastOrderId;
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Orders?$filter=Id+eq+'. $id .'&$expand=Owner,Contact($expand=OtherProperties),OtherProperties,Products($select=Product,Discount,Quantity,UnitPrice,Id,Ordination;$expand=Product($select=Code,Id;$expand=Group,OtherProperties))&$orderby=Id',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method['0']),
            CURLOPT_HTTPHEADER => $this->headers
        ));
        
        $response = json_decode(curl_exec($curl),true);

        curl_close($curl);
        $order = (empty($response['value'][0])) ? Null : $response['value'][0]; 
        
        return $order;
      
    }

    //CRIA INTERAÇÃO NO PLOOMES
    public function createPloomesIteraction(string $json):bool
    {

        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'InteractionRecords',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>strtoupper($this->method[1]),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl),true);
        curl_close($curl);

        $idIntegration = $response['value'][0]['Id']??Null;

        return ($idIntegration !== null)?true:false;
       
    }

    //encontra cliente no ploomes pelo CNPJ
    public function consultaClientePloomesCnpj(string $cnpj){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi .'Contacts?$filter=CNPJ+eq+'."'$cnpj'",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);

        return $response['value'][0]['Id'] ?? null;

    }

    public function getOrderStages(){
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Orders@Stages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = curl_exec($curl);
        $stage =json_decode($response, true);
       
        curl_close($curl);

       return ($stage['value']);
    }

    //ALTERA O ESTÁGIO DA VENDA NO PLOOMES
    public function alterStageOrder($json, $orderId)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Orders('.$orderId.')',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[2]),
            CURLOPT_POSTFIELDS =>$json,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl), true);
        
        $stage = json_decode($json,true);

       
        curl_close($curl);

       return ($response['value'][0]['StageId'] === $stage['StageId']) ? true :  false;
    }

    //encontra cliente no omnismart pelo Id
    public function userGetOne(string $id):array|null
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'user/' . trim($id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);
        // print_r($response);
        // exit;
        return $response['value'][0];

    }
    //encontra o chat do omnismart pelo id
    public function chatGetOne(string $id):array|null
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . '/v1/chat/' . trim($id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response =json_decode(curl_exec($curl), true);
        
        curl_close($curl);

        return $response;

    }

    //encontra a equipe do omnismart pelo id
    public function getTeamById(string $id):array|null
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . '/v1/team/' . trim($id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response =json_decode(curl_exec($curl), true);
        
        curl_close($curl);

        return $response;

    }

    public function getListByTagName($tagName)
    { 
        $curl = curl_init();

        // Codifica os parâmetros da URL
        $encodedFilter = urlencode("Name eq '$tagName'");
        $url = $this->baseApi . "Products@Lists?\$filter=" . $encodedFilter;

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
           return 'cURL Error: ' . curl_error($curl);
        }
    
        // $info = curl_getinfo($curl); // Informações da requisição
        // var_dump($info);
        $response =json_decode($response, true);
        curl_close($curl);

        return $response['value'][0] ?? false;
    }

    public function createNewListTag($json){

       $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi .'Products@Lists',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[1]),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);

        return $response['value'][0] ?? false;

    }

    //encontra produto no ploomes pelo Id
    public function getProductById(string $id):array|null
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi .'Products?$filter=Id+eq+'.$id.'&$expand=OtherProperties',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);
       
        return $response['value'][0] ?? null;

    }

    //encontra produto no ploomes pelo code
    public function getProductByCode(string $codigo):array|null
    {

        $codigoEncoded = urlencode($codigo);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi .'Products?$filter=Code+eq+'."'$codigoEncoded'".'&$expand=OtherProperties',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);
    
        return $response['value'][0] ?? null;

    }
    //encontra cidade no ploomes pelo Id
    public function getCitiesById(string $id):array|null
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi .'Cities?$filter=Id+eq+'.$id.'&$expand=Country,State',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);
        $response =json_decode($response, true);

        curl_close($curl);

        return $response['value'][0];

    }

    //encontra a cidade pelo codigo IBGE
    public function getCitiesByIBGECode(string $ibgeCode):array|null
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi .'Cities?$filter=IBGECode+eq+'.$ibgeCode,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers

        ));

        $response = curl_exec($curl);
        $response =json_decode($response, true);

        curl_close($curl);

        return $response['value'][0];

    }

    //encontra cliente no ploomes pelo CNPJ
    public function getStateById(string $id):array|null
    {

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $this->baseApi .'Cities@Countries@States?$filter=Id+eq+'.$id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
        CURLOPT_HTTPHEADER => $this->headers

    ));

    $response = curl_exec($curl);
    $response =json_decode($response, true);

    curl_close($curl);

    return $response['value'][0];

    }

    //CRIA CONTACT NO PLOOMES
    public function createPloomesContact(string $json):int
    {
    
        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Contacts?$expand=OtherProperties',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>strtoupper($this->method[1]),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl),true);
        curl_close($curl);

        $idIntegration = $response['value'][0]['Id'] ?? 0;

        return $idIntegration;
       
    }

        //CRIA CONTACT NO PLOOMES
    public function createPloomesPerson(string $json):int
    {
        // print_r($json);
        // exit;
    
        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Contacts',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>strtoupper($this->method[1]),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl),true);

        curl_close($curl);
// print_r($response);
// exit;
        $idIntegration = $response['value'][0]['Id'] ?? 0;

        return $idIntegration;
       
    }



    

    //CRIA Produto NO PLOOMES
    public function createPloomesProduct(string $json):bool|string|int
    {

        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Products?$expand=OtherProperties',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>strtoupper($this->method[1]),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl),true);
        curl_close($curl);

        $idIntegration = $response['value'][0]['Id']??Null;

        return ($idIntegration !== null)?$idIntegration:false;
        
    }

    //ATUALIZA CONTACT NO PLOOMES
    public function updatePloomesContact(string $json, int $idContact):array
    {
            
        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Contacts('.$idContact.')?$expand=OtherProperties,City,State,Country,Owner($select=Id,Name,Email,Phone),Tags($expand=Tag),Phones($expand=Type),LineOfBusiness,Contacts($expand=Phones($expand=Type))',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>strtoupper($this->method[2]),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl),true);

        curl_close($curl);
        
        return $response['value'][0] ?? null;
    
    }

    //ATUALIZA Product NO PLOOMES
    public function updatePloomesProduct(string $json, int $idProduct):bool
    {    
      
        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Products('.$idProduct.')',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>strtoupper($this->method[2]),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl),true);
        curl_close($curl);

        $idIntegration = $response['value'][0]['Id'] ?? Null;

        return ($idIntegration !== null)?true:false;
    
    }

    //DELETA CONTACT NO PLOOMES
    public function deletePloomesContact(int $idPloomes):bool
    {
        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Contacts('.$idPloomes.')',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>strtoupper($this->method[4]),
            CURLOPT_POSTFIELDS => null,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl),true);
        curl_close($curl);

        return ($response !== null) ? false : true;
       
    }
    
    //DELETA Product NO PLOOMES
    public function deletePloomesProduct(int $idPloomes):bool
    {
        //CHAMADA CURL PRA CRIAR WEBHOOK NO PLOOMES
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi . 'Products('.$idPloomes.')',//ENDPOINT PLOOMES
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>strtoupper($this->method[4]),
            CURLOPT_POSTFIELDS => null,
            CURLOPT_HTTPHEADER => $this->headers
        ));

        $response = json_decode(curl_exec($curl),true);
        curl_close($curl);

        return ($response !== null) ? false : true;
       
    }

    //encontra todos os campos customizáveis de contacts
    public function getContactCustomFields():array
    {

        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi .'Fields?$filter=Dynamic+eq+true&$expand=Type($select=NativeType),Entity($select=Id,Name)&$select=Name,Key,TypeId,SendExternalKey,OptionsTableId',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
            
        ));
        
        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);

        return $response['value'];

    }

    public function getOptionsTableById($id):array
    {

        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseApi ."Fields@OptionsTables?\$expand=Options&\$filter=Id+eq+{$id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
            
        ));
        
        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);
        // var_dump($response);
        // exit;
        return $response['value'][0];

    }

    public function getTagsByEntityId($entityId){

        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => "{$this->baseApi}Tags?\$filter=EntityId+eq+{$entityId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
            
        ));
        
        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);

        return $response['value'];

    }

    public function getFamilyByName($family){

        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => "{$this->baseApi}Products@Families?\$filter=Name+eq+'{$family}'",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
            
        ));
        
        $response = curl_exec($curl);
        $response =json_decode($response, true);
        
        curl_close($curl);

        return $response['value'][0] ?? false;

    }

    public function createNewFamily($family){

        $array = ['Name'=> $family];

        $json = json_encode($array);

        $curl = curl_init();
 
         curl_setopt_array($curl, array(
             CURLOPT_URL => $this->baseApi .'Products@Families',
             CURLOPT_RETURNTRANSFER => true,
             CURLOPT_ENCODING => '',
             CURLOPT_MAXREDIRS => 10,
             CURLOPT_TIMEOUT => 0,
             CURLOPT_FOLLOWLOCATION => true,
             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
             CURLOPT_CUSTOMREQUEST => strtoupper($this->method[1]),
             CURLOPT_POSTFIELDS => $json,
             CURLOPT_HTTPHEADER => $this->headers
 
         ));
 
         $response = curl_exec($curl);
         $response =json_decode($response, true);
         
         curl_close($curl);

         return $response['value'][0] ?? false;
 
     }

     public function getGroupByName($group){

        
        
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => "{$this->baseApi}Products@Groups?\$filter=Name+eq+'{$group}'",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($this->method[0]),
            CURLOPT_HTTPHEADER => $this->headers
            
        ));
        
        $response = curl_exec($curl);
        $response =json_decode($response, true);
      
        curl_close($curl);

        return $response['value'][0] ?? false;

     }

     public function createNewGroup($group, $familyId){

        $array = ['Name'=> $group, 'FamilyId'=>$familyId];

        $json = json_encode($array);

        $curl = curl_init();
 
         curl_setopt_array($curl, array(
             CURLOPT_URL => $this->baseApi .'Products@Groups',
             CURLOPT_RETURNTRANSFER => true,
             CURLOPT_ENCODING => '',
             CURLOPT_MAXREDIRS => 10,
             CURLOPT_TIMEOUT => 0,
             CURLOPT_FOLLOWLOCATION => true,
             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
             CURLOPT_CUSTOMREQUEST => strtoupper($this->method[1]),
             CURLOPT_POSTFIELDS => $json,
             CURLOPT_HTTPHEADER => $this->headers
 
         ));
 
         $response = curl_exec($curl);
         $response =json_decode($response, true);
         
         curl_close($curl);

         return $response['value'][0] ?? false;
 
     }

   

}