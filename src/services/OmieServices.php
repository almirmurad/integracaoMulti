<?php

namespace src\services;

use src\functions\DiverseFunctions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Response;
use src\contracts\ErpManagerInterface;
use src\exceptions\WebhookReadErrorException;

class OmieServices implements ErpManagerInterface{

    private $appName;
    private $appKey;
    private $appSecret;
    private $ncc;
    private $tenancyId;

    public function __construct($appK = null, $omieBases = null)
    {   
         $omieBase = [];
        foreach($omieBases as $oBase){

            if($oBase['app_key'] == $appK){
                $omieBase['id'] = $oBase['id'];
                $omieBase['app_name'] = $oBase['app_name'];
                $omieBase['app_secret'] = $oBase['app_secret'];
                $omieBase['app_key'] = $oBase['app_key'];
                $omieBase['ncc'] = $oBase['ncc'];
                $omieBase['tenancy_id'] = $oBase['tenancy_id'];
            }
        }


        $this->appName = $omieBase['app_name'] ?? null;
        $this->appKey = $omieBase['app_key'] ?? null;
        $this->appSecret = $omieBase['app_secret'] ?? null;
        $this->ncc = $omieBase['ncc'] ?? null;
        $this->tenancyId = $omieBase['tenancy_id'] ?? null;
    }

    public function getOmieApp(){

        $omieBase =[];
        $omieBase['app_name'] = $this->appName;
        $omieBase['app_secret'] = $this->appSecret;
        $omieBase['app_key'] = $this->appKey;
        $omieBase['ncc'] = $this->ncc;
        $omieBase['tenancy_id'] = $this->tenancyId;
        
        return $omieBase;
    }

    public function clienteCnpjErp($order)
    {
        $jsonOmieIdCliente = [
            'app_key' => $order->appKey ,
            'app_secret' => $order->appSecret,
            'call' => 'ConsultarCliente',
            'param' => [
                [
                    'codigo_cliente_omie'=>$order->codCliente
                ]
            ]
                ];
    

        $jsonCnpj = json_encode($jsonOmieIdCliente);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonCnpj,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $cliente = json_decode($response, true);
        $cnpj = DiverseFunctions::limpa_cpf_cnpj($cliente['cnpj_cpf']);

        return $cnpj;
    }
 
    //PEGA O ID DO CLIENTE DO OMIE
    public function clientIdErp($omie, $contactCnpj)
    {      
        $jsonOmieIdCliente = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ListarClientes',
            'param' => [
                [
                    'clientesFiltro'=>['cnpj_cpf'=> $contactCnpj]
                ]
            ]
                ];

        $jsonCnpj = json_encode($jsonOmieIdCliente);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonCnpj,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $cliente = json_decode($response, true);
        
        $idClienteOmie = $cliente['clientes_cadastro'][0]['codigo_cliente_omie'];
        
        return $idClienteOmie;
    }

    //PEGA O ID DO vendedor DO OMIE
    public function vendedorIdErp($omie, $mailVendedor)
    {

        $jsonOmieVendedor = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ListarVendedores',
            'param' => [
                [
                    'filtrar_por_email'=>$mailVendedor
                    ]
                    ]
                ];
                
                $jsonVendedor = json_encode($jsonOmieVendedor);
                
                $curl = curl_init();
                
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/vendedores/',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => $jsonVendedor,
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json'
                    ),
                ));
                
                $response = curl_exec($curl);
                
                curl_close($curl);
                
                $vendedor = json_decode($response,true);
                
                $codigoVendedor = '';
                if(!isset($vendedor['cadastro'])){
                    return null;
                }
                $arrayVendedores = $vendedor['cadastro'];
                if(count($arrayVendedores) > 0){
                    foreach($arrayVendedores as $itArrVend){
                        
                        if(isset($itArrVend['inativo']) && $itArrVend['inativo'] === 'N'){
                            $codigoVendedor = $itArrVend['codigo'];
                        }else{
                            return null;
                        }
                    }
                }else{
                    return null;
                }
      
        return $codigoVendedor;
    }

    //PEGA O ID DO vendedor DO OMIE
    public function getMailVendedorById($omie, $contact)
    {
        $jsonOmieVendedor = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ConsultarVendedor',
            'param' => [
                [
                    'codigo'=>$contact->codigoVendedor,

                ]
            ]
                ];

        $jsonVendedor = json_encode($jsonOmieVendedor);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/vendedores/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonVendedor,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $vendedor = json_decode($response,true);
        
        
        return $vendedor['email'] ?? null;
    }

    //BUSCA O ID DE UM PRODUTO BASEADO NO CODIGO DO PRODUTO NO PLOOMES
    public function buscaIdProductErp($omie, $idItem)
    {
        $jsonId = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ConsultarProduto',
            'param' => [
                [
                    'codigo'=>$idItem
                ]
            ],
        ];

        $jsonId = json_encode($jsonId);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/produtos/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonId,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $item = json_decode($response);

        
        $id = $item->codigo_produto;
        
        return $id;

    }

    // INSERE O PROJETO NO OMIE
    public function insertProject($omie, $projectName)
    {
        $array = [
            'app_key' =>   $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'IncluirProjeto',
            'param'=>[
                [
                    'codint'=> $projectName,
                    'nome'=>$projectName,
                    'inativo'=> "N"
                ]
            ],
        ];

        $json = json_encode($array);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/projetos/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
       
        $response = json_decode($response,true);
        
        return $response;


    }

    // DELETA O PROJETO NO OMIE
    public function deleteProject($json){
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/projetos/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
       
        $response = json_decode($response,true);
        
        return $response;


    }

    // CRIA PEDIDO DE VENDA DE PRODUTO NO OMIE
    public function criaPedidoErp(string $json, string $url)
    {      
       
        // {"app_key":"4194053472609","app_secret":"43cf1d9c1d63974acf152aeeab8777ef","call":"IncluirPedido","param":[{"cabecalho":{"codigo_cliente":"7118052178","codigo_pedido_integracao":"VEN_PRD\/406775106","data_previsao":"30\/04\/2025","etapa":"10","numero_pedido":406775106,"codigo_parcela":"000","origem_pedido":"API"},"det":[{"ide":{"codigo_item_integracao":466694095},"produto":{"quantidade":1,"valor_unitario":140,"codigo_produto":"7121784805"},"inf_adic":{"numero_pedido_compra":null,"item_pedido_compra":1}},{"ide":{"codigo_item_integracao":466694096},"produto":{"quantidade":2,"valor_unitario":53,"codigo_produto":"7121784805"},"inf_adic":{"numero_pedido_compra":null,"item_pedido_compra":2}}],"frete":{"modalidade":null},"informacoes_adicionais":{"codigo_categoria":"1.01.03","codigo_conta_corrente":"1234","numero_pedido_cliente":"0","codVend":null,"codproj":null,"dados_adicionais_nf":null},"observacoes":{"obs_venda":null}}]}
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        // print_r($response);
        // exit;
        
        return json_decode($response, true);
      
        //return $body;

    }

    // CRIA UMA ORDEM DE SERVIÇO NO OMIE
    public function criaOSErp(object $omie, object $newOS, array $structureOS)
    {
        $array = [
            'app_key' =>   $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'IncluirOS',
            'param'=>[$newOS],
        ];

        // $client = new Client([
            //     'handler' => new CurlHandler([
                //          'handle_factory' => new CurlFactory(0)
                //     ])
                // ]);
                
                // $response = $client->post('https://app.omie.com.br/api/v1/servicos/os/',[
                    //     "json" => $array
                    // ]);
                    
                    // $body = json_decode($response->getBody(),true); 
                    
                    // return $body;    
        
        $json = json_encode($array);
        // print_r($json);
        // exit;      
                    
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/servicos/os/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        return json_decode($response, true);
    }

        // busca o pedido através do Id do OMIE
    public function consultaVendaERP(string $json, string $url)
    {

       
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        return json_decode($response, true);

    } 

    // busca o pedido através do Id do OMIE
    public function consultaPedidoErp(object $omie, int $idPedido)
    {
        
        $array = [
                    'app_key'=>$omie->appKey,
                    'app_secret'=>$omie->appSecret,
                    'call'=>'ConsultarPedido',
                    'param'=>[
                            [
                                'codigo_pedido'=>$idPedido,
                            ]
                        ]
                ];

        $json = json_encode($array);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/produtos/pedido/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        return json_decode($response, true);

    } 

        // busca o pedido através do Id do OMIE
    public function consultaOSErp(object $omie, int $idPedido)
    {
        $array = [
                    'app_key'=>$omie->appKey,
                    'app_secret'=>$omie->appSecret,
                    'call'=>'ConsultarOS',
                    'param'=>[
                            [
                                'nCodOS'=>$idPedido,
                            ]
                        ]
                ];

        $json = json_encode($array);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/servicos/os/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        return json_decode($response, true);

    } 

            // busca o contrato através do Id do OMIE
    public function consultaContrato(object $omie, int $idContrato)
    {

        $array = [
                    'app_key'=>$omie->appKey,
                    'app_secret'=>$omie->appSecret,
                    'call'=>'ConsultarContrato',
                    'param'=>[
                            [
                                'cNumCtr'=>$idContrato
                            ]
                        ]
                ];

        $json = json_encode($array);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/servicos/contrato/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);

    } 

    public function listarContratos( $omie)
    {

        $array = [
                    'app_key'=>$omie->app_key,
                    'app_secret'=>$omie->app_secret,
                    'call'=>'ListarContratos',
                    'param'=>[
                            [
                                "apenas_importado_api" => "S"
                            ]
                        ]
                ];

        $json = json_encode($array);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/servicos/contrato/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);

    }

    //consulta nota fiscal no omie
    public function consultaNotaErp(object $omie, int $idPedido)
    {
        // var_dump($idPedido);
        // print_r($omie);
        // exit;
        $array = [
            'app_key'=>$omie->appKey,
            'app_secret'=>$omie->appSecret,
            'call'=>'ConsultarNF',
            'param'=>[
                    [
                        'nIdPedido'=>$idPedido,
                    ]
                ]
            ];

        $json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/produtos/nfconsultar/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $data = json_decode($response, true);
        $nf = [];
        $nf['chave'] = $data['compl']['cChaveNFe'] ?? null;
        $nf['nNF'] = $data['ide']['nNF'];
        $nf['cnpjCpf'] = $data['nfDestInt']['cnpj_cpf'];
  
        return $nf;
    }

    //consulta nota fiscal no omie
    public function consultaNotaServico(object $omie, int $idPedido)
    {    
        $array = [
            'app_key'=>$omie->appKey,
            'app_secret'=>$omie->appSecret,
            'call'=>'ListarNFSEs',
            'param'=>[
                    [
                        'nCodigoOS'=>$idPedido,
                    ]
                ]
            ];

        $json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/servicos/nfse/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        return json_decode($response, true);

        // print_r($nfe);
        // exit;

        // return ($nfe['nfseEncontradas'][0]['Cabecalho']['cStatusNFSe'] === "F") ? $nfe['nfseEncontradas'][0]['Cabecalho']['nNumeroNFSe'] : false; 
        
    }

    
     //busca departamento pelo ID
    public function buscaDeptoByCode(object $omie, int $codDepto):array
    { 
        // print_r($contact);
        // exit;
        $array = [
            'app_key' => $omie->appKey ?? $this->appKey,
            'app_secret' => $omie->appSecret ?? $this->appSecret,
            'call' => 'ConsultarDepartamento',
            'param' => [
                [
                    'codigo'=>$codDepto
                    ]
                    ]
                ];
                
        $json = json_encode($array);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/departamentos/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);
        
        
    }

         //busca departamento pelo ID
    public function listDeptos(string $json, string $url):array|bool
    { 
        

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true) ?? false;
        
        
    }

    //busca cliente pelo ID
    public function getClientById( $contact)
    { 
        // print_r($contact);
        // exit;
        $jsonOmieIdCliente = [
            'app_key' => $omie->appKey ?? $this->appKey,
            'app_secret' => $omie->appSecret ?? $this->appSecret,
            'call' => 'ConsultarCliente',
            'param' => [
                [
                    'codigo_cliente_omie'=>$contact->codigoClienteOmie
                    ]
                    ]
                ];
                
        $jsonCnpj = json_encode($jsonOmieIdCliente);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonCnpj,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);
        
        
    }
    
    //busca cliente pelo ID
    public function getCaracteristicasClienteByid($contact)
    { 
        // print_r($contact);
        // exit;
        $jsonOmieIdCliente = [
            'app_key' => $omie->appKey ?? $this->appKey,
            'app_secret' => $omie->appSecret ?? $this->appSecret,
            'call' => 'ConsultarCaractCliente',
            'param' => [
                [
                    'codigo_cliente_omie'=>$contact->codigoClienteOmie
                    ]
                    ]
                ];
                
        $jsonCnpj = json_encode($jsonOmieIdCliente);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientescaract/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonCnpj,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
     
        $r = json_decode($response, true);
        
  
        return (!isset($r['faultstring'])) ? $r : false;
    }

    //busca cliente pelo ID
    public function getShipping($omie, $contact)
    {
        if($contact->cTransportadoraPadrao){
            $param = array(

                [
                    'codigo_cliente_omie'=>$contact->cTransportadoraPadrao
                ]
            );
               
        }else{
            $param = array(
                [
                    'codigo_cliente_integracao'=>"$contact->idTranspPadrao"
                ]
            );
        }

        
        $jsonOmieIdCliente = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ConsultarCliente',
            'param'=> $param
                ];
                
                $jsonCnpj = json_encode($jsonOmieIdCliente);
                
             
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonCnpj,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $shipping = json_decode($response, true);
        
        return $shipping['codigo_cliente_omie'] ?? null;
        
    }

    //Cria cliente no Omie ERP
    public function criaClienteERP(string $json)
    {
        // // print_r($omie);
        // // print 'estamos em omie services';
        // // print_r($contact);
        // // exit;
        // $array = [
        //     'app_key'=>$omie->appKey,
        //     'app_secret'=>$omie->appSecret,
        //     'call'=>'UpsertClienteCpfCnpj',
        //     'param'=>[]
        // ];
        
        // $clienteJson = [];
        // // $clienteJson['codigo_cliente_integracao'] = $contact->id;
        // $clienteJson['razao_social'] = htmlspecialchars_decode($contact->legalName) ?? null; 
        // $clienteJson['nome_fantasia'] = htmlspecialchars_decode($contact->name) ?? null;
        // $clienteJson['cnpj_cpf'] = $contact->cnpj ?? $contact->cpf;
        // $clienteJson['email'] = $contact->email ?? null;
        // $clienteJson['homepage'] = $contact->website ?? null;
        // $clienteJson['telefone1_ddd'] = $contact->ddd1 ?? null;
        // $clienteJson['telefone1_numero'] = $contact->phone1 ?? null;
        // $clienteJson['telefone2_ddd'] = $contact->ddd2 ?? null;
        // $clienteJson['telefone2_numero'] = $contact->phone2 ?? null;
        // $clienteJson['contato'] = $contact->contato1 ?? null;
        // $clienteJson['endereco'] = $contact->streetAddress;
        // $clienteJson['endereco_numero'] = $contact->streetAddressNumber;
        // $clienteJson['bairro'] = $contact->neighborhood;
        // $clienteJson['complemento'] = $contact->streetAddressLine2 ?? null;
        // $clienteJson['estado'] = $contact->stateShort;//usar null para teste precisa pegar o codigo da sigla do estado na api omie
        // //$clienteJson['cidade'] = $contact->cityName;
        // $clienteJson['cidade_ibge'] = $contact->cityId;
        // // $clienteJson['cep'] = $contact->streetAdress ?? null;
        // $clienteJson['cep'] = $contact->zipCode;
        // $clienteJson['documento_exterior'] = $contact->documentoExterior ?? null;
        // $clienteJson['inativo'] = $contact->inativo ?? null;
        // $clienteJson['bloquear_exclusao'] = $contact->bloquearExclusao ?? null;
        // //inicio aba CNAE e Outros
        // $clienteJson['cnae'] = $contact->cnaeCode ?? null;
        // $clienteJson['inscricao_estadual'] = $contact->inscricaoEstadual ?? null;
        // $clienteJson['inscricao_municipal'] = $contact->inscricaoMunicipal ?? null;
        // $clienteJson['inscricao_suframa'] = $contact->inscricaoSuframa ?? null;
        // $clienteJson['optante_simples_nacional'] = $contact->simplesNacional ?? null;
        // $clienteJson['produtor_rural'] = $contact->produtorRural ?? null;
        // $clienteJson['contribuinte'] = $contact->contribuinte ?? null;
        // $clienteJson['tipo_atividade'] = $contact->ramoAtividade ?? null;
       
        // $clienteJson['valor_limite_credito'] = $contact->limiteCredito ?? null;
        // $clienteJson['observacao'] = $contact->observacao ?? null;
        // //fim aba CNAE e Outros
        // //inicio array dados bancários
        // $clienteJson['dadosBancarios'] =[];
        // $dadosBancarios =[];
        // $dadosBancarios['codigo_banco'] = $contact->cBanco ?? null;
        // $dadosBancarios['agencia'] = $contact->agencia ?? null;
        // $dadosBancarios['conta_corrente'] = $contact->nContaCorrente ?? null;
        // $dadosBancarios['doc_titular'] = $contact->docTitular ?? null;
        // $dadosBancarios['nome_titular'] = $contact->nomeTitular ?? null;
        // $dadosBancarios['transf_padrao'] = $contact->transferenciaPadrao ?? null;
        // $dadosBancarios['cChavePix'] = $contact->chavePix ?? null;
        // $clienteJson['dadosBancarios'][]=$dadosBancarios;
        // //fim array dados bancários
        // //inicio array recoja mendações
        // $clienteJson['recomendacoes'] =[];
        // $recomendacoes=[];//vendedor padrão
        // $recomendacoes['codigo_vendedor'] = $contact->cVendedorOmie ?? null;
        // $recomendacoes['codigo_transportadora'] = $contact->idTransportadora ?? null;
        // $clienteJson['recomendacoes'][] = $recomendacoes;
        // //fim array recomendações

        // // $caracteristicas = [];
        // // //$caracteristicasCampo=[];
        // // //$caracteristicasConteudo=[];
        // // $caracteristicasCampo = 'Regiao';
        // // $caracteristicasConteudo = $contact->regiao;
        // // $caracteristicas['campo'] = $caracteristicasCampo;
        // // $caracteristicas['conteudo']=$caracteristicasConteudo;
        // //$clienteJson['caracteristicas'] = $caracteristicas;
        // //$clienteJson['tags']=[];
        // $clienteJson['tags']=$contact->tags;
         
        // $array['param'][] = $clienteJson;

        // $json = json_encode($array);     

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $cliente = json_decode($response, true);

        return $cliente;

    }

    // busca o pedido através do Id do OMIE
    public function buscaIdProjetoOmie(object $omie, string $projetoName)
    {
        $array = [
                    'app_key'=>$omie->appKey,
                    'app_secret'=>$omie->appSecret,
                    'call'=>'ListarProjetos',
                    'param'=>[
                            [
                                'apenas_importado_api'=> 'N',
                                'nome_projeto'=> $projetoName
                            ]
                        ]
                ];

        $json = json_encode($array);
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/projetos/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $projeto = json_decode($response, true);

        return ($projeto['registros'] <= 0 || $projeto['cadastro'][0]['inativo'] === 'S') ? false : $projeto['cadastro'][0]['codigo'];



    }

    //altera um cliente no OMIE com base nas difereças dos arrays Old e News do Ploomes
    public function alteraCliente(object $omie, array $diff)
    {   
        $array = [
            'app_key'=>$omie->appKey,
            'app_secret'=>$omie->appSecret,
            'call'=>'AlterarCliente',
            'param'=>[]
        ];
    
        $clienteJson = [];

        ($diff['idOmie'] === null)?$clienteJson['codigo_cliente_integracao'] = $diff['idIntegracao']:$clienteJson['codigo_cliente_omie'] = $diff['idOmie'];   
        
        $clienteJson['razao_social'] = htmlspecialchars_decode($diff['legalName']['new']) ?? null; 
        $clienteJson['nome_fantasia'] = htmlspecialchars_decode($diff['name']['new']) ?? null;
        $clienteJson['cnpj_cpf'] = $diff['cnpj']['new'] ?? $diff['cpf']['new'] ?? null;
        $clienteJson['email'] = $diff['email']['new'] ?? null;
        $clienteJson['homepage'] = $diff['website']['new'] ?? null;
        $clienteJson['telefone1_ddd'] = $diff['ddd1']['new'] ?? null;
        $clienteJson['telefone1_numero'] = $diff['phone1']['new'] ?? null;
        $clienteJson['telefone2_ddd'] = $diff['ddd2']['new'] ?? null;
        $clienteJson['telefone2_numero'] = $diff['phone2']['new'] ?? null;
        $clienteJson['contato'] = $diff['contato1']['new'] ?? null;
        $clienteJson['endereco'] = $diff['streetAddress']['new'] ?? null;
        $clienteJson['endereco_numero'] = $diff['streetAddressNumber']['new'] ?? null;
        $clienteJson['bairro'] = $diff['neighborhood']['new'] ?? null;
        $clienteJson['complemento'] = $diff['streetAddressLine2']['new'] ?? null;
        $clienteJson['estado'] = $diff['stateShort']['new'] ?? null;//usar null para teste precisa pegar o codigo da sigla do estado na api omie
        //$clienteJson['cidade'] = $diff['cityName']['new'];
        $clienteJson['cidade_ibge'] = $diff['cityId']['new'] ?? null;
        // $clienteJson['cep'] = $diff['streetAdress']['new'] ?? null;
        $clienteJson['cep'] = $diff['zipCode']['new'] ?? null;
        $clienteJson['documento_exterior'] = $diff['documentoExterior']['new'] ?? null;
        $clienteJson['inativo'] = $diff['inativo']['new'] ?? null;
        $clienteJson['bloquear_exclusao'] = $diff['bloquearExclusao']['new'] ?? null;
        //inicio aba CNAE e Outros
        $clienteJson['cnae'] = $diff['cnaeCode']['new'] ?? null;//3091102 ?? null;
        $clienteJson['inscricao_estadual'] = $diff['inscricaoEstadual']['new'] ?? null;
        $clienteJson['inscricao_municipal'] = $diff['inscricaoMunicipal']['new'] ?? null;
        $clienteJson['inscricao_suframa'] = $diff['inscricaoSuframa']['new'] ?? null;
        $clienteJson['optante_simples_nacional'] = $diff['simplesNacional']['new'] ?? null;
        $clienteJson['produtor_rural'] = $diff['produtorRural']['new'] ?? null;
        $clienteJson['contribuinte'] = $diff['contribuinte']['new'] ?? null;
        $clienteJson['tipo_atividade'] = $diff['ramoAtividade']['new'] ?? null;
        $clienteJson['valor_limite_credito'] = $diff['limiteCredito']['new'] ?? null;
        $clienteJson['observacao'] = $diff['observacao']['new'] ?? null;
        //fim aba CNAE e Outros
        //inicio array dados bancários
        $clienteJson['dadosBancarios'] =[];
        $dadosBancarios =[];
        $dadosBancarios['codigo_banco'] = $diff['cBanco']['new'] ?? null;
        $dadosBancarios['agencia'] = $diff['agencia']['new'] ?? null;
        $dadosBancarios['conta_corrente'] = $diff['nContaCorrente']['new'] ?? null;
        $dadosBancarios['doc_titular'] = $diff['docTitular']['new'] ?? null;
        $dadosBancarios['nome_titular'] = $diff['nomeTitular']['new'] ?? null;
        $dadosBancarios['transf_padrao'] = $diff['transferenciaPadrao']['new'] ?? null;
        $dadosBancarios['cChavePix'] = $diff['chavePix']['new'] ?? null;
        $clienteJson['dadosBancarios'][] =array_filter($dadosBancarios); 
        //fim array dados bancários
        //inicio array recoja mendações
        $clienteJson['recomendacoes'] = [];
        $recomendacoes = [];//vendedor padrão

        $recomendacoes['codigo_vendedor'] = $diff['cVendedorOmie'] ?? null;
        $recomendacoes['codigo_transportadora']= null;//6967396742;// $diff['ownerId']['new'] ?? null;
        $clienteJson['recomendacoes'][] = array_filter($recomendacoes);
        
        //fim array recomendações
        $tags =[];
        $tags[]['tag'] = $diff['tags.0']['new'] ?? null;
        $clienteJson['tags']=$tags;
    
            
        $array['param'][] = array_filter($clienteJson);

        
        $json = json_encode($array);
        // print_r($json);
   
            print_r($array);
        // print_r($diff);

            exit;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $cliente = json_decode($response, true);

        return $cliente;

    }

    //altera um cliente no OMIE com base nas difereças dos arrays Old e News do Ploomes
    public function alteraClienteCRMToERP($json)
    {   
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        $cliente = json_decode($response, true);

        return $cliente;

    }
    //Exclui um cliente no OMIE
    public function deleteClienteOmie(object $omie, object $contact)
    {  
        if(!isset($contact->idOmie)){

            $json = [
                'app_key' => $omie->appKey,
                'app_secret' => $omie->appSecret,
                'call' => 'ExcluirCliente',
                'param' => [
                    [
                        'codigo_cliente_integracao'=>$contact->id
                    ]
                ]
                    ];

        } else{

            $json = [
                'app_key' => $omie->appKey,
                'app_secret' => $omie->appSecret,
                'call' => 'ExcluirCliente',
                'param' => [
                    [
                        'codigo_cliente_omie'=>$contact->idOmie
                    ]
                ]
                    ];
        }

        $jsonDelete = json_encode($json);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/clientes/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonDelete,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $cliente = json_decode($response, true);
    
        return $cliente;

    }

    //consulta estoque 
    public function getStockById(string $json){

            $curl = curl_init();
    
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://app.omie.com.br/api/v1/estoque/consulta/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));
    
            $response = curl_exec($curl);
    
            curl_close($curl);
    
            $estoque = json_decode($response, true);
        
            return $estoque;

    }

        //consulta estoque 
    public function getStock(string $json){

            $curl = curl_init();
    
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://app.omie.com.br/api/v1/estoque/resumo/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));
    
            $response = curl_exec($curl);
    
            curl_close($curl);
    
            $estoque = json_decode($response, true);
        
            return $estoque;

    }

    public function getStockLocation(string $json){

            $curl = curl_init();
    
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://app.omie.com.br/api/v1/estoque/local/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));
    
            $response = curl_exec($curl);
    
            curl_close($curl);
    
            $estoque = json_decode($response, true);
        
            return $estoque;

    }

    //seta o id de integração como Id Ploomes
    public function setProductIntegrationCodeAction($product)
    {
        $array = [
            'app_key' => $product->baseFaturamento['appKey'],
            'app_secret' => $product->baseFaturamento['appSecret'],
            'call' => 'AssociarCodIntProduto',
            'param' => [
                [
                    'codigo_produto' => $product->baseFaturamento['idOmie'],
                    'codigo_produto_integracao' => $product->idPloomes
                ]
            ]
        ];

        $json = json_encode($array);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/produtos/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $infoIntegracao = json_decode($response, true);

        return $infoIntegracao;

    }

    // Busca uma familia de produtos pelo id
    public function getFamiliaById(object $product)
    {

        $array = [
            'app_key' => $product->appKey,
            'app_secret' => $product->appSecret,
            'call' => 'ConsultarFamilia',
            'param' => [
                [
                    'codigo' => $product->codigo_familia
                ]
            ]
        ];

        $json = json_encode($array);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/familias/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $familia = json_decode($response, true);

        return $familia['nomeFamilia'];

    }

    public function getTipoATividade(object $omie,  $id = null, $name = null): array
    {
        $data = $id ?? $name;
        $filter = (isset($id)) ? 'filtrar_por_codigo' : 'filtrar_por_descricao';

        $array = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ListarTipoAtiv',
            'param' => [
                [
                    $filter => $data,
                ]
            ]
        ];

        $json = json_encode($array);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.omie.com.br/api/v1/geral/tpativ/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $tipoAtiv = json_decode($response, true);
    
        // print_r($tipoAtiv['lista_tipos_atividade'][0]);
        // exit;
        return $tipoAtiv['lista_tipos_atividade'][0];

    }

    public function getFinaceiro($json, $url){

        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $res = json_decode($response, true);

        // print_r($res);
        // exit;

        return $res['titulosEncontrados'];


    }

    public function getProductStructure(string $json, string $url): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = json_decode(curl_exec($curl), true);

        curl_close($curl);

        return $response;

    }

    public function getProductById(string $json, string $url): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = json_decode(curl_exec($curl), true);

        curl_close($curl);

        return $response;

    }

    
}