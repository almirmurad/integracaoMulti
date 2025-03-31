<?php

namespace src\formatters;

use src\contracts\ErpFormattersInterface;
use src\exceptions\PedidoInexistenteException;
use src\functions\CustomFieldsFunction;
use src\functions\DiverseFunctions;
use src\services\OmieServices;
use stdClass;

Class OmieFormatter implements ErpFormattersInterface{

    private object $omieServices;

    public function __construct($appk, $omieBases)
    {
        $this->omieServices = new OmieServices($appk, $omieBases);
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
        $informacoes_adicionais['codVend']= $order->codVendedorOmie ?? null;
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

    //cadastra obj cliente com dados vindos do erp para enviar ao crm
    public function createClientErpToCrmObj(array $args):object
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
        //$data['RoleId'] = $contact->cargo ?? null;//Id do cargo do cliente(inexistente no omie)
        //$data['DepartmentId'] = $contact->departamento ?? null;//Id do departamento do cliente(inexistente no omie)
        //$data['Skype'] = $contact->skype ?? null;//Skype do cliente(inexistente no omie)
        //$data['Facebook'] = $contact->facebook ?? null;//Facebook do cliente(inexistente no omie)
        //$data['ForeignZipCode'] = $contact->cepInternacional ?? null;//(inexistente no omie)
        //$data['CurrencyId'] = $contact->moeda ?? null;//(inexistente no omie)
        //$data['EmailMarketing'] = $contact->marketing ?? null;//(inexistente no omie)
        $data['CNAECode'] = $contact->cnae ?? null;
        //$data['Latitude'] = $contact->latitude ?? null;(inexistente no omie)
        //$data['Longitude'] = $contact->longitude ?? null;(inexistente no omie)
        $data['Key'] = $contact->codigoClienteOmie ?? null;//chave externa do cliente(código Omie)
        //$data['AvatarUrl'] = $contact->avatar ?? null;(inexistente no omie)
        //$data['IdentityDocument'] = $contact->exterior ?? null;//(documento internacional exterior)
        //$data['CNAEName'] = $contact->cnaeName ?? null;(inexistente no omie)
        // $person = [];
        // $person['id'] = '';
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
        $op = [];
        if(!empty($contact->tipoAtividade)){
            
            $id = match($contact->tipoAtividade){  
                '0'=>420130572,
                '1'=>420130568,
                '2'=>420130574,
                '3'=>420130569,
                '4'=>420130565,
                '5'=>420130566,           
                '6'=>420130571,
                '7'=>420130570,
                '8'=>420130563,
                '9'=>420130573,
            };
        }
        
        $tags = [];
        $tag = [];
        if(isset($contact->tags) && !empty($contact->tags)){
            
            foreach($contact->tags as $t)
            {
                $idTag = match($t['tag']){
                    "Fornecedor"=>40203491,         
                    "Transportadora"=>40203492,
                    "Funcionário"=>40203493,               
                    "Min. da Fazenda"=>40203494,
                    "Banco e Inst. Financeiras"=>40203495,
                    "Diretor"=>40203497,
                    "Cliente"=>40203778,
                    "GAMATERMIC"=>40203778,
                };
                
                $tag['TagId'] = $idTag;
                $tag['Tag']['Name'] = $t['tag'];
                
                $tags[]=$tag;
            }
            $data['Tags'] = $tags;
        }else{
            $data['Tags'] = null;
        }     
        

        $custom = $_SESSION['contact_custom_fields'][$omie->tenancyId];
         
        $op = CustomFieldsFunction::createOtherPropertiesByEntity($custom['Cliente'], $contact);
        // print_r($op);
        // exit;
        
        // $tipo = [
        //     'FieldKey'=>'contact_879A3AA2-57B1-49DC-AEC2-21FE89617665',
        //     'IntegerValue'=>409150910,//pessoa juridica
        // ];
        // $porte = [
        //     'FieldKey'=>'contact_FA99392B-CED8-4668-B003-DFC1111DACB0',
        //     'IntegerValue'=>'',//pequeno, medio, grande
        // ];
        // $importancia = [
        //     'FieldKey'=>'contact_20B72360-82CF-4806-BB05-21E89D5C61FD',
        //     'IntegerValue'=>409150919,//alta
        // ];
        // $situacao = [
            //     'FieldKey'=>'contact_5F52472B-E311-4574-96E2-3181EADFAFBE',
            //     'IntegerValue'=>409150897,//ativo???
            // ];
            // $cicloCompra = [
        //     'FieldKey'=>'contact_9E595E72-E50C-4E95-9A05-D3B024C177AD',
        //     'StringValue'=>'',
        // ];
        // $inscEstadual = [
        //     'FieldKey'=>'contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB',
        //     'StringValue'=>$contact->inscricaoEstadual ?? null,
        // ];
        // $inscMunicipal = [
        //     'FieldKey'=>'contact_D21FAEED-75B2-40E4-B169-503131EB3609',
        //     'StringValue'=>$contact->inscricaoMunicipal ?? null,
        // ];
        // $inscSuframa = [
        //     'FieldKey'=>'contact_3094AFFE-4263-43B6-A14B-8B0708CA1160',
        //     'StringValue'=>$contact->inscricaoSuframa ?? null, 
        // ];
        // $simplesNacional = [
        //     'FieldKey'=>'contact_9BB527FD-8277-4D1F-AF99-DD88D5064719',
        //     'BoolValue'=>(isset($contact->simplesNacional) && $contact->simplesNacional === 'S') ? $contact->simplesNacional = true : $contact->simplesNacional = false,
        // ];
        // $contato1 = [
        //     'FieldKey'=>'contact_3C521209-46BD-4EA5-9F41-34756621CCB4',
        //     'StringValue'=>$contact->contato ?? null,
        // ];
        // $prodRural = [
        //     'FieldKey'=>'contact_F9B60153-6BDF-4040-9C3A-E23B1469894A',
        //     'BoolValue'=>(isset($contact->produtorRural) && $contact->produtorRural === 'S') ? $contact->produtorRural = true : $contact->produtorRural = false,
        // ];
        // $contribuinte = [
        //     'FieldKey'=>'contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453',
        //     'BoolValue'=>(isset($contact->contribuinte) && $contact->contribuinte === 'S') ? $contact->contribuinte = true : $contact->contribuinte = false,
        // ];
        // $limiteCredito = [
        //     'FieldKey'=>'contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD',
        //     'DecimalValue'=>$contact->limiteCredito ?? null,
        // ];
        // $inativo = [
        //     'FieldKey'=>'contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4',
        //     'BoolValue'=>(isset($contact->inativo) && $contact->inativo === 'S') ? $contact->inativo = true : $contact->inativo = false,
        // ];
        // $bloqExclusao = [
        //     'FieldKey'=>'contact_C613A391-155B-42F5-9C92-20C3371CC3DE',
        //     'BoolValue'=>(isset($contact->bloquearExclusao) && $contact->bloquearExclusao === 'S') ? $contact->bloquearExclusao = true : $contact->bloquearExclusao = false,
        // ];
        // $transpPadrao = [
        //     'FieldKey'=>'contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33',
        //     //'IntegerValue'=>'',
        //     'StringValue'=>$contact->idTranspPadrao ?? null
        // ];
        // $idTranspPadrao = [
        //     'FieldKey'=>'contact_3E075EA9-320C-479E-A956-5A3734C55E51',
        //     //'IntegerValue'=>'',
        //     'IntegerValue'=>$contact->idTranspPadraoPloomes ?? null
        // ];
        // $cBanco = [
        //     'FieldKey'=>'contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9',
        //     'StringValue'=>$contact->cBanco ?? null,
        // ];
        // $agencia = [
        //     'FieldKey'=>'contact_1F1E1F00-34CB-4356-B852-496D62A90E10',
        //     'StringValue'=>$contact->agencia ?? null,
        // ];
        // $nContaCorrente = [
        //     'FieldKey'=>'contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80',
        //     'StringValue'=>$contact->nContaCorrente ?? null,
        // ];
        // $docTitular = [
        //     'FieldKey'=>'contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50',
        //     'StringValue'=>$contact->docTitular ?? null,
        // ];
        // $nomeTitular = [
        //     'FieldKey'=>'contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066',
        //     'StringValue'=>$contact->nomeTitular ?? null,
        // ];
        // $cli = $this->omieServices->getClientById($contact);
        // $chavePix = [
        //     'FieldKey'=>'contact_847FE760-74D0-462D-B464-9E89C7E1C28E',
        //     'StringValue'=>$cli['dadosBancarios']['cChavePix'] ?? null,
        // ];
        // $transferenciaPadrao = [
        //     'FieldKey'=>'contact_33015EDD-B3A7-464E-81D0-5F38D31F604A',
        //     'BoolValue'=>(isset($contact->transferenciaPadrao) && $contact->transferenciaPadrao === 'S') ? true : false,
        // ];

        //Abaixo tratamos dos campos que são indicativos de quais bases o cliente integra no erp.

        // switch($contact->appKey){

        //     case '1120581879417':
        //         $integrarBase1 = [
        //             'FieldKey'=>'contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C',
        //             'BoolValue'=> true,
        //         ];
        //         $op[] = $integrarBase1;
        //         break;
        //     case '146532853467':
        //         $integrarBase2 = [
        //             'FieldKey'=>'contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C',
        //             'BoolValue'=> true,
        //         ];
        //         $op[] = $integrarBase2;
        //         break;
        //     case '146571186762':
        //         $integrarBase3 = [
        //             'FieldKey'=>'contact_02AA406F-F955-4AE0-B380-B14301D1188B',
        //             'BoolValue'=>true,
        //         ];
        //         $op[] = $integrarBase3;
        //         break;
        //     case '171250162083':
        //         $integrarBase4 = [
        //             'FieldKey'=>'contact_E497C521-4275-48E7-B44E-7A057844B045',
        //             'BoolValue'=>true,
        //         ];
        //         $op[] = $integrarBase4;
        //         break;
        // }
        //no caso de der certo funções personalizadas então não precisa mais do array abaixo
        // $op[] = $ramo;
        // $op[] = $tipo;
        // $op[] = $importancia;
        // // $op[] = $situacao;
        // $op[] = $inscEstadual;
        // $op[] = $inscMunicipal;
        // $op[] = $inscSuframa;
        // $op[] = $simplesNacional;
        // $op[] = $contato1;
        // $op[] = $prodRural;
        // $op[] = $contribuinte;
        // $op[] = $limiteCredito;
        // $op[] = $inativo;
        // $op[] = $bloqExclusao;
        // $op[] = $transpPadrao;
        // $op[] = $idTranspPadrao;
        // $op[] = $cBanco;
        // $op[] = $agencia;
        // $op[] = $nContaCorrente;
        // $op[] = $docTitular;
        // $op[] = $nomeTitular;
        // $op[] = $cOmie ?? null;
        // $op[] = $chavePix;
        // $op[] = $transferenciaPadrao;
        
        $data['OtherProperties'] = $op;
        
        $json = json_encode($data,JSON_UNESCAPED_UNICODE);
     
        return $json;

    }

    // createContactObjFromPloomesCrm - cria obj cliente vindo do Ploomes ao ERP Omie
    public function createContactObjFromPloomesCrm(array $args, object $ploomesServices):object
    {
        $decoded = $args['body'];
        $cliente = $ploomesServices->getClientById($decoded['New']['Id']);
        
        $contact = new stdClass(); 
        
        $custom = CustomFieldsFunction::compareCustomFields($decoded['New']['OtherProperties'],$args['Tenancy']['tenancies']['id'],'Cliente');
    
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
        $ramo= $prop['contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227'] ?? null;
        
        if($ramo !== null){
            $id = match($ramo){
                
                420130572=>0,
                420130568=>1,
                420130574=>2,
                420130569=>3,
                420130565=>4,
                420130566=>5,           
                420130571=>6,
                420130570=>7,
                420130563=>8,
                420130573=>9,
    
            };
            $contact->ramoAtividade = $id;
        }else{
            $contact->ramoAtividade = null;
        }
        //contact_FA99392B-CED8-4668-B003-DFC1111DACB0 = Porte
        $contact->porte = $prop['contact_FA99392B-CED8-4668-B003-DFC1111DACB0'] ?? null;
        //contact_20B72360-82CF-4806-BB05-21E89D5C61FD = importância
        $contact->importancia = $prop['contact_20B72360-82CF-4806-BB05-21E89D5C61FD'] ?? null;
        //contact_5F52472B-E311-4574-96E2-3181EADFAFBE = situação
        $contact->situacao = $prop['contact_5F52472B-E311-4574-96E2-3181EADFAFBE'] ?? null;
        //contact_9E595E72-E50C-4E95-9A05-D3B024C177AD = ciclo de compra
        $contact->cicloCompra = $prop['contact_9E595E72-E50C-4E95-9A05-D3B024C177AD'] ?? null;
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
        $contact->documentoExterior = $cliente['IdentityDocument'] ?? null; // Documento extrangeiro CPF
        $contact->segmento = $cliente['LineOfBusiness']['Id'] ?? null; // Segmento CPF
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
        foreach($args['Tenancy']['omie_bases'] as $base)
        {   
            $name = strtolower($base['app_name']);
            $chaveId = "bicorp_api_id_cliente_omie_{$name}_out";
  
            $contact->codOmie[] = $custom[$chaveId] ?? null;           
        
            $chave = "bicorp_api_integrar_base_{$name}_out";
            
            if(isset($custom[$chave]) && $custom[$chave] !== false){
                $base['SendExternalKey'] = $chave;
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

    // updateContactCRMToERP - atualiza um contato do CRM para o ERP
    public function updateContactCRMToERP(object $contact, object $ploomesServices):array
    {
   
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        $total = 0;
        $current = date('d/m/Y H:i:s');
        if(!empty($contact)){
            
            foreach($contact->basesFaturamento as $k => $bf)
            {
                $omie[$k] = new stdClass();
                    if(isset($bf['integrar']) && $bf['integrar']  > 0){
                        $total ++;
                        $omie[$k]->baseFaturamentoTitle = $bf['app_name'];
                        // $omie[$k]->target = $bf['sigla']; 
                        $omie[$k]->appSecret = $bf['app_secret'];
                        $omie[$k]->appKey = $bf['app_key'];
                        
                        $contact->idIntegracao = $contact->id;
                        $contact->idOmie = $contact->codOmie[$k];
                        $contact->cVendedorOmie = (isset($contact->ownerEmail) && $contact->ownerEmail !== null) ? $this->omieServices->vendedorIdOmie($omie[$k],$contact->ownerEmail) : null;
                        $contact->cTransportadora = $contact->cTranspOmie[$k] ?? null;
                        // $alterar = $this->omieServices->alteraCliente($omie[$k], $diff);
                      
                        $alterar = $this->omieServices->alteraClienteCRMToERP($omie[$k], $contact);

                        //verifica se criou o cliente no omie
                        if (isset($alterar['codigo_status']) && $alterar['codigo_status'] == "0") 
                        {
                            $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' alterado no Omie ERP ('.$omie[$k]->baseFaturamentoTitle.') com o numero: '.$alterar['codigo_cliente_omie'].' e mensagem enviada com sucesso em: '.$current;

                            $messages['success'][] = $message;

                            //monta a mensagem para atualizar o cliente do ploomes
                            // $msg=[
                            // 'ContactId' => $contact->id,
                            //     'Content' => 'Cliente '.$contact->name.' alterado no OMIE via API BICORP na base '.$omie[$k]->baseFaturamentoTitle,
                            //     'Title' => 'Pedido Criado'
                            // ];
                            
                            // //cria uma interação no card
                            // ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' alterado no Omie ERP ('.$omie[$k]->baseFaturamentoTitle.') com o numero: '.$alterar['codigo_cliente_omie'].' e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' alterado no Omie ERP com o numero: '.$alterar['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;

                            //aqui atualizaria a base de dados com sql de update
                        
                            // $messages['success'][] = $message;
                            
                        }else{
                           
                            //monta a mensagem para atualizar o card do ploomes
                            $msg=[
                                'ContactId' => $contact->id,
                                'Content' => 'Erro ao alterar cliente no Omie: '. $alterar['faultstring'].' na base '.$omie[$k]->baseFaturamentoTitle.' Data = '.$current,
                                'Title' => 'Erro ao alterar cliente'
                            ];
                            //cria uma interação no card
                            ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Erro ao alterar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $alterar['faultstring'].' Data = '.$current: $message = 'Erro ao alterar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $alterar['faultstring'].' e erro ao enviar mensagem no card do cliente do Ploomes Data = '.$current;
                            $messages['error'][]=$message;
                        }       
                    }else{
                        $messages['error'][]='Base de faturamento ['.$bf['app_name'].'], não selecionada para integração';
                    }
            }   
        }else{
            $messages['error'][]='Esta alteração já foi feita';
        }

        return $messages;       
    }

    // createContact - cria um contato no CRM envia ao ERP
    public function createContact(object $contact, object $ploomesServices):array
    {
        
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        
        $current = date('d/m/Y H:i:s');
        
        foreach($contact->basesFaturamento as $k => $bf)
        {
            
            $omie[$k] = new stdClass();
            
            if(isset($bf['integrar']) &&  $bf['integrar'] > 0){
                $omie[$k]->baseFaturamentoTitle = $bf['app_name'];
                // $omie[$k]->target = $bf['sigla']; 
                $omie[$k]->appSecret = $bf['app_secret'];
                $omie[$k]->appKey = $bf['app_key'];
                $contact->cVendedorOmie = $this->omieServices->vendedorIdOmie($omie[$k],$contact->ownerEmail) ?? null; 
                $contact->idTransportadora = $this->omieServices->getShipping($omie[$k], $contact);
                $criaClienteOmie = $this->omieServices->criaClienteOmie($omie[$k], $contact);

                //verifica se criou o cliente no omie
                if (isset($criaClienteOmie['codigo_status']) && $criaClienteOmie['codigo_status'] == "0") {
                    $match = match ($k) {
                         0=> 'contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3',
                         1=> 'contact_6DB7009F-1E58-4871-B1E6-65534737C1D0',
                         2=> 'contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2',
                         3=> 'contact_07784D81-18E1-42DC-9937-AB37434176FB',
                    };
                    $codigoOmie = $criaClienteOmie['codigo_cliente_omie'];
                    $array = [
                        'TypeId'=>1,
                        'OtherProperties'=>[
                            [
                                'FieldKey'=>$match,
                                'StringValue'=>"$codigoOmie",
                            ]
                        ]
                    ];
                    $json = json_encode($array);
                    
                    //insere o id do omie no campo correspondente do cliente Ploomes
                    $ploomesServices->updatePloomesContact($json, $contact->id);
                    
                    $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o numero: '.$criaClienteOmie['codigo_cliente_omie'].' em: '.$current;
                    
                    $messages['success'][]=$message;

                    // $insertIdOmie = $ploomesServices->updatePloomesContact($json, $contact->id);
                    //if($insertIdOmie){

                        
                        //monta a mensagem para atualizar o cliente do ploomes
                        // $msg=[
                        //     'ContactId' => $contact->id,
                        //     'Content' => 'Cliente '.$contact->name.' criada no OMIE via API BICORP na base '.$omie[$k]->baseFaturamentoTitle,
                        //     'Title' => 'Pedido Criado'
                        // ];
                        
                        // //cria uma interação no card
                        // ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o numero: '.$criaClienteOmie['codigo_cliente_omie'].' e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o numero: '.$criaClienteOmie['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;

                    //}
                    
                    // $messages['success'][]=$message;
                    
                }else{
                    //monta a mensagem para atualizar o card do ploomes
                    $msg=[
                        'ContactId' => $contact->id,
                        'Content' => 'Erro ao gravar cliente no Omie: '. $criaClienteOmie['faultstring'].' na base '.$omie[$k]->baseFaturamentoTitle.' Data = '.$current,
                        'Title' => 'Erro ao Gravar cliente'
                    ];
                    
                    //cria uma interação no card
                    ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Erro ao gravar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $criaClienteOmie['faultstring'].' Data = '.$current: $message = 'Erro ao gravar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $criaClienteOmie['faultstring'].' e erro ao enviar mensagem no card do cliente do Ploomes Data = '.$current;

                    $messages['error'][]=$message;
                }       
            }
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

}



