<?php

namespace src\formatters;

use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\functions\CustomFieldsFunction;
use src\services\NasajonServices;
use src\services\PloomesServices;
use stdClass;

Class NasajonFormatter implements ErpFormattersInterface{

    private NasajonServices $nasajonServices;
    public mixed $current;    

    public function __construct($erpBases)
    {
        $this->nasajonServices = new NasajonServices($erpBases);

        $this->current = date('d/m/Y H:i:s');
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

        $contact->id = 123;//required
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
        $contact->grupo_empresarial = 'Gamatermic';//required
        $contact->tenant = 123;//required
        

        $contatos = [];
        if(isset($dataContact['Contacts']) && !empty($dataContact['Contacts']))
        {
            foreach($dataContact['Contacts'] as $contato){
                $emails = [];
                $telefones = [];
              
                $cont['nome'] = $contato['Name'];
                $cont['nascimento']= $contato['Birthday']??null;
                $cont['cargo'] = $contato['Birthday']??null;
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
                $cont['atualizado_em'] = "2019-08-24T14:15:22Z";

                $email['email'] = $contato['Email']??null;
                $email['principal'] = false;
                $email['atualizado_em'] = "2019-08-24T14:15:22Z";
                $emails[] = $email;
                $cont['emails'] = $emails;
                foreach($contato['Phones'] as $phone){
                    $phoneNumber = $phone['PhoneNumber'];
                    $xPhone = explode(' ', $phoneNumber);

                    $telefone['ddd'] = $xPhone[0];
                    $telefone['telefone'] = $xPhone[1];
                    $telefone['descricao'] = $firstName . ' (' .$phone['Type']['Name'] . ')' ?? null;
                    $telefone['ramal'] = 123;
                    $telefone['ddi'] = 123;
                    $telefone['tipo'] = strtolower($phone['Type']['Name']) ?? 'comercial';
                    $telefone['principal'] = false;
                    $telefone['atualizado_em'] = "2019-08-24T14:15:22Z";

                    $telefones[] = $telefone;
                }
                
                $cont['telefones'] = $telefones;
                $contatos[] = $cont;
            }            
        }else{

            $contatos['nome'] = $custom['bicorp_api_contato_out'];
            $contatos['nascimento']= null;
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
            $contatos['atualizado_em'] = "2019-08-24T14:15:22Z";

        }
        $contact->contatos = $contatos;

        $enderecos=[];
        $enderecos[0]['tipo_logradouro'] = "aer";
        $enderecos[0]['logradouro'] = "rua xpto";
        $enderecos[0]['complemento'] = "41b";
        $enderecos[0]['cep'] = "41b";
        $enderecos[0]['bairro'] = "41b";
        $enderecos[0]['tipo'] = "41b";
        $enderecos[0]['uf_exterior'] = "41b";
        $enderecos[0]['padrao'] = false;
        $enderecos[0]['uf'] = "41b";
        $enderecos[0]['pais'] = "41b";
        $enderecos[0]['ibge'] = "41b";
        $enderecos[0]['cidade'] = "41b";
        $enderecos[0]['atualizado_em'] = "2019-08-24T14:15:22Z";
        $contact->enderecos = $enderecos;

        return $contact;
    }

    public function updateContactCRMToERP(object $contact, PloomesServices $ploomesServices, object $tenant): array
    {
        $json = $this->createJsonClienteCRMToERP($contact, $tenant);

        $updateClienteERP = $this->nasajonServices->criaClienteERP($json);

        if(isset($updateClienteERP['code']) && $updateClienteERP['code'] != 200)
        {
            throw new WebhookReadErrorException('Erro ao alterar o cliente no ERP: '.$updateClienteERP['message'], $updateClienteERP['code']);
        }

        print_r($updateClienteERP);
        exit;
        


        print_r($contact);
                exit;
        return [];
    }

    public function createContactCRMToERP(object $contact, PloomesServices $ploomesServices, object $tenant): array
    {
        $json = $this->createJsonClienteCRMToERP($contact, $tenant);

        print_r($json);
        exit;
        
        return [];
    }

    public function createContactERP(string $json, PloomesServices $ploomesServices): array
    {
        return[];
    }

    public function updateContactERP(string $json, object $contact, PloomesServices $ploomesServices): array
    {
        return [];
    }

    public function createOrderErp(string $jsonPedido): array
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
        $contact->id = $contact->codErp[strtolower($tenant->tenant)];
        $contact->codigo = $contact->codErp[strtolower($tenant->tenant)];
        unset($contact->basesFaturamento);
        unset($contact->codErp);
        unset($contact->totalTenanties);
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

}