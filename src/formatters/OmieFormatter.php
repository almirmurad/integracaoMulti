<?php

namespace src\formatters;

use GrahamCampbell\ResultType\Success;
use src\contracts\ErpFormattersInterface;
use src\exceptions\PedidoInexistenteException;
use src\exceptions\WebhookReadErrorException;
use src\functions\CustomFieldsFunction;
use src\functions\DiverseFunctions;
use src\services\OmieServices;
use stdClass;

Class OmieFormatter implements ErpFormattersInterface{

    private object $omieServices;
    public mixed $current;    

    public function __construct($appk, $omieBases)
    {
        $this->omieServices = new OmieServices($appk, $omieBases);
        $this->current = date('d/m/Y H:i:s');
    }

    public function detectLoop(array $args){
        
        if($args['body']['author']['name'] === 'Integração' || $args['body']['author']['email'] === 'no-reply@omie.com.br' ){
            throw new WebhookReadErrorException('Dados de retorno da última integração', 500);
        }

        return true;
    }

    //order
    public function distinctProductsServicesFromOmieOrders(array $orderArray, bool $isService, string $idItemOmie, object $order):array
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
            
            if(!array_key_exists($idItemOmie, $opItem )){
                throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado o id do produto  Omie para o aplicativo de faturamento escolhido no pedido.', 500);
            }

            //verifica se é venda de serviço 
            if($isService){
               //retorna o modelo de serviço para o erp de destino 
               $contentServices[] = $this->getOrdersServicesItens($prdItem, $opItem[$idItemOmie], $order);
                
            }else{
                

                $productsOrder[] = $this->getOrdersProductsItens($prdItem, $opItem[$idItemOmie], $order);
               
            }
        }

        return ['products'=>$productsOrder, 'services'=>$contentServices];
    }

    public function createOrder(object $order, object $omie):string
    {        
        
        //separa e monta os arrays de produtos e serviços
        // cabecalho
        $cabecalho = [];//cabeçalho do pedido (array)
        $cabecalho['codigo_cliente'] = $order->idClienteOmie;//int
        $cabecalho['codigo_pedido_integracao'] = 'VEN_PRD/'.$order->id;//string
        $cabecalho['data_previsao'] = DiverseFunctions::convertDate($order->previsaoFaturamento);//string
        $cabecalho['etapa'] = '10';//string
        $cabecalho['numero_pedido'] = $order->id;//string
        $cabecalho['codigo_parcela'] = $order->idParcelamento ?? '000';//string'qtde_parcela'=>2
        $cabecalho['origem_pedido'] = 'API';//string
    
        //frete
        $frete = [];//array com infos do frete, por exemplo, modailidade;
        $frete['modalidade'] = $order->modalidadeFrete ?? null;//string
    
        //informações adicionais
        $informacoes_adicionais = []; //informações adicionais por exemplo codigo_categoria = 1.01.03, codigo_conta_corrente = 123456789
        $informacoes_adicionais['codigo_categoria'] = '1.01.03';//string
        $informacoes_adicionais['codigo_conta_corrente'] = $omie->ncc;//int
        $informacoes_adicionais['numero_pedido_cliente']= $order->numPedidoCliente ?? "0";
        $informacoes_adicionais['codVend']= $order->codVendedorErp ?? null;
        $informacoes_adicionais['codproj']= $order->codProjeto ?? null;
        $informacoes_adicionais['dados_adicionais_nf'] = $order->notes;
    
        //observbacoes
        $observacoes =[];
        $observacoes['obs_venda'] = $order->notes;
    
        $newPedido = [];//array que engloba tudo
        $newPedido['cabecalho'] = $cabecalho;
        $newPedido['det'] = $order->contentOrder;
        $newPedido['frete'] = $frete;
        $newPedido['informacoes_adicionais'] = $informacoes_adicionais;
        //$newPedido['lista_parcelas'] = $lista_parcelas;
        $newPedido['observacoes'] = $observacoes;
    
        if(
            !empty($newPedido['cabecalho']) || !empty($newPedido['det']) ||
            !empty($newPedido['frete']) || !empty($newPedido['informacoes_adicionais']) ||
            !empty($newPedido['observacoes'])
        )
        {
            $array = [
                'app_key' =>   $omie->appKey,
                'app_secret' => $omie->appSecret,
                'call' => 'IncluirPedido',
                'param'=> [$newPedido],
            ];

            return json_encode($array, JSON_UNESCAPED_UNICODE);       

        }else{
            throw new PedidoInexistenteException('Erro ao montar o pedido para enviar ao Omie: Estrutura de pedido com problema',500);
        }
    
    }

    public function createOrderErp(string $jsonPedido): array{

        return $this->omieServices->criaPedidoErp($jsonPedido);

    }

    public function getOrdersServicesItens(array $prdItem, int $idItemOmie, object $order):array
    {
        $serviceOrder = [];
        $pu = [];
        $service = [];
        $produtosUtilizados = [];

        //verifica se tem serviço com produto junto
        if($prdItem['Product']['Group']['Name'] !== 'Serviços'){
                     
            //monts o produtos utilizados (pu)
            $pu['nCodProdutoPU'] = $idItemOmie;
            $pu['nQtdePU'] = $prdItem['Quantity'];
            
            $produtosUtilizados[] = $pu;
            
        }else{
            
            //monta o serviço
            $service['nCodServico'] = $idItemOmie;
            $service['nQtde'] = $prdItem['Quantity'];
            $service['nValUnit'] = $prdItem['UnitPrice'];
            $service['cDescServ'] = $order->descricaoServico;
            
            $serviceOrder[] = $service;
        }

        $contentServices['servicos'] = $serviceOrder;
        $contentServices['produtosServicos'] = $produtosUtilizados;

        return $contentServices;

    }

    public function getOrdersProductsItens(array $prdItem, int $idItemOmie, object $order):array
    {
        $det = [];  
        $det['ide'] = [];
        $det['produto'] = [];

        $det['ide']['codigo_item_integracao'] = $prdItem['Id'];
        $det['produto']['quantidade'] = $prdItem['Quantity'];
        $det['produto']['valor_unitario'] = $prdItem['UnitPrice'];
        $det['produto']['codigo_produto'] = $idItemOmie;

        $det['inf_adic'] = [];
        $det['inf_adic']['numero_pedido_compra'] = $order->numPedidoCompra;
        $det['inf_adic']['item_pedido_compra'] =$prdItem['Ordination']+1;

        return $productsOrder[] = $det;

    }

    public function getIdVendedorERP(object $omie, string $mailVendedor):string|null
    {
        return $this->omieServices->vendedorIdErp($omie, $mailVendedor);
    }

    //clientes

    //cadastra obj cliente com dados vindos do erp para enviar ao crm
    public function createObjectCrmContactFromErpData(array $args):object
    {
        $cliente = new stdClass();
        $decoded=$args['body'];
        $cliente->codigoClienteOmie= $decoded['event']['codigo_cliente_omie'];

        $omieApp = $this->omieServices->getOmieApp();

        $c =  $this->omieServices->getClientById($cliente);
        
        $array = DiverseFunctions::achatarArray($c);
        $chave = 'idClienteOmie' . $omieApp['app_name'];
        $cliente->$chave = $array['codigo_cliente_omie'];
    
        //$cliente->messageId = $array['messageId'];
        // $cliente->topic = $array['topic'];
        $cliente->bairro = $array['bairro'] ?? null;
        $cliente->bloqueado = $array['bloqueado']  ?? null;
        $cliente->bloquearFaturamento = $array['bloquear_faturamento']  ?? null;
        $cep = (int)str_replace('-','',$array['cep'])  ?? null;
        $cliente->cep = $cep  ?? null;
        $cliente->cidade = $array['cidade']  ?? null;
        $cliente->cidadeIbge = $array['cidade_ibge'] ?? null  ?? null;
        $cliente->cnae = $array['cnae']  ?? null;
        $cliente->cnpjCpf = $array['cnpj_cpf']  ?? null;
        $cliente->codigoClienteIntegracao = $array['codigo_cliente_integracao']  ?? null;
        $cliente->codigoPais = $array['codigo_pais']  ?? null;
        $cliente->complemento = $array['complemento']  ?? null;
        $cliente->contato = $array['contato']  ?? null;
        $cliente->contribuinte = $array['contribuinte']  ?? null;
        $cliente->agencia = $array['dadosBancarios_agencia']  ?? null;
        $cliente->cBanco = $array['dadosBancarios_codigo_banco']  ?? null;
        $cliente->nContaCorrente = $array['dadosBancarios_conta_corrente']  ?? null;
        $cliente->docTitular = $array['dadosBancarios_doc_titular']  ?? null;
        $cliente->nomeTitular = $array['dadosBancarios_nome_titular']  ?? null;
        $cliente->email = $array['email']  ?? null;
        $cliente->endereco = $array['endereco']  ?? null;
        $cliente->enderecoNumero = $array['endereco_numero']  ?? null;
        $cliente->estado = $array['estado']  ?? null;
        $cliente->exterior = $array['exterior']  ?? null;
        $cliente->faxDdd = $array['fax_ddd']  ?? null;
        $cliente->faxNumero = $array['fax_numero']  ?? null;
        $cliente->homepage = $array['homepage']  ?? null;
        $cliente->inativo = $array['inativo']  ?? null;
        $cliente->inscricaoEstadual = $array['inscricao_estadual']  ?? null;
        $cliente->inscricaoMunicipal = $array['inscricao_municipal']  ?? null;
        $cliente->inscricaoSuframa = $array['inscricao_suframa']  ?? null;
        $cliente->logradouro = $array['logradouro']  ?? null;
        $cliente->nif = $array['nif']  ?? null;
        $cliente->nomeFantasia = htmlspecialchars_decode($array['nome_fantasia'])  ?? null;
        $cliente->obsDetalhadas = $array['obs_detalhadas']  ?? null;
        $cliente->observacao = $array['observacao']  ?? null;
        $cliente->simplesNacional = $array['optante_simples_nacional']  ?? null;
        $cliente->pessoaFisica = $array['pessoa_fisica']  ?? null;
        $cliente->produtorRural = $array['produtor_rural']  ?? null;
        $cliente->razaoSocial = htmlspecialchars_decode($array['razao_social'])  ?? null;
        $cliente->recomendacaoAtraso = $array['recomendacao_atraso']  ?? null;
        $cliente->codigoVendedor = $array['recomendacoes_codigo_vendedor'] ?? null;
        $cliente->emailFatura = $array['recomendacoes_email_fatura'] ?? null;
        $cliente->gerarBoletos = $array['recomendacoes_gerar_boletos'] ?? null;
        $cliente->numeroParcelas = $array['recomendacoes_numero_parcelas'] ?? null;
        $cliente->idTranspPadrao = $array['recomendacoes_codigo_transportadora'] ?? null;
        $transp = new stdClass();
        $transp->codigoClienteOmie = $cliente->idTranspPadrao;
        $transp = $this->omieServices->getClientByid($transp );
        $cliente->idTranspPadraoPloomes = $transp['codigo_cliente_integracao'] ?? null;
        $tags=[];
     
        foreach($decoded['event']['tags'] as $t=>$v){
            $tags[$t]=$v;
           
        }
        $cliente->tags = $tags;
        $cliente->telefoneDdd1 = $array['telefone1_ddd'];
        $cliente->telefoneNumero1 = $array['telefone1_numero'];
        $cliente->telefoneDdd2 = $array['telefone2_ddd'];
        $cliente->telefoneNumero2 = $array['telefone2_numero'];
        $cliente->tipoAtividade = $array['tipo_atividade'];
        $cliente->limiteCredito = $array['valor_limite_credito'];
        // $cliente->authorEmail = $array['author_email'];
        // $cliente->authorName = $array['author_name'];
        // $cliente->authorUserId = $array['author_userId'];
        $cliente->appKey = $decoded['appKey'];
        // $cliente->appHash = $array['appHash'];
        // $cliente->origin = $array['origin'];
        return $cliente;
    }

    // createPloomesContactFromErpObject - cria o json no formato do ploomes para enviar pela API do Ploomes
    public function createPloomesContactFromErpObject(object $contact, object $ploomesServices):string
    {
        $omie = new stdClass();
        $omieApp = $this->omieServices->getOmieApp();
        
        $omie->appName = $omieApp['app_name'];
        $omie->appSecret = $omieApp['app_secret'];
        $omie->appKey = $omieApp['app_key'];
        $omie->ncc = $omieApp['ncc'];
        $omie->tenancyId = $omieApp['tenancy_id'];
        $custom = $_SESSION['contact_custom_fields'][$omie->tenancyId];
        
        $data = [];
        $data['TypeId'] = 1;
        $data['Name'] = $contact->nomeFantasia;
        $data['LegalName'] = $contact->razaoSocial;
        $data['Register'] = DiverseFunctions::limpa_cpf_cnpj($contact->cnpjCpf);
        $data['Neighborhood'] = $contact->bairro ?? null;
        $data['StatusId'] = 40059036;
        $data['ZipCode'] = $contact->cep ?? null;
        $data['StreetAddress'] = $contact->endereco ?? null;
        $data['StreetAddressNumber'] = $contact->enderecoNumero ?? null;
        $data['StreetAddressLine2'] = $contact->complemento ?? null;
        $city = (!empty($contact->cidadeIbge) ? $ploomesServices->getCitiesByIBGECode($contact->cidadeIbge) : null);
        $data['CityId'] = $city['Id'] ?? null;//pegar na api do ploomes
        $data['LineOfBusiness'] = $contact->segmento ?? null;//Id do Tipo de atividade(não veio no webhook de cadastro do omie)
        $data['NumbersOfEmployeesId'] = $contact->nFuncionarios ?? null;//Id do número de funcionários(não veio no webhook de cadastro do omie)
        $mailVendedor = (!empty($contact->codigoVendedor) ? $this->omieServices->getMailVendedorById($omie, $contact) : null);
        $contact->mailVendedor = $mailVendedor ?? null; 
        $idVendedorPloomes = (!empty($contact->ownerId) ? $ploomesServices->ownerId($contact) : null);
        (!$idVendedorPloomes) ? $contact->cVendedorPloomes = null : $contact->cVendedorPloomes = $idVendedorPloomes;
        $data['OwnerId'] = $contact->cVendedorPloomes ?? null;//Id do vendedor padrão(comparar api ploomes)
        $data['Note'] = $contact->observacao ?? null;
        $data['Email'] = $contact->email ?? null;
        $data['Website'] = $contact->homepage ?? null;
        $data['RoleId'] = $contact->cargo ?? null;//Id do cargo do cliente(inexistente no omie)
        $data['DepartmentId'] = $contact->departamento ?? null;//Id do departamento do cliente(inexistente no omie)
        $data['Skype'] = $contact->skype ?? null;//Skype do cliente(inexistente no omie)
        $data['Facebook'] = $contact->facebook ?? null;//Facebook do cliente(inexistente no omie)
        $data['ForeignZipCode'] = $contact->cepInternacional ?? null;//(inexistente no omie)
        $data['CurrencyId'] = $contact->moeda ?? null;//(inexistente no omie)
        $data['EmailMarketing'] = $contact->marketing ?? null;//(inexistente no omie)
        $data['CNAECode'] = $contact->cnae ?? null;
        $data['Latitude'] = $contact->latitude ?? null;//(inexistente no omie)
        $data['Longitude'] = $contact->longitude ?? null;//(inexistente no omie)
        $data['Key'] = $contact->codigoClienteOmie ?? null;//chave externa do cliente(código Omie)
        $data['AvatarUrl'] = $contact->avatar ?? null;//(inexistente no omie)
        $data['IdentityDocument'] = $contact->exterior ?? null;//(documento internacional exterior)
        $data['CNAEName'] = $contact->cnaeName ?? null;//(inexistente no omie)
        //aqui cria um novo contato para a empresa com os dados do contato cadastrado no Omie
        // $person = [];
        // $person['Id'] = $contact->contatoId ?? null;
        // $person['TypeId'] = 2;
        // $person['Name'] = $contact->contato;
        
        // $data['Contacts'] = $person;
        $data['Phones'] = [];
        $ddd1 = preg_replace("/[^0-9]/", "", $contact->telefoneDdd1);
        $ddd2 = preg_replace("/[^0-9]/", "", $contact->telefoneDdd2);
        $phone1 = [
            'PhoneNumber'=>"($ddd1) $contact->telefoneNumero1" ?? null,
            'TypeId'=>1,
            'CountryId'=>76,
        ];
        
        $phone2 = [
            'PhoneNumber'=>"($ddd2) $contact->telefoneNumero2",
            'TypeId' => 2,
            'CountryId' => 76,
        ];
        $data['Phones'][] = $phone1 ?? null;
        $data['Phones'][] = $phone2 ?? null;

        //OtherProperties
        $op = [];

        if(!empty($contact->tipoAtividade) || $contact->tipoAtividade == 0){
            
            $atividade = $this->omieServices->getTipoAtividade( $omie, $contact->tipoAtividade, $name = null);
            
            foreach($custom['Cliente'] as $c){
                if($c['SendExternalKey'] === 'bicorp_api_tipo_atividade_out'){
                    
                    foreach($c['Options'] as $optAtividade){
                        
                        if($optAtividade['Name'] === $atividade['cDescricao']){

                            $contact->tipoAtividade = $optAtividade['Id'];
                        }
                    }
                }
            }

        }
        
        $ploomesTags = $ploomesServices->getTagsByEntityId(1);//id da entidade
        
        $tags = [];
        $tag = [];
        if(isset($contact->tags) && !empty($contact->tags)){
            
            foreach($contact->tags as $t)
            {
                foreach($ploomesTags as $pTag){
                    if(strtolower($pTag['Name']) === strtolower($t['tag'])){
                        $tag['TagId'] = $pTag['Id'];
                        $tag['Tag']['Name'] = $pTag['Name'];
                    }
                }                
        
                $tags[]=$tag;
            }
            $data['Tags'] = $tags;
        }else{
            $data['Tags'] = null;
        }     
         
        $op = CustomFieldsFunction::createOtherPropertiesByEntity($custom['Cliente'], $contact);
        
        $data['OtherProperties'] = $op;
        
        $json = json_encode($data,JSON_UNESCAPED_UNICODE);
     
        return $json;

    }

    // createObjectErpClientFromCrmData - cria obj cliente vindo do Ploomes ao ERP Omie
    public function createObjectErpClientFromCrmData(array $args, object $ploomesServices):object
    {
        $decoded = $args['body'];
        $cliente = $ploomesServices->getClientById($decoded['New']['Id']);
        $omie = new stdClass();
        //este app omie só pode servir para buscar campos fixos do omie os dados dos usuários devem vir do ploomes
        $omieApp =$args['Tenancy']['erp_bases'][0];
        
        $omie->appName = $omieApp['app_name'];
        $omie->appSecret = $omieApp['app_secret'];
        $omie->appKey = $omieApp['app_key'];
        $omie->ncc = $omieApp['ncc'];
        $omie->tenancyId = $omieApp['tenancy_id'];
        
        $contact = new stdClass(); 
        
        $custom = CustomFieldsFunction::compareCustomFields($decoded['New']['OtherProperties'],$args['Tenancy']['tenancies']['id'],'Cliente');

        $allCustoms = $_SESSION['contact_custom_fields'][$args['Tenancy']['tenancies']['id']];
        
        /************************************************************
         *                   Other Properties                        *
         *                                                           *
         * No webhook do Contact pegamos os campos de Other Properies*
         * para encontrar a chave da base de faturamento do Omie     *
         *                                                           *
         *************************************************************/
        $prop = [];
        foreach ($decoded['New']['OtherProperties'] as $key => $op) {
            $prop[$key] = $op;
            // print '['.$key.']=>['.$op.']';
        }

        //contact_879A3AA2-57B1-49DC-AEC2-21FE89617665 = tipo de cliente
        $contact->tipoCliente = $prop['contact_879A3AA2-57B1-49DC-AEC2-21FE89617665'] ?? null;
        //contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227 = ramo de atividade
        //$contact->ramoAtividade = $prop['contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227'] ?? null;
        // $ramo= $prop['contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227'] ?? null;

        foreach($allCustoms['Cliente'] as $ac){
            
            if ($ac['SendExternalKey'] === 'bicorp_api_tipo_atividade_out'){
                
                $options = $ac['Options'];
                
                foreach($options as $opt){
                    
                    if($opt['Id'] === $custom['bicorp_api_tipo_atividade_out']){
                     
                        $atividade = $this->omieServices->getTipoATividade($omie, $id = null, $opt['Name']);
                        $contact->tipoAtividade = $atividade['cCodigo'];
                    }
                }
            }
        }
        

        //contact_FA99392B-CED8-4668-B003-DFC1111DACB0 = Porte
        //$contact->porte = $prop['contact_FA99392B-CED8-4668-B003-DFC1111DACB0'] ?? null;
        //contact_20B72360-82CF-4806-BB05-21E89D5C61FD = importância
        //$contact->importancia = $prop['contact_20B72360-82CF-4806-BB05-21E89D5C61FD'] ?? null;
        //contact_5F52472B-E311-4574-96E2-3181EADFAFBE = situação
        $contact->situacao = $prop['contact_5F52472B-E311-4574-96E2-3181EADFAFBE'] ?? null;
        //contact_9E595E72-E50C-4E95-9A05-D3B024C177AD = ciclo de compra
        //$contact->cicloCompra = $prop['contact_9E595E72-E50C-4E95-9A05-D3B024C177AD'] ?? null;
        //contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB = inscrição estadual
        // $contact->inscricaoEstadual = $prop['contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB'] ?? null; //estava com esta linha aqui ativa
        $contact->inscricaoEstadual = $custom['bicorp_api_inscricaoEstadual_out'] ?? null;
        //contact_D21FAEED-75B2-40E4-B169-503131EB3609 = inscrição municipal
        $contact->inscricaoMunicipal = $prop['contact_D21FAEED-75B2-40E4-B169-503131EB3609'] ?? null;
        //contact_3094AFFE-4263-43B6-A14B-8B0708CA1160 = inscrição suframa
        $contact->inscricaoSuframa = $prop['contact_3094AFFE-4263-43B6-A14B-8B0708CA1160'] ?? null;
        //contact_9BB527FD-8277-4D1F-AF99-DD88D5064719 = Simples nacional?(s/n)  
        $contact->simplesNacional = $prop['contact_9BB527FD-8277-4D1F-AF99-DD88D5064719'] ?? null;//é obrigatório para emissão de nf
        (isset($contact->simplesNacional) && $contact->simplesNacional !== false) ? $contact->simplesNacional = 'S' : $contact->simplesNacional = 'N';
        //contact_3C521209-46BD-4EA5-9F41-34756621CCB4 = contato1
        // $contact->contato1 = $prop['contact_3C521209-46BD-4EA5-9F41-34756621CCB4'] ?? null; //estava com esta linha aqui ativa
        $contact->contato1 = $custom['bicorp_api_contato_out'] ?? null;
        //contact_F9B60153-6BDF-4040-9C3A-E23B1469894A = Produtor Rural
        $contact->produtorRural = $prop['contact_F9B60153-6BDF-4040-9C3A-E23B1469894A'] ?? null;
        (isset($contact->produtorRural) && $contact->produtorRural !== false) ? $contact->produtorRural = 'S' : $contact->produtorRural = 'N';
        //contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453 = Contribuinte(s/n)
        $contact->contribuinte = $prop['contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453'] ?? null;
        (isset($contact->contribuinte) && $contact->contribuinte !== false) ? $contact->contribuinte = 'S' : $contact->contribuinte = 'N';
        //contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD = limite de crédito
        $contact->limiteCredito = $prop['contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD'] ?? null;
        //contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4 = inativo (s/n)
        $contact->inativo = $prop['contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4'] ?? null;
        (isset($contact->inativo) && $contact->inativo !== false) ? $contact->inativo = 'S' : $contact->inativo = 'N';
        //contact_C613A391-155B-42F5-9C92-20C3371CC3DE = bloqueia excusão (s/n)
        $contact->bloquearExclusao = $prop['contact_C613A391-155B-42F5-9C92-20C3371CC3DE'] ?? null;
        (isset($contact->bloquearExclusao) && $contact->bloquearExclusao !== false) ? $contact->bloquearExclusao = 'S' : $contact->bloquearExclusao = 'N';
        //contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33 = transportadora padrão
        $contact->cTransportadoraPadrao = $prop['contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33'] ?? null;
        //contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9 = codigo do banco
        $contact->cBanco = $prop['contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9'] ?? null;
        //contact_1F1E1F00-34CB-4356-B852-496D62A90E10 = Agência
        $contact->agencia = $prop['contact_1F1E1F00-34CB-4356-B852-496D62A90E10'] ?? null;
        //contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80 = Num conta corrente
        $contact->nContaCorrente = $prop['contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80'] ?? null;
        //contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50 = CNPJ/CPF titular
        $contact->docTitular = $prop['contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50'] ?? null;
        //contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066 = Nome di titular
        $contact->nomeTitular = $prop['contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066'] ?? null;
        //contact_847FE760-74D0-462D-B464-9E89C7E1C28E = chave pix
        $contact->chavePix = $prop['contact_847FE760-74D0-462D-B464-9E89C7E1C28E'] ?? null;
        //contact_3E075EA9-320C-479E-A956-5A3734C55E51 = Transportadora Padrão (código cliente ploomes)
        $contact->idTranspPadrao = $prop['contact_3E075EA9-320C-479E-A956-5A3734C55E51'] ?? null;
        if($contact->idTranspPadrao !== null){
            $c = $ploomesServices->getClientById($contact->idTranspPadrao);
            $transpOP = [];
            foreach($c['OtherProperties']  as $cOthers){

                $fk = $cOthers['FieldKey'];
                $vl = $cOthers['StringValue'];

                    $transpOP[$fk] = $vl;
            }
            
            $contact->cTranspOmie = [];
            $contact->cTranspOmie[0] = $transpOP['contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3'] ?? null;
            $contact->cTranspOmie[1] = $transpOP['contact_6DB7009F-1E58-4871-B1E6-65534737C1D0'] ?? null;
            $contact->cTranspOmie[2] = $transpOP['contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2'] ?? null;
            $contact->cTranspOmie[3] = $transpOP['contact_07784D81-18E1-42DC-9937-AB37434176FB'] ?? null;

        }   

        //contact_33015EDD-B3A7-464E-81D0-5F38D31F604A = Transferência Padrão
        $contact->transferenciaPadrao = $prop['contact_33015EDD-B3A7-464E-81D0-5F38D31F604A'] ?? null;
        (isset($contact->transferenciaPadrao) && $contact->transferenciaPadrao !== false) ? $contact->transferenciaPadrao = 'S' : $contact->transferenciaPadrao = 'N';
        //contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C = Integrar com base omie 1? (s/n)
        // (isset($prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C']) && $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] !== false) ? $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 1 : $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 0;
        
        $phones = [];
        foreach($cliente['Phones'] as $phone){
            
            $partes = explode(' ',$phone['PhoneNumber']);
            $ddd = $partes[0];
            $nPhone = $partes[1];
            $phones[] = [
                'ddd'=>$ddd,
                'nPhone' => $nPhone
            ];        
        }
        
        $contact->id = $cliente['Id']; //Id do Contact
        $contact->name = $cliente['Name']; // Nome ou nome fantasia do contact !obrigatório!
        $contact->legalName = $cliente['LegalName']; // Razão social do contact !obrigatório!
        $contact->cnpj = $cliente['CNPJ'] ?? null; // Contatos CNPJ !obrigatório!
        $contact->cpf = $cliente['CPF'] ?? null; // Contatos CPF !obrigatório!
        $contact->documentoExterior = (isset($cliente['IdentityDocument']) && $cliente['IdentityDocument'] === 'N' ) ? null :  $cliente['IdentityDocument']; // Documento extrangeiro CPF
        $contact->segmento = ($cliente['LineOfBusiness']['Id']) ?? null; // Segmento CPF
        $contact->email = $cliente['Email']; // Contatos Email obrigatório
        $contact->website = $cliente['Website'] ?? null; // Contatos website obrigatório
        $contact->ddd1 = $phones[0]['ddd'] ?? null; //"telefone1_ddd": "011",
        $contact->phone1 = $phones[0]['nPhone'] ?? null; //"telefone1_numero": "2737-2737",
        $contact->ddd2 = $phones[1]['ddd'] ?? null; //"telefone1_ddd": "011",
        $contact->phone2 = $phones[1]['nPhone'] ?? null; //"telefone1_numero": "2737-2737",
        //$contact->contato1 = $prop['contact_E6008BF6-A43D-4D1C-813E-C6BD8C077F77'] ?? null;
        $contact->streetAddress = $cliente['StreetAddress']; // Endereço !obrigatório!
        $contact->streetAddressNumber = $cliente['StreetAddressNumber']; // Número Endereço !obrigatório!
        $contact->streetAddressLine2 = $cliente['StreetAddressLine2'] ?? null; // complemento do Endereço 
        $contact->neighborhood = $cliente['Neighborhood']; // bairro do Endereço é !obrigatório!
        $contact->zipCode = $cliente['ZipCode']; // CEP do Endereço é !obrigatório!
        $contact->cityId = $cliente['City']['IBGECode'] ?? null; // Id da cidade é obrigatório
        $contact->cityName = $cliente['City']['Name'] ?? null; // estamos pegando o IBGE code
        $contact->cityLagitude = $cliente['City']['Latitude'] ?? null; // Latitude da cidade 
        $contact->cityLongitude = $cliente['City']['Longitude'] ?? null; // Longitude da cidade 
        $contact->stateShort = $cliente['State']['Short']; // Sigla do estado é !obrigatório!
        $contact->stateName = $cliente['State']['Name'] ?? null; //estamos pegando a sigla do estado
        $contact->countryId = $cliente['CountryId'] ?? null; // Omie preenche o país automaticamente
        $contact->cnaeCode = $cliente['CnaeCode'] ?? null; // Id do cnae 
        $contact->cnaeName = $cliente['CnaeName'] ?? null; // Name do cnae 
        $contact->ownerId = $cliente['Owner']['Id'] ?? null; // Responsável (Vendedor)
        //$contact->ownerEmail = 'tecnologia@bicorp.com.br';// Responsável (Vendedor) 
        $contact->ownerEmail = $cliente['Owner']['Email'] ?? null; // Responsável (Vendedor) 
        $contact->observacao = $cliente['Note']; // Observação 
        
        //inclui na base de faturamento o indicativo de que integrar esta marcado como sim para a base
        
        $bases =[];
        $contact->codOmie = [];
        foreach($args['Tenancy']['erp_bases'] as $base)
        {   
            $name = strtolower($base['app_name']);
            $chaveId = "bicorp_api_id_cliente_erp_{$name}_out";
            
            $contact->codOmie[] = $custom[$chaveId] ?? null;           
            
            $chave = "bicorp_api_integrar_base_{$name}_out";
            
            if(isset($custom[$chave]) && $custom[$chave] !== false){
                $base['sendExternalKey'] = $chave;
                $base['sendExternalKeyIdErp'] = $chaveId;
                $base['integrar'] = 1;
            }
            
            $bases[] =$base; 
        }

        $contact->basesFaturamento = $bases;      
        
        $tags = [];

        if (isset($decoded['New']['Tags']) && !empty($decoded['New']['Tags'])) {
            $entityId = 1;
            $tagsPloomes = $ploomesServices->getTagsByEntityId($entityId);

            foreach ($decoded['New']['Tags'] as $iTag) {
                foreach ($tagsPloomes as $tagPloo) {
                    if ($iTag['TagId'] === $tagPloo['Id']) {
                        $tags[] = [
                            'tag' => $tagPloo['Name']
                        ];
                    }
                }
            }
        }
        
        $contact->tags = $tags;
        
        return $contact;
    }

    // updateContactCRMToERP - atualiza um contato do CRM para o ERP ok
    public function updateContactCRMToERP(object $contact, object $ploomesServices, object $tenant):array
    {
        $json = $this->createJsonClienteCRMToERP($contact, $tenant); 

        $alterar = $this->omieServices->alteraClienteCRMToERP($json);

        if(isset($alterar['codigo_status']) && $alterar['codigo_status'] == "0") 
        {
            $json = $this->insertIdClienteERPinContactCRM($alterar, $tenant);
            //insere o id do omie no campo correspondente do cliente Ploomes
            ($ploomesServices->updatePloomesContact($json, $contact->id)) ?
            $mUpdateContact = 'Id cliente ERP inserido no cliente do CRM. ':
            $mUpdateContact = 'Não foi possível inserir o Id cliente ERP no cliente do CRM ';

            $messages['success'][] = "Integração concluída com sucesso! Cliente Ploomes id: {$contact->id} alterado no Omie ERP ({$tenant->tenant}) com o numero: {$alterar['codigo_cliente_omie']}.{$mUpdateContact} e mensagem enviada com sucesso em: {$this->current}";

        }else{

            //monta a mensagem para atualizar o card do ploomes
            $msg=[
                'ContactId' => $contact->id,
                'Content' => 'Erro ao alterar cliente no Omie: '. $alterar['faultstring'].' na base '.$tenant->tenant.' Data = '.$this->current,
                'Title' => 'Erro ao alterar cliente'
            ];
            //cria uma interação no card
            ($ploomesServices->createPloomesIteraction(json_encode($msg))) ? $message = 'Erro ao alterar cliente no Omie base '.$tenant->tenant.': '. $alterar['faultstring'].' Data = '.$this->current: $message = 'Erro ao alterar cliente no Omie base '.$tenant->tenant.': '. $alterar['faultstring'].' e erro ao enviar mensagem no card do cliente do Ploomes Data = '.$this->current;
            $messages['error'][]=$message;
        } 
        
        return $messages;
       
    }

    // createContact - cria um contato no CRM envia ao ERP ok
    public function createContactCRMToERP(object $contact, object $ploomesServices, object $tenant):array
    { 
        $messages=['success'=>[],'error'=>[]];
                
        $json = $this->createJsonClienteCRMToERP($contact, $tenant);
        
        $criaClienteERP = $this->omieServices->criaClienteERP($json);

        //verifica se criou o cliente no ERP (No caso do Omie, o prórpio retorn o traz o status da inserção e o ID do cliente)
        if (isset($criaClienteERP['codigo_status']) && $criaClienteERP['codigo_status'] == "0") {

            //atualiza contact ploomes com o id do cliente no ERP id omie no ploomes teste voltar a ele 1940698872
            $json = $this->insertIdClienteERPinContactCRM($criaClienteERP, $tenant);
            
            //insere o id do omie no campo correspondente do cliente Ploomes
            ($ploomesServices->updatePloomesContact($json, $contact->id)) ?
            $mUpdateContact = 'Id cliente ERP inserido no cliente do CRM. ':
            $mUpdateContact = 'Não foi possível inserir o Id cliente ERP no cliente do CRM ';
         
            $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o código: '.$criaClienteERP['codigo_cliente_omie']. ' e ' .$mUpdateContact. ' em: '.$this->current;
            
            $messages['success'] = $message;
   
        }else{
            //monta a mensagem para atualizar o card do ploomes
            $msg=[
                'ContactId' => $contact->id,
                'Content' => 'Erro ao gravar cliente no Omie: '. $criaClienteERP['faultstring'].' na base '.$tenant->tenant.' Data = '.$this->current,
                'Title' => 'Erro ao Gravar cliente'
            ];
            
            //cria uma interação no card
            ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Erro ao gravar cliente no Omie base '.$tenant->tenant.': '. $criaClienteERP['faultstring'].' Data = '.$this->current: $message = 'Erro ao gravar cliente no Omie base '.$tenant->tenant.': '. $criaClienteERP['faultstring'].' e erro ao enviar mensagem no card do cliente do Ploomes Data = '.$this->current;

           $messages['error']= $message;
        }       
        return $messages;
    }

    // createContactERP - cria um contato no ERP envia ao CMR
    public function createContactERP(string $json, object $ploomesServices):array
    {

        $contact = json_decode($json);
        // print_r($contact);
        // exit;
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
   
        $current = date('d/m/Y H:i:s');
        

        //$cnpj = DiverseFunctions::limpa_cpf_cnpj($contact->register);
        $pContact = $ploomesServices->consultaClientePloomesCnpj($contact->Register);

        if($pContact !== null){
            // $messages['error'] = 'Erro ao cadastrar o cliente '.$contact->nomeFantasia .'('.$contact->cnpjCpf.') Cliente já cadastrado no Ploomes com o código: '.$pContact.' Data:' .$current;

           
            $process = $this->updateContactERP($json, $contact, $ploomesServices);

            if($process){
                return $process;
            }

        }else{
  
            if(!$ploomesServices->createPloomesContact($json)){

            }else{
                $messages['success'] = 'Cliente '.$contact->LegalName.' Cadastrado no Ploomes CRM com sucesso! Data: '.$current;
            }
            
        }
        return $messages;
    }

    // updateContactERP - atualiza um contato do ERP para o CRM
    public function updateContactERP(string $json, object $contact, object $ploomesServices):array
    {
        $cpf = $contact->cnpjCpf ?? $contact->Register;
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        $current = date('d/m/Y H:i:s');
        $idContact = $ploomesServices->consultaClientePloomesCnpj(DiverseFunctions::limpa_cpf_cnpj($cpf));

        if(!$idContact)
            {
                $messages['error'] = 'Erro: cliente '.$contact->nomeFantasia.' não foi encontrado no Ploomes CRM';
            }
        else
            {
    
                $ploomesServices->updatePloomesContact($json, $idContact);
                // $messages['success'] = 'Cliente '.$contact->nomeFantasia.' alterado no Ploomes CRM com sucesso!';
                //monta a mensagem para atualizar o cliente do ploomes
                $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$idContact.' alterado no Ploomes CRM com sucesso em: '.$current;
                
                // $msg=[
                //     'ContactId' => $idContact,
                //     'Content' => 'Cliente '.$contact->nomeFantasia.' alterado no Omie ERP na base: '.$contact->baseFaturamentoTitle.' via Bicorp Integração',
                //     'Title' => 'Cliente Alterado'
                // ];
                
                // //cria uma interação no card
                // ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$idContact.' alterado no Ploomes CRM ('.$contact->baseFaturamentoTitle.') e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$idContact.' alterado no PLoomes CRM, porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;
                
                $messages['success'] = $message;
            }

        return $messages;
    }

    public function createJsonClienteCRMToERP(object $contact, object $tenant)
    {
        $array = [
            'app_key'=>$tenant->appKey,
            'app_secret'=>$tenant->appSecret,
            'call'=>'UpsertClienteCpfCnpj',
            'param'=>[]
        ];
    
        $clienteJson = [];

        $clienteJson['razao_social'] = htmlspecialchars_decode($contact->legalName) ?? null; 
        $clienteJson['nome_fantasia'] = htmlspecialchars_decode($contact->name) ?? null;
        $clienteJson['cnpj_cpf'] = $contact->cnpj ?? $contact->cpf ?? null;
        $clienteJson['email'] = $contact->email ?? null;
        $clienteJson['homepage'] = $contact->website ?? null;
        $clienteJson['telefone1_ddd'] = $contact->ddd1 ?? null;
        $clienteJson['telefone1_numero'] = $contact->phone1 ?? null;
        $clienteJson['telefone2_ddd'] = $contact->ddd2 ?? null;
        $clienteJson['telefone2_numero'] = $contact->phone2 ?? null;
        $clienteJson['contato'] = $contact->contato1 ?? null;
        $clienteJson['endereco'] = $contact->streetAddress ?? null;
        $clienteJson['endereco_numero'] = $contact->streetAddressNumber ?? null;
        $clienteJson['bairro'] = $contact->neighborhood ?? null;
        $clienteJson['complemento'] = $contact->streetAddressLine2 ?? null;
        $clienteJson['estado'] = $contact->stateShort ?? null;//usar null para teste precisa pegar o codigo da sigla do estado na api omie
        //$clienteJson['cidade'] = $contact->cityName;
        $clienteJson['cidade_ibge'] = $contact->cityId ?? null;
        // $clienteJson['cep'] = $contact->streetAdress ?? null;
        $clienteJson['cep'] = $contact->zipCode ?? null;
        $clienteJson['documento_exterior'] = $contact->documentoExterior ?? null;
        $clienteJson['inativo'] = $contact->inativo ?? null;
        $clienteJson['bloquear_exclusao'] = $contact->bloquearExclusao ?? null;
        //inicio aba CNAE e Outros
        $clienteJson['cnae'] = $contact->cnaeCode ?? null;//3091102 ?? null;
        $clienteJson['inscricao_estadual'] = $contact->inscricaoEstadual ?? null;
        $clienteJson['inscricao_municipal'] = $contact->inscricaoMunicipal ?? null;
        $clienteJson['inscricao_suframa'] = $contact->inscricaoSuframa ?? null;
        $clienteJson['optante_simples_nacional'] = $contact->simplesNacional ?? null;
        $clienteJson['produtor_rural'] = $contact->produtorRural ?? null;
        $clienteJson['contribuinte'] = $contact->contribuinte ?? null;
        $clienteJson['tipo_atividade'] = $contact->tipoAtividade ?? null;
        $clienteJson['valor_limite_credito'] = $contact->limiteCredito ?? null;
        $clienteJson['observacao'] = $contact->observacao ?? null;
        //fim aba CNAE e Outros
        //inicio array dados bancários
        $clienteJson['dadosBancarios'] =[];
        $dadosBancarios =[];
        $dadosBancarios['codigo_banco'] = $contact->cBanco ?? null;
        $dadosBancarios['agencia'] = $contact->agencia ?? null;
        $dadosBancarios['conta_corrente'] = $contact->nContaCorrente ?? null;
        $dadosBancarios['doc_titular'] = $contact->docTitular ?? null;
        $dadosBancarios['nome_titular'] = $contact->nomeTitular ?? null;
        $dadosBancarios['transf_padrao'] = $contact->transferenciaPadrao ?? null;
        $dadosBancarios['cChavePix'] = $contact->chavePix ?? null;
        $clienteJson['dadosBancarios'][] =array_filter($dadosBancarios); 
        //fim array dados bancários
        //inicio array recoja mendações
        $clienteJson['recomendacoes'] = [];
        $recomendacoes = [];//vendedor padrão

        $recomendacoes['codigo_vendedor'] = $contact->cVendedorOmie ?? null;
        $recomendacoes['codigo_transportadora']=$contact->cTransportadora ?? null;//6967396742;// $contact->ownerId ?? null;
        $clienteJson['recomendacoes'][] = array_filter($recomendacoes);
        
        //fim array recomendações
        
        $clienteJson['tags']=$contact->tags ?? null;
           
        $array['param'][] = array_filter($clienteJson);

        $json = json_encode($array);
        // print_r($tenant);
        // print_r($contact);
        // // print_r($clienteJson);
        // print_r($json);
        //  exit;

        return $json;

    }

    //Insere o id do ERP no Contact do CRM e retorna mensagem de sucesso
    public function insertIdClienteERPinContactCRM ($criaClienteERP, $tenant){

        $custom = CustomFieldsFunction::getCustomFields();
        
        foreach($custom['Cliente'] as $op){
            if($op['SendExternalKey'] === $tenant->sendExternalKeyIdErp)
            {
                $fieldKey = $op['Key'];
            }
        }

        $codigoERP = $criaClienteERP['codigo_cliente_omie'];

        $array = [
            'TypeId'=>1,
            'OtherProperties'=>[
                [
                    'FieldKey'=>$fieldKey,
                    'StringValue'=>"$codigoERP",
                ]
            ]
        ];

        $json = json_encode($array);
        
        return $json;
    }

    //products

    //cria um objeto do webhook vindo do omie para enviar ao ploomes
    public function createObjectCrmProductFromErpData($args, $ploomesServices)
    {
        $decoded = $args['body'];
    
        // Função recursiva para limpar todos os campos do array
        function auto_clean_json($data) {
            $entidades_customizadas = [
                "+Chr(39)+" => "'", // Aspas simples
                "Chr(34)"   => '"', // Aspas duplas
                "&apos;"    => "'", // Entidade HTML para aspas simples
            ];

            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    $value = auto_clean_json($value);
                } elseif (is_string($value)) {
                    // Decodifica entidades HTML
                    $value = htmlspecialchars_decode($value, ENT_QUOTES);

                    // Substitui padrões específicos
                    $value = strtr($value, $entidades_customizadas);

                    // Substitui múltiplas aspas simples (3 ou mais) por uma única
                    $value = preg_replace("/'{2,}/", "'", $value);
                }
            }
            return $data;
        }


        // Aplica a função para limpar todos os campos do JSON decodificado
        $cleaned_data = auto_clean_json($decoded);

        //achata o array multidimensional decoded em um array simples
        $array = DiverseFunctions::achatarArray($cleaned_data);
        //cria o objeto de produtos
        $product = new stdClass();
        $omieApp = $this->omieServices->getOmieApp();

        $chave = 'idProductOmie' . $omieApp['app_name'];
        $product->$chave = $array['event_codigo_produto'];

        $product->appKey = $array['appKey'];
        $product->appSecret = $omieApp['app_secret'];
        $pFamily = explode('.' , $array['topic']);
        $nameFamily= $pFamily[0].'s';//o s é para o topic Produto ficar no plural
        $verifyFamily = $ploomesServices->getFamilyByName($nameFamily);
        if(isset($verifyFamily['Id'])){
            $product->idFamily =  $verifyFamily['Id'];
        }else{
            $createFamily = $ploomesServices->createNewFamily($nameFamily);
            $product->idFamily =  $createFamily['Id'];
        }        
        $product->messageId = $array['messageId'];
        $product->altura = $array['event_altura'];
        $product->bloqueado = $array['event_bloqueado'];
        $product->cnpj_fabricante = $array['event_cnpj_fabricante'];
        $product->codigo = $array['event_codigo'];
        $product->codigo_familia = $array['event_codigo_familia'];
        ($product->codigo_familia == 0 || $product->codigo_familia == null) ? $product->nome_familia = $nameFamily : $product->nome_familia = $this->omieServices->getFamiliaById($product);
        $verifyGroup = $ploomesServices->getGroupByName($product->nome_familia);
        if(isset($verifyGroup['Id'])){
            $product->idGroup =  $verifyGroup['Id'];
        }else{
            $createGroup = $ploomesServices->createNewGroup($product->nome_familia, $product->idFamily);
            $product->idGroup =  $createGroup['Id'];
        }
        $product->codigo_produto = $array['event_codigo_produto'];
        $product->codigo_produto_integracao = $array['event_codigo_produto_integracao'];
        $product->combustivel_codigo_anp = $array['event_combustivel_codigo_anp'];
        $product->combustivel_descr_anp = $array['event_combustivel_descr_anp'];
        $product->cupom_fiscal = $array['event_cupom_fiscal'];
        $product->descr_detalhada = $array['event_descr_detalhada'];
        $product->descricao = $array['event_descricao'];
        $product->dias_crossdocking = $array['event_dias_crossdocking'];
        $product->dias_garantia = $array['event_dias_garantia'];
        $product->ean = $array['event_ean'];
        $product->estoque_minimo = $array['event_estoque_minimo'];
        $product->id_cest = $array['event_id_cest'];
        $product->id_preco_tabelado = $array['event_id_preco_tabelado'];
        $product->inativo = $array['event_inativo'];
        $product->indicador_escala = $array['event_indicador_escala'];
        $product->largura = $array['event_largura'];
        $product->marca = $array['event_marca'];
        $product->market_place = $array['event_market_place'];
        $product->modelo = $array['event_modelo'];
        $product->ncm = $array['event_ncm'];
        $product->obs_internas = $array['event_obs_internas'];
        $product->origem_mercadoria = $array['event_origem_mercadoria'];
        $product->peso_bruto = $array['event_peso_bruto'];
        $product->peso_liq = $array['event_peso_liq'];
        $product->profundidade = $array['event_profundidade'];
        $product->quantidade_estoque = $array['event_quantidade_estoque'];
        $product->tipoItem = $array['event_tipoItem'];
        $product->unidade = $array['event_unidade'];
        $product->valor_unitario = $array['event_valor_unitario'];
        $product->author_email = $array['author_email'];
        $product->author_name = $array['author_name'];
        $product->author_userId = $array['author_userId'];
        
        $product->appHash = $array['appHash'];
        $product->origin = $array['origin'];      
        //estoque

        $k = 'tabela_estoque_'.strtolower($omieApp['app_name']);
        $product->$k = $this->getStock($product, $omieApp); 
        //$product->stock = $this->getStock($product, $omieApp);      
        
        return $product;
    }

    public function getStock(object $product, array $omieApp){

        $array = [
                    'app_key' => $omieApp['app_key'],
                    'app_secret' => $omieApp['app_secret'],
                    'call' => 'PosicaoEstoque',
                    'param' => [
                        [
                            'id_prod'=>$product->codigo_produto,
                            'data'=>date('d/m/Y'),
                        ]
                    ]
                ];

        $json = json_encode($array);
        $stock = $this->omieServices->getStockById($json);
        $table = $this->createTableStock($stock);

        return $table;

    }

    public function createTableStock($stock)
    {
        $local = ($stock['codigo_local_estoque'] === 6879399409)? 'Padrão' : $stock['codigo_local_estoque'];
        //$html = file_get_contents('http://localhost/gamatermic/src/views/pages/gerenciador.pages.stockTable.php');
        $html = file_get_contents('https://integracao.dev-webmurad.com.br/src/views/pages/gerenciador.pages.stockTable.php');
        $html = str_replace('{local}', $local, $html);
        $html = str_replace('{saldo}', $stock['saldo'], $html);
        $html = str_replace('{minimo}', $stock['estoque_minimo'], $html);
        $html = str_replace('{pendente}', $stock['pendente'], $html);
        $html = str_replace('{reservado}', $stock['reservado'], $html);
        $html = str_replace('{fisico}', $stock['fisico'], $html);
        $html = str_replace('{data}', date('d/m/Y H:i:s'), $html);

        return $html;
    }

    public function moveStock($args,  $ploomesServices)
    {
        $omie = new stdClass();
        $omieApp = $this->omieServices->getOmieApp();
        
        $omie->appName = $omieApp['app_name'];
        $omie->appSecret = $omieApp['app_secret'];
        $omie->appKey = $omieApp['app_key'];
        $omie->ncc = $omieApp['ncc'];
        $omie->tenancyId = $omieApp['tenancy_id'];
        $custom = $_SESSION['contact_custom_fields'][$omie->tenancyId];
        
        $decoded = $args['body'];
        //1 - preciso montar a tabela html cm estoque do produto 
        $stock = [];
        foreach($decoded['event'] as $k => $v){
            $stock[$k] = $v;
        }
        $table = $this->createTableStock($stock);

        //2 - para encontrar o produto podemos pesquisar no ploomes pelo idPloomes(codigo integração omie), pelo Code(código omie) porém eles precisam ser unicos 
        // 2-1 - estamos com webhook do omie, temos o id omie mas não o id ploomes. Temos o code mas ele pode se repitir no ploomes. neste caso precisamos do id de integração no produto pois ele é o id unico do produto no ploomes. sendo assim precisamos forçar este codigo no produto do omie antes de fazer a consulta.

        //$pPloomes = $ploomesService->getProductById($stock['codigo_produto_integracao']);
        $product = new stdClass();
        $product->codigo = $stock['codigo'];

        $key = 'tabela_estoque_'.strtolower($omieApp['app_name']);
        $product->$key = $table;

        $op = CustomFieldsFunction::createOtherPropertiesByEntity($custom['Produto'], $product);

        $array = [];
        // $op =[];
        // $op []= $stockTable;
        $array['OtherProperties'] = $op;
        $json = json_encode($array);
        $pProduct = $ploomesServices->getProductByCode($decoded['event']['codigo']);

        if(!isset($pProduct['Id'])){
            throw new WebhookReadErrorException('Erro ao alterar o estoque do produto: produto ['.$decoded['event']['codigo'].'] não encontrado no Ploomes');
        }

        return $ploomesServices->updatePloomesProduct($json, $pProduct['Id']);


    }

    // cria o objet e a requisição a ser enviada ao ploomes com o objeto do omie
    public function createPloomesProductFromErpObject($product, $ploomesServices)
    {
        $omie = new stdClass();
        $omieApp = $this->omieServices->getOmieApp();
        
        $omie->appName = $omieApp['app_name'];
        $omie->appSecret = $omieApp['app_secret'];
        $omie->appKey = $omieApp['app_key'];
        $omie->ncc = $omieApp['ncc'];
        $omie->tenancyId = $omieApp['tenancy_id'];
        $custom = $_SESSION['contact_custom_fields'][$omie->tenancyId];

        //cria o produto formato ploomes 
        $data = [];
        $data['Name'] = $product->descricao;
        $data['GroupId'] = $product->idGroup;
        $data['FamilyId'] = $product->idFamily;
        $data['Code'] = $product->codigo;
        // $data['Code'] = $product->codigo_produto;
        $data['MeasurementUnit'] = $product->unidade;
        //$data['ImageUrl'] = $product->endereco ?? null;
        //$data['CurrencyId'] = $product->enderecoNumero ?? null;
        $data['UnitPrice'] = $product->valor_unitario ?? null;
        // $data['CreateImportId'] = $city['Id'];//pegar na api do ploomes
        // $data['UpdateImportId'] = $product->segmento ?? null;//Id do Tipo de atividade(não veio no webhook de cadastro do omie)
        // $data['Editable'] = $product->nFuncionarios ?? null;//Id do número de funcionários(não veio no webhook de cadastro do omie)
        // $data['Deletable'] = $product->cVendedorPloomes ?? null;//Id do vendedor padrão(comparar api ploomes)
        // $data['Suspended'] = $product->observacao ?? null;
        // $data['CreatorId'] = $product->email ?? null;
        // $data['UpdaterId'] = $product->homepage ?? null;
        // $data['CreateDate'] = $product->cnae ?? null;
        // $data['LastUpdateDate'] = $product->codigoClienteOmie ?? null;//chave externa do cliente(código Omie)
        //$data['ImportationIdCreate'] = $product->latitude ?? null;(inexistente no omie)
        //$data['ImportationIdUpdate'] = $product->longitude ?? null;(inexistente no omie)

        //     $data['Lists']=null;
        // }else{
           
        //     $marcador = $ploomesServices->getListByTagName($product->nome_familia);

        //     if($marcador){
        
        //         $data['Lists'] = [
        //             [
        //                 'ListId'=> $marcador['Id'],
        //                 'ProductId'=> $pProduct['Id']
        //             ]
        //         ];
    
        //     }else{
        //         $array = [
        //             'Name'=>$product->nome_familia,
        //             'Editable'=>true
        //         ];
        //         $json = json_encode($array);
    
        //         $nMarcador = $ploomesServices->createNewListTag($json);

        //         $data['Lists'] = [
        //             [
        //                 'ListId'=> $nMarcador['Id'],
        //                 'ProductId'=> $pProduct['Id']
        //             ]
        //         ];
        //     }
        // }

        // $pProduct = $ploomesServices->getProductByCode($product->codigo);
        
        // if(!$pProduct){
        $op = CustomFieldsFunction::createOtherPropertiesByEntity($custom['Produto'], $product);
        $data['OtherProperties'] = $op;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        return $json;

    }


    //Serviços
    //cria um objeto do webhook vindo do omie para enviar ao ploomes
    public function createObjectCrmServiceFromErpData($args, $ploomesServices)
    {
        $decoded = $args['body'];
        //achata o array multidimensional decoded em um array simples
        $array = DiverseFunctions::achatarArray($decoded);
        //cria o objeto de produtos
        $service = new stdClass();

        $omieApp = $this->omieServices->getOmieApp();

        $chave = 'idProductOmie' . $omieApp['app_name'];
        $service->$chave = $array['event_intListar_nCodServ'];
       
        $service->appKey = $array['appKey'];
        $service->appSecret = $omieApp['app_secret'];
        $pFamily = explode('.' , $array['topic']);
        $nFamily = $pFamily[0].'s';//o s é para o topic Produto ficar no plural
        $nameFamily= str_replace('c', 'ç', $nFamily);

        $verifyFamily = $ploomesServices->getFamilyByName($nFamily);

        if(isset($verifyFamily['Id'])){
            $service->idFamily =  $verifyFamily['Id'];
        }else{
            $createFamily = $ploomesServices->createNewFamily($nameFamily);
            $service->idFamily =  $createFamily['Id'];
        }        
        $verifyGroup = $ploomesServices->getGroupByName($nFamily);
        if(isset($verifyGroup['Id'])){
            $service->idGroup =  $verifyGroup['Id'];
        }else{
            $createGroup = $ploomesServices->createNewGroup($nameFamily, $service->idFamily);
            $service->idGroup =  $createGroup['Id'];
        }
       
        $service->messageId = $array['messageId'];
        $service->topic = $array['topic'];
        $service->codLC116 = $array['event_cabecalho_cCodLC116'];
        $service->codServMun = $array['event_cabecalho_cCodServMun'];
        $service->codigo = $array['event_cabecalho_cCodigo'];
        $service->descricao = $array['event_cabecalho_cDescricao'];
        $service->idTrib = $array['event_cabecalho_cIdTrib'];
        $service->idNBS = $array['event_cabecalho_nIdNBS'];
        $service->precoUnit = $array['event_cabecalho_nPrecoUnit'];
        $service->descrCompleta = $array['event_descricao_cDescrCompleta'];
        $service->retCOFINS = $array['event_impostos_cRetCOFINS'];
        $service->retCSLL = $array['event_impostos_cRetCSLL'];
        $service->retINSS = $array['event_impostos_cRetINSS'];
        $service->retIR = $array['event_impostos_cRetIR'];
        $service->retISS = $array['event_impostos_cRetISS'];
        $service->retPIS = $array['event_impostos_cRetPIS'];
        $service->aliqCOFINS = $array['event_impostos_nAliqCOFINS'];
        $service->aliqCSLL = $array['event_impostos_nAliqCSLL'];
        $service->aliqINSS = $array['event_impostos_nAliqINSS'];
        $service->aliqIR = $array['event_impostos_nAliqIR'];
        $service->aliqISS = $array['event_impostos_nAliqISS'];
        $service->aliqPIS = $array['event_impostos_nAliqPIS'];
        $service->redBaseINSS = $array['event_impostos_nRedBaseINSS'];
        $service->impAPI = $array['event_info_cImpAPI'];
        $service->inativo = $array['event_info_cInativo'];
        $service->dAlt = $array['event_info_dAlt'];
        $service->dInc = $array['event_info_dInc'];
        $service->hAlt = $array['event_info_hAlt'];
        $service->hInc = $array['event_info_hInc'];
        $service->uAlt = $array['event_info_uAlt'];
        $service->uInc = $array['event_info_uInc'];
        $service->codIntServ = $array['event_intListar_cCodIntServ'];
        $service->codServ = $array['event_intListar_nCodServ'];
        $service->author_email = $array['author_email'];
        $service->author_name = $array['author_name'];
        $service->author_userId = $array['author_userId'];
        $service->appKey = $array['appKey'];
        $service->appHash = $array['appHash'];
        $service->origin = $array['origin'];    
 
        return $service;
    }

    // cria o objet e a requisição a ser enviada ao ploomes com o objeto do omie
    public function createCrmServiceFromErpObject($service, $ploomesServices)
    {
        $omie = new stdClass();
        $omieApp = $this->omieServices->getOmieApp();
        
        $omie->appName = $omieApp['app_name'];
        $omie->appSecret = $omieApp['app_secret'];
        $omie->appKey = $omieApp['app_key'];
        $omie->ncc = $omieApp['ncc'];
        $omie->tenancyId = $omieApp['tenancy_id'];
        $custom = $_SESSION['contact_custom_fields'][$omie->tenancyId];
        //cria o produto formato ploomes 
        $data = [];
        $data['Name'] = $service->descricao;
        $data['GroupId'] = $service->idGroup;
        $data['FamilyId'] = $service->idFamily;
        $data['Code'] = $service->codigo;
        //$data['ImageUrl'] = $service->endereco ?? null;
        $data['UnitPrice'] = $service->precoUnit ?? null;
        // $data['CreateImportId'] = $city['Id'];//pegar na api do ploomes
        // $data['UpdateImportId'] = $service->segmento ?? null;//Id do Tipo de atividade(não veio no webhook de cadastro do omie)
        // $data['Editable'] = $service->nFuncionarios ?? null;//Id do número de funcionários(não veio no webhook de cadastro do omie)
        // $data['Deletable'] = $service->cVendedorPloomes ?? null;//Id do vendedor padrão(comparar api ploomes)
        // $data['Suspended'] = $service->observacao ?? null;
        // $data['CreatorId'] = $service->email ?? null;
        // $data['UpdaterId'] = $service->homepage ?? null;
        // $data['CreateDate'] = $service->cnae ?? null;
        // $data['LastUpdateDate'] = $service->codigoClienteOmie ?? null;//chave externa do cliente(código Omie)
        //$data['ImportationIdCreate'] = $service->latitude ?? null;(inexistente no omie)
        //$data['ImportationIdUpdate'] = $service->longitude ?? null;(inexistente no omie)
        $op = CustomFieldsFunction::createOtherPropertiesByEntity($custom['Produto'], $service);
       
        // $ncm = [
        //     'FieldKey'=> 'product_15405B03-AA47-4921-BC83-E358501C3227',
        //     'StringValue'=>$service->ncm ?? null,
        // ];
        // $marca = [
        //     'FieldKey'=>'product_4C2CCB79-448F-49CF-B27A-822DA762BE5E',
        //     'StringValue'=>$service->marca ?? null,
        // ];

        // $modelo = [
        //     'FieldKey'=>'product_A92259E5-1E19-44AC-B781-CB908F5602EC',
        //     'StringValue'=>$service->modelo ?? null,
        // ];
        // $descDetalhada = [
        //     'FieldKey'=>'product_F48280B4-688C-4346-833C-03E28991564C',
        //     'BigStringValue'=>$service->descrCompleta ?? null,
        // ];
        // $obsInternas = [
        //     'FieldKey'=>'product_5FB6D80C-CB90-4A46-95BD-1A18141FBC46',
        //     'BigStringValue'=>$service->nCodServ ?? null,
        // ];
        // $categoria = [
        //     'FieldKey'=>'product_44CCBB11-CD81-439A-8304-921C2E39C25D',
        //     'StringValue'=>$service->codigo_familia ?? null,
        // ];

        // $op[] = $descDetalhada;
        // $op[] = $obsInternas;
        // $op[] = $cOmie;
        // $op[] = $categoria;
   
        $data['OtherProperties'] = $op;
      
        $json = json_encode($data);

        return $json;

    }
}