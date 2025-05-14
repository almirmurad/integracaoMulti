<?php

namespace src\formatters;

use src\contracts\ErpFormattersInterface;
use src\functions\CustomFieldsFunction;
use src\services\NasajonServices;
use src\services\PloomesServices;
use stdClass;

Class NasajonFormatter implements ErpFormattersInterface{

    private object $nasajonServices;
    public mixed $current;    

    public function __construct($erpBases)
    {
        $this->nasajonServices = new NasajonServices($erpBases);

        $this->current = date('d/m/Y H:i:s');
    }

    public function createOrder(object $orderData, object $credentials): string
    {
        return '';
    }

    public function createObjectCrmContactFromErpData(array $clientData): object
    {
        $obj = [];
        $o = (object) $obj;
        return $o;
    }

    public function createPloomesContactFromErpObject(object $contact, object $ploomesServices): string
    {
        return '';
    }

    public function createObjectErpClientFromCrmData(array $contactData, PloomesServices $ploomesServices): object
    {

        $decoded = $contactData['body'];
        $custom = CustomFieldsFunction::compareCustomFields($decoded['New']['OtherProperties'],$contactData['Tenancy']['tenancies']['id'],'Cliente');

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
        $contact->qualificacao = ($decoded['New']['CPF'] === null)?"juridica":"fisica";//required
        $contact->nome = $decoded['New']['LegalName'];//required
        $contact->nome_fantasia = $decoded['New']['Name'] ?? null;
        $contact->inativo = false ?? null;
        $contact->situacao_financeiro = "nenhum" ?? null;
        $contact->indicador_inscricao_estadual = "contribuinte_icms" ?? null;
        $contact->inscricao_estadual = $custom['bicorp_api_inscricao_estadual_out'] ?? null;
        $contact->contribuinte_icms = true  ?? null;
        $contact->email = $decoded['New']['Email'] ?? null;
        $contact->observacao = $decoded['New']['Note'] ?? null;
        $contact->grupo_empresarial = 'Gamatermic';//required
        $contact->tenant = 123;//required
        $dataContact = $ploomesServices->getClientById($decoded['New']['Id']);

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

    public function updateContactCRMToERP(object $contact, object $ploomesServices, object $tenant): array
    {
        $json = $this->createJsonClienteCRMToERP($contact, $tenant);

        $criaClienteERP = $this->nasajonServices->criaClienteERP($json);

        print_r($json);
        exit;
        


        print_r($contact);
                exit;
        return [];
    }

    public function createContactCRMToERP(object $contact, object $ploomesServices, object $tenant): array
    {
        
        print'aqui create';

        print_r($tenant);
        print_r($contact);
                exit;
        return [];
    }

    public function createContactERP(string $json, object $ploomesServices): array
    {
        return[];
    }

    public function updateContactERP(string $json, object $contact, object $ploomesServices): array
    {
        return [];
    }

    public function createOrderErp(string $jsonPedido): array
    {
        return [];
    }

    public function getIdVendedorERP(object $omie, string $mailVendedor): ?string
    {
        return '';
    }

    public function createJsonClienteCRMToERP(object $contact, object $tenant):string
    {
        $contact->id = $contact->codErp[strtolower($tenant->tenant)];
        $contact->codigo = $contact->codErp[strtolower($tenant->tenant)];
        unset($contact->basesFaturamento);
        unset($contact->codErp);
        unset($contact->totalTenanties);
        $json = json_encode($contact);

        return $json;       

    }

}