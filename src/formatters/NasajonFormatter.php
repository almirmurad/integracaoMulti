<?php

namespace src\formatters;

use DateTime;
use DateTimeZone;
use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\functions\CustomFieldsFunction;
use src\services\NasajonServices;
use src\services\PloomesServices;
use stdClass;

Class NasajonFormatter implements ErpFormattersInterface{

    private NasajonServices $nasajonServices;
    public mixed $current;    
    public mixed $currentDateTime;

    public function __construct($erpBases)
    {
        $date =  new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $this->nasajonServices = new NasajonServices($erpBases);

        $this->current = date('d/m/Y H:i:s');

        $this->currentDateTime = $date->format("Y-m-d\TH:i:s\Z");


    }

    public function createOrder(object $orderData, object $credentials): string
    {
        return 'aqui criamos a estrutura Json do pedido nasajon';
    }

    public function createObjectCrmContactFromErpData(array $clientData): object
    {
        $obj = [];
        $o = (object) $obj;
        return $o;
    }

    public function createPloomesContactFromErpObject(object $contact, PloomesServices $ploomesServices): string
    {
        return '';
    }

    public function createObjectErpClientFromCrmData(array $contactData, PloomesServices $ploomesServices): object
    {
        $decoded = $contactData['body'];
        
        ($decoded['New']['TypeId'] === 2 )? $dataContact = $ploomesServices->getClientById($decoded['New']['CompanyId']):$dataContact = $ploomesServices->getClientById($decoded['New']['Id']);

        $custom = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($dataContact['OtherProperties'],'Cliente',$contactData['Tenancy']['tenancies']['id']);

        $contact = new stdClass();
        $bases =[];
        $contact->codErp = [];
        foreach($contactData['Tenancy']['erp_bases'] as $base)
        {   
            $name = strtolower($base['app_name']);
            $chaveId = "bicorp_api_id_cliente_erp_{$name}_out";
            
            $contact->codErp[$name] = $custom[$chaveId] ?? null;           
            
            $chave = "bicorp_api_integrar_base_{$name}_out";
            
            if(isset($custom[$chave]) && $custom[$chave] !== false){
                $base['sendExternalKey'] = $chave;
                $base['sendExternalKeyIdErp'] = $chaveId;
                $base['integrar'] = 1;
                
            }
            
            $bases[] =$base; 
        }

        $contact->basesFaturamento = $bases; 

        $contact->id = $dataContact['Register'];//required
        $contact->codigo = 123456;//required
        $contact->qualificacao = ($dataContact['CPF'] === null)?"juridica":"fisica";//required
        $contact->nome = $dataContact['LegalName'];//required
        $contact->nome_fantasia = $dataContact['Name'] ?? null;
        $contact->inativo = false ?? null;
        $contact->situacao_financeiro = "nenhum" ?? null;
        $contact->indicador_inscricao_estadual = "contribuinte_icms" ?? null;
        $contact->inscricao_estadual = $custom['bicorp_api_inscricao_estadual_out'] ?? null;
        $contact->contribuinte_icms = true  ?? null;
        $contact->email = $dataContact['Email'] ?? null;
        $contact->observacao = $dataContact['Note'] ?? null;
        // $contact->grupo_empresarial = 'Gamatermic';//required
        // $contact->tenant = 123;//required
        

        $contatos = [];
        if(isset($dataContact['Contacts']) && !empty($dataContact['Contacts']))
        {
            foreach($dataContact['Contacts'] as $contato){
                $emails = [];
                $telefones = [];
                $cont['nome'] = $contato['Name'];
                $cont['nascimento'] = (isset($contato['Birthday']) && $contato['Birthday'] != null) ? date('Y-m-d',strtotime($contato['Birthday'])) : null;
                //$cont['cargo'] = $contato['Birthday']??null;
                $cont['sexo'] = 'masculino';
                $cont['observacao'] = $contato['Note']??null;
                $completeName = $contato['Name'];
                $xName = explode(' ',$completeName);
                $firstName = $xName[0] ?? null;
                $secondName = $xName[1] ?? null;
                $cont['primeiro_nome'] = $firstName ?? null;
                $cont['sobre_nome'] = $secondName;
                $cont['cpf'] = $contato['Register']??null;
                $cont['reponsavel_legal'] = true;
                $cont['principal'] = true;
                $cont['titulo'] = 'Contato';
                $cont['decisor'] = true;
                $cont['influenciador'] = true;
              

                $email['email'] = $contato['Email']??null;
                $email['principal'] = false;
                
                $emails[] = $email;
                $cont['emails'] = $emails;
                foreach($contato['Phones'] as $phone){
                    $phoneNumber = $phone['PhoneNumber'];
                    $xPhone = explode(' ', $phoneNumber);
                    $ddd = str_replace(['(',')'],'',$xPhone[0]);

                    $telefone['ddd'] = $ddd;

                    $telefone['telefone'] = $xPhone[1];
                    $telefone['descricao'] = $firstName . ' (' .$phone['Type']['Name'] . ')' ?? null;
                    $telefone['ramal'] = 123;
                    $telefone['ddi'] = 123;
                    $telefone['tipo'] = strtolower($phone['Type']['Name']) ?? 'comercial';
                    $telefone['principal'] = false;
                   
                    $telefones[] = $telefone;
                }
                
                $cont['telefones'] = $telefones;
                $contatos[] = $cont;
            }            
        }else{

            $contatos['nome'] = $custom['bicorp_api_contato_out'];
            $contatos['nascimento']= (isset($contato['Birthday']) && $contato['Birthday'] != null) ? date('Y-m-d',strtotime($contato['Birthday'])) : null;
            $contatos['cargo'] = null;
            $contatos['sexo'] = 'masculino';
            $contatos['observacao'] = null;
            $completeName = $custom['bicorp_api_contato_out'];
            $xName = explode(' ',$completeName);
            $firstName = $xName[0] ?? null;
            $secondName = $xName[1] ?? null;
            $contatos['primeiro_nome'] = $firstName;
            $contatos['sobre_nome'] = $secondName;
            $contatos['cpf'] = null;
            $contatos['reponsavel_legal'] = true;
            $contatos['principal'] = true;
            $contatos['titulo'] = 'Contato';
            $contatos['decisor'] = true;
            $contatos['influenciador'] = true;

        }
        $contact->contatos = $contatos;

        $enderecos=[];
        $enderecos[0]['tipo_logradouro'] = "aer";
        $enderecos[0]['logradouro'] = $dataContact['StreetAddress'];
        $enderecos[0]['numero'] = $dataContact['StreetAddressNumber'];
        $enderecos[0]['complemento'] = $dataContact['StreetAddressLine2'];
        $enderecos[0]['cep'] = $dataContact['ZipCode'];
        $enderecos[0]['bairro'] = $dataContact['Neighborhood'];
        $enderecos[0]['tipo'] = "local";
        $enderecos[0]['uf_exterior'] = null;
        $enderecos[0]['padrao'] = false;
        $enderecos[0]['uf'] = $dataContact['State']['Short'];
        $enderecos[0]['pais'] = "1058";
        $enderecos[0]['ibge'] = $dataContact['City']['IBGECode'];
        $enderecos[0]['cidade'] = $dataContact['City']['Name'];

        $contact->enderecos = $enderecos;

        return $contact;
    }

    public function updateContactCRMToERP(object $contact, PloomesServices $ploomesServices, object $tenant): array
    {
        
        $json = $this->createJsonClienteCRMToERP($contact, $tenant);

        return $this->nasajonServices->editaClienteERP($json, $contact->id);

    }

    public function createContactCRMToERP(object $contact, PloomesServices $ploomesServices, object $tenant): array
    {
        $json = $this->createJsonClienteCRMToERP($contact, $tenant);

        return $this->nasajonServices->criaClienteERP($json);
    }

    public function createContactERP(string $json, PloomesServices $ploomesServices): array
    {
        return[];
    }

    public function updateContactERP(string $json, object $contact, PloomesServices $ploomesServices): array
    {
        return [];
    }

    public function createOrderErp(string $jsonPedido, array $arrayIsServices): array
    {
        $createOrder = $this->nasajonServices->criaPedidoErp($jsonPedido);

        if(isset($createOrder['codigo_status']) && $createOrder['codigo_status'] == "0")
        {
            $createOrder['create'] = true;
            $createOrder['num_pedido'] = $createOrder['numero_pedido'];           
        }else{
            $createOrder['create'] = false;
        }

        return $createOrder;
        
    }

    public function getIdVendedorERP(object $erp, string $mailVendedor):string
    {
       
        return 'idVendedor';
    }

    public function createJsonClienteCRMToERP(object $contact, object $tenant):string
    {
        
        $contact->codigo = $contact->codErp[strtolower($tenant->tenant)];
        $contact->grupo_empresarial = $tenant->client_secret;
        $contact->tenant = 12556;
        unset($contact->basesFaturamento);
        unset($contact->codErp);
        $json = json_encode($contact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $json;       

    }

    public function createPersonArrays(object $contact)
    {
        
    }


    //order
    public function distinctProductsServicesFromOmieOrders(array $orderArray, bool $isService, string $idItemNasajon, object $order):array
    {
        $productsOrder = [];
        $contentServices = [];
          //separa e monta os arrays de produtos e serviços
        $opItem = [];       
        
        foreach($orderArray['Products'] as $prdItem)
        {   
            foreach($prdItem['Product']['OtherProperties'] as $otherp){
                $opItem[$otherp['FieldKey']] = $otherp['ObjectValueName'] ?? 
                $otherp['BigStringValue'] ?? $otherp['StringValue'] ??  $otherp['IntegerValue'] ?? $otherp['DateTimeValue'];
            } 
 
            if(!array_key_exists($idItemNasajon, $opItem )){
                throw new WebhookReadErrorException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado o id do produto  Omie para o aplicativo de faturamento escolhido no pedido.', 500);
            }

            //verifica se é venda de serviço 
            if($isService){
               //retorna o modelo de serviço para o erp de destino 
               $contentServices[] = $this->getOrdersServicesItens($prdItem, $opItem[$idItemNasajon], $order);
                
            }else{
                               
                $productsOrder[] = $this->getOrdersProductsItens($prdItem, $opItem[$idItemNasajon], $order);
               
            }
        }

        return ['products'=>$productsOrder, 'services'=>$contentServices];
    }

    public function getOrdersServicesItens(array $prdItem, int $idItemNasajon, object $order):array
    {
        $serviceOrder = [];
        $pu = [];
        $service = [];
        $produtosUtilizados = [];

        //verifica se tem serviço com produto junto
        if($prdItem['Product']['Group']['Name'] !== 'Serviços'){
                     
            //monts o produtos utilizados (pu)
            $pu['nCodProdutoPU'] = $idItemNasajon;
            $pu['nQtdePU'] = $prdItem['Quantity'];
            
            $produtosUtilizados[] = $pu;
            
        }else{
            
            //monta o serviço
            $service['nCodServico'] = $idItemNasajon;
            $service['nQtde'] = $prdItem['Quantity'];
            $service['nValUnit'] = $prdItem['UnitPrice'];
            $service['cDescServ'] = $order->descricaoServico;
            
            $serviceOrder[] = $service;
        }

        $contentServices['servicos'] = $serviceOrder;
        $contentServices['produtosServicos'] = $produtosUtilizados;

        return $contentServices;

    }

    public function getOrdersProductsItens(array $prdItem, int $idItemNasajon, object $order):array
    {
        $det = [];  
        $det['ide'] = [];
        $det['produto'] = [];

        $det['ide']['codigo_item_integracao'] = $prdItem['Id'];
        $det['produto']['quantidade'] = $prdItem['Quantity'];
        $det['produto']['valor_unitario'] = $prdItem['UnitPrice'];
        $det['produto']['codigo_produto'] = $idItemNasajon;

        $det['inf_adic'] = [];
        $det['inf_adic']['numero_pedido_compra'] = $order->numPedidoCompra;
        $det['inf_adic']['item_pedido_compra'] =$prdItem['Ordination']+1;

        return $productsOrder[] = $det;

    }

    public function detectLoop(array $args): bool
    {
        return true;
    }

    public function createInvoiceObject(array $args): object
    {
        $object = new stdClass();
        return $object;
    }

}