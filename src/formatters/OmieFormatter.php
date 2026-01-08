<?php

namespace src\formatters;

use DateTime;
use GrahamCampbell\ResultType\Success;
use src\contracts\ErpFormattersInterface;
use src\exceptions\PedidoInexistenteException;
use src\exceptions\WebhookReadErrorException;
use src\functions\CustomFieldsFunction;
use src\functions\DiverseFunctions;
use src\models\Contact;
use src\services\OmieServices;
use src\services\PloomesServices;
use stdClass;

Class OmieFormatter implements ErpFormattersInterface{

    private object $omieServices;
    public mixed $current;    

    public function __construct($appk, $omieBases)
    {        
        $this->omieServices = new OmieServices($appk, $omieBases);
        $this->current = date('d/m/Y H:i:s');
    }

    public function detectLoop(array $args):bool
    {
        
        if($args['body']['author']['name'] === 'Integração' || $args['body']['author']['email'] === 'no-reply@omie.com.br' ){
            throw new WebhookReadErrorException('Dados de retorno da última integração', 500);
        }

        return true;
    }

    //order
    public function distinctProductsServicesFromOmieOrders(array $orderArray, array $arrayIsServices, string $idItemOmie, object $order):array
    { 
        // print_r($orderArray);
        // exit;
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
                throw new WebhookReadErrorException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado o id do produto  Omie para o aplicativo de faturamento escolhido no pedido.', 500);
            }

            //verifica se é venda de serviço 
            if($arrayIsServices['isService'] || $arrayIsServices['isRecurrence']){
                // print'aqui';
                // exit;
               //retorna o modelo de serviço para o erp de destino 
               $contentServices[] = $this->getOrdersServicesItens($prdItem, $opItem[$idItemOmie], $order, $arrayIsServices['isRecurrence']);
            
            }else{
             
                $productsOrder[] = $this->getOrdersProductsItens($prdItem, $opItem[$idItemOmie], $order);
               
            }
        }

        // print_r($contentServices);
        // print_r($productsOrder);
        // exit;

        return ['products'=>$productsOrder, 'services'=>$contentServices];
    }

    public function createOrder(object $order, object $omie):string
    {        
        // print_r($order);exit;
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
        $cabecalho['codigo_cenario_impostos'] = $order->codCenarioFiscal ?? null;//5329821555;//cenario de fiscal (impostos)
        
    
        //frete
        $frete = [];//array com infos do frete, por exemplo, modailidade;
        $frete['modalidade'] = $order->modalidadeFrete ?? null;//string
        $frete['previsao_entrega'] = DiverseFunctions::convertDate($order->previsaoEntrega);
    
        //informações adicionais
        $informacoes_adicionais = []; //informações adicionais por exemplo codigo_categoria = 1.01.03, codigo_conta_corrente = 123456789
        //enderço de entrega do pedido de venda
        $outros_detalhes = [];
        $outros_detalhes['cCnpjCpfOd'] = $order->docRecebedorEnderecoEntrega;
        $outros_detalhes['cNomeOd'] = $order->nomeEnderecoEntrega;
        $outros_detalhes['cInscrEstadualOd'] = $order->ieEnderecoEntrega;
        $outros_detalhes['cEnderecoOd'] = $order->enderecoEnderecoEntrega;
        $outros_detalhes['cNumeroOd'] = $order->numeroEnderecoEntrega;
        $outros_detalhes['cComplementoOd'] = $order->complementoEnderecoEntrega;
        $outros_detalhes['cBairroOd'] = $order->bairroEnderecoEntrega;
        $outros_detalhes['cEstadoOd'] = $order->ufEnderecoEntrega;
        $outros_detalhes['cCidadeOd'] = $order->cidadeEnderecoEntrega;
        $outros_detalhes['cCEPOd'] = $order->cepEnderecoEntrega;
        $outros_detalhes['cTelefoneOd'] = $order->telefoneEnderecoEntrega;

        $informacoes_adicionais['codigo_categoria'] = $order->codigoCategoriaVenda;//string
        $informacoes_adicionais['codigo_conta_corrente'] = $omie->ncc;//int
        $informacoes_adicionais['numero_pedido_cliente']= $order->numPedidoCliente ?? "0";
        $informacoes_adicionais['codVend']= $order->codVendedorErp ?? null;
        $informacoes_adicionais['codproj']= $order->codProjeto ?? null;
        $informacoes_adicionais['dados_adicionais_nf'] = $order->notes;
        $informacoes_adicionais['outros_detalhes'] = $outros_detalhes;
    
        //observbacoes
        $observacoes =[];
        $observacoes['obs_venda'] = $order->description;
    
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
                'app_key' => $omie->appKey,
                'app_secret' => $omie->appSecret,
                'call' => 'IncluirPedido',
                'param'=> [$newPedido],
            ];

            return json_encode($array, JSON_UNESCAPED_UNICODE);       

        }else{
            throw new WebhookReadErrorException('Erro ao montar o pedido para enviar ao Omie: Faltaram dados para montar a estrutura do pedido',500);
        }
    
    }

    public function createContract(object $order, object $omie):string
    {        
        // print 'aqui em contract';
        // exit;
        // print_r($order);
        // exit;
        // cabecalho
        $cabecalho = [];//cabeçalho do pedido (array)
        $cabecalho['cCodIntCtr'] = 'VEN_CONT/'.$order->id;//string
        $cabecalho['nCodCli'] = $order->idClienteOmie;//int
        $cabecalho['cNumCtr'] = $order->numContrato;//string
        $cabecalho['cCodSit'] = $order->sitContrato ?? 10;//string'qtde_parcela'=>2
        $cabecalho['dVigInicial'] = $order->inicioVigencia;//string
        $cabecalho['dVigFinal'] = $order->fimVigencia;//string
        $cabecalho['nDiaFat'] = $order->diaFaturamento;//string
        $cabecalho['cTipoFat'] = $order->tipoFaturamento;//string
        
        //departamentos
        //precisamos entender se o depto vai vir um só ou mais de um, se vier mais de um precisa fazer o calculo para gerar os valores para inserir 
        $departamentos = [];//array com infos do frete, por exemplo, modailidade;
        if(isset($order->codigoDepartamento) && !empty($order->codigoDepartamento)){
           // $depto = $this->omieServices->buscaDeptoByCode($omie, $order->codigoDepartamento);
            $arrayDepto = [
                'cCodDep'=>$order->codigoDepartamento,
                'nPerDep'=>100,
                'nValDep'=>$order->amount,
                // 'nValorFixo'=>'S'
            ];
            
            // cCodDepto	string40	ID do Departamento.
            // nPerc	decimal	Percentual de Rateio.
            // nValor	decimal	Valor do Rateio.
            // nValorFixo	string1	Indica que o valor foi fixado na distribuição do rateio.
            // Depar
            $departamentos[] = $arrayDepto;

        } 
        
        $emailCliente = [];
        $emailCliente['cEnviarBoleto'] = $order->enviaBoleto;
        $emailCliente['cEnviarLinkNfse'] = 'S';
        // $emailCliente['cEnviarRecibo'] = 'S';
        $emailCliente['cUtilizarEmails'] = $order->emailNF;
       
        //informações adicionais
        $infAdic = []; //informações adicionais por exemplo codigo_categoria = 1.01.02, codigo_conta_corrente = 123456789
        $infAdic['cCidPrestServ'] = $order->cidadeServico;//int
        $infAdic['cCodART'] = '';//string
        $infAdic['cCodCateg'] = $order->codigoCategoriaVenda ??  '1.01.02';//string
        $infAdic['cCodObra'] = '';
        $infAdic['cContato'] = '';
        $infAdic['cDadosAdicNF']= $order->observacoesOS;
        $infAdic['nCodCC']= $omie->ncc;
        $infAdic['nCodProj'] = $order->codProjeto;
        $infAdic['nCodVend']= $order->codVendedorErp;

        //itens do contrato
        $itensContrato = [];
        $prodUti = [];
        
        foreach($order->contentOrder as $service){

            foreach($service['pu'] as $prdUtl){
                $prodUti[] = $prdUtl;
            }
            
            $descricaoServico = $service['desc'];
            unset($service['pu'],$service['desc']);
                   
            // $itemCabecalho['codIntItem'] = 1;
            // $itemCabecalho['cCodCategItem'] = 1;
            // $itemCabecalho['cNaoGerarFinanceiro'] = 1;
            // $itemCabecalho['codLC116'] = 1;
            // $itemCabecalho['codNBS'] = 1;
            // $itemCabecalho['codServMunic'] = 1;
            //$itemCabecalho['codServico'] = 1;
            // $itemCabecalho['natOperacao'] = 1;
            // $itemCabecalho['quant'] = 1;
            // $itemCabecalho['seq'] = 1;
            // $itemCabecalho['valorDed'] = 1;
            // $itemCabecalho['valorTotal'] = 1;
            // $itemCabecalho['valorUnit'] = 1;
    
            $it['itemCabecalho'] = $service;
            //descricao do item
            $itemDescrServ= [
                'descrCompleta' => $descricaoServico
            ];
            
            $it['itemDescrServ'] = $itemDescrServ;
            $itensContrato[] = $it;
        }

        $pu = [];
        $pu['cAcaoProdUtilizados'] = 'EST';
        $pu['produtoUtilizado'] = $prodUti;

        //observbacoes
        $observacoes =[];
        $observacoes['cObsContrato'] = $order->notes;

        $vencTextos = [
            'cAdContrato'=>'S',//perguntar 
            'cAdPeriodo'=>'N',//perguntar
            'cAdVenc'=>'',//perguntar
            'cAntecipar'=>'N',
            'cCodPerRef'=>'001',//perguntar
            'cDiaFim'=>0,
            'cDiaIni'=>0,
            'cPostergar'=>'',
            'cProxMes'=>'',
            'cTpVenc'=>$order->codTipoVencimento ?? '001',
            'nDiaFixo'=>$order->diaFixoVencCont,
            'nDias'=>$order->numDiasVencimCont,
        ];
    
        $newContract = [];//array que engloba tudo
        $newContract['cabecalho'] = $cabecalho;
        $newContract['departamentos'] = $departamentos;
        $newContract['emailCliente'] = $emailCliente;
        $newContract['infAdic'] = $infAdic;
        $newContract['itensContrato'] = $itensContrato;
        $newContract['observacoes'] = $observacoes;
        $newContract['vencTextos'] = $vencTextos;
        $newContract['produtosUtilizados'] = $pu;

        //$newContract['lista_parcelas'] = $lista_parcelas;
        //$newContract['observacoes'] = $observacoes;
    
        if(
            !empty($newContract['cabecalho']) || !empty($newContract['infAdic']) ||
            !empty($newContract['itensContrato']) || !empty($newContract['observacoes']) ||
            !empty($newContract['vencTextos'])
        )
        {
            $array = [
                'app_key' => $omie->appKey,
                'app_secret' => $omie->appSecret,
                'call' => 'UpsertContrato',
                'param'=> [$newContract],
            ];

            return json_encode($array, JSON_UNESCAPED_UNICODE);       

        }else{
            throw new WebhookReadErrorException('Erro ao montar o pedido para enviar ao Omie: Faltaram dados para montar a estrutura do pedido',500);
        }
    
    }

    public function createOs(object $os, object $omie):string
    {
        $cabecalho = [];//cabeçalho do pedido (array)
        $cabecalho['nCodCli'] = $os->idClienteOmie;//int
        $cabecalho['cCodIntOS'] = 'VEN_SRV/'.$os->id;//string
        $cabecalho['dDtPrevisao'] = DiverseFunctions::convertDate($os->previsaoFaturamento);//string
        $cabecalho['cEtapa'] = $os->codigoEtapa ?? '10';//string
        $cabecalho['cCodParc'] =  $os->idParcelamento ?? '000';//string'qtde_parcela'=>2
        $cabecalho['nQtdeParc'] = 3;//string'qtde_parcela'=>2
        $cabecalho['nCodVend'] = $os->codVendedorErp;//string'qtde_parcela'=>2
        $observacoes = [];
        $observacoes['cObsOS'] = $os->observacoesOS ?? null;

        $parcelas = [];
        $parcelas['dDtVenc'] = DiverseFunctions::convertDate($os->dataVencParc) ?? '20/01/2026';
        $parcelas['nParcela'] = 1;
        $parcelas['nValor'] = $os->amount;
        $parcelas['nPercentual'] = 100;

        $emailNF=[];
        $emailNF['cEnvBoleto'] = $os->enviaBoleto;
        $emailNF['cEnvLink'] = 'S';
        $emailNF['cEnviarPara'] = $os->emailNF;


        $InformacoesAdicionais = []; //informações adicionais por exemplo codigo_categoria = 1.01.02 p/ serviços
        $InformacoesAdicionais['cCodCateg'] = $os->codigoCategoriaVenda ?? '1.01.02';//string
        $InformacoesAdicionais['nCodCC'] = $omie->ncc;//int
        $InformacoesAdicionais['cDadosAdicNF'] = $os->notes;//string 
        $InformacoesAdicionais['cNumPedido']=$os->numPedidoCliente ?? "0";
        $InformacoesAdicionais['cNumContrato']= $os->numContrato ?? "0";
        
        $InformacoesAdicionais['nCodProj']= $os->codProjeto ?? null;
        $InformacoesAdicionais['cCidPrestServ']= $os->cidadeServico ?? null;
        $prodUti = [];
        $services = [];
//  print $os->codigoDepartamento;
        //departamentos
        //precisamos entender se o depto vai vir um só ou mais de um, se vier mais de um precisa fazer o calculo para gerar os valores para inserir 
        $departamentos = [];//array com infos do frete, por exemplo, modailidade;
        if(isset($os->codigoDepartamento) && !empty($os->codigoDepartamento)){
            // $depto = $this->omieServices->buscaDeptoByCode($omie, $os->codigoDepartamento);
            // print_r($depto);
            // exit;
            $arrayDepto = [
                'cCodDepto'=>$os->codigoDepartamento,
                'nPerc'=>100,
                'nValor'=>$os->amount,
                'nValorFixo'=>'S'
            ];
            
            // cCodDepto	string40	ID do Departamento.
            // nPerc	decimal	Percentual de Rateio.
            // nValor	decimal	Valor do Rateio.
            // nValorFixo	string1	Indica que o valor foi fixado na distribuição do rateio.
            // Depar
            $departamentos[] = $arrayDepto;
        }  
        
        // print_r($departamentos);
        // exit;
    
        foreach($os->contentOrder as $service){
            if(isset($service['pu'])){

                foreach($service['pu'] as $prdUtl){
                    $prodUti[] = $prdUtl;
                }
                unset($service['pu']);
            }
            $services[] = $service;           
        }
  
        $pu = [];

        $pu['cAcaoProdUtilizados'] = 'EST';
        $pu['produtoUtilizado'] = $prodUti;
    
        $newOS = [];//array que engloba tudo
        $newOS['cabecalho'] = $cabecalho;
        $newOS['departamentos'] = $departamentos;
        $newOS['Email'] = $emailNF;
        $newOS['InformacoesAdicionais'] = $InformacoesAdicionais;
        $newOS['servicosPrestados'] = $services;
        $newOS['Parcelas'] = $parcelas;
        $newOS['produtosUtilizados'] = $pu;
        $newOS['observacoes'] = $observacoes;


        if( !empty($newOS['cabecalho']) || !empty($newOS['InformacoesAdicionais']) ||
            !empty($newOS['servicosPrestados']))
        {

            $array = [
                'app_key' => $omie->appKey,
                'app_secret' => $omie->appSecret,
                'call' => 'IncluirOS',
                'param'=> [$newOS],
            ];

            // print_r(json_encode($array, JSON_UNESCAPED_UNICODE));
            // exit;
            return json_encode($array, JSON_UNESCAPED_UNICODE);  

        }
        else
        {
            throw new WebhookReadErrorException('Erro ao montar a OS para enviar ao Omie: Faltaram dados para montar a estrutura da OS',500);
        }

    }

    public function createOrderErp(string $jsonPedido, array $arrayIsServices): array
    {   
        // print_r($jsonPedido);
        // print_r($arrayIsServices);
        // exit;
        if(($arrayIsServices['isService']) && ($arrayIsServices['isRecurrence'])){
            $url = 'https://app.omie.com.br/api/v1/servicos/contrato/';
        }elseif($arrayIsServices['isService'] && !$arrayIsServices['isRecurrence'] ){
            $url ='https://app.omie.com.br/api/v1/servicos/os/';
        }elseif($arrayIsServices['isRecurrence']){
            $url = 'https://app.omie.com.br/api/v1/servicos/contrato/';
        }else{
            $url = 'https://app.omie.com.br/api/v1/produtos/pedido/';
        }
     
        $createOrder = $this->omieServices->criaPedidoErp($jsonPedido, $url);

        if(isset($createOrder['codigo_status']) && $createOrder['codigo_status'] == "0")
        {
            $createOrder['create'] = true;
            $createOrder['num_pedido'] = $createOrder['numero_pedido'];            
        }elseif(isset($createOrder['cCodStatus']) && $createOrder['cCodStatus'] == "0"){
            $createOrder['create'] = true;
            $createOrder['num_pedido'] = $createOrder['cNumOS'] ?? $createOrder['nCodCtr'];
        }
        else{
            $createOrder['create'] = false;
        }

        return $createOrder;

    }

    public function buscaVendaOmie(object $omie, int $id, string $tipoVenda)
    {

      
        if(mb_strtolower($tipoVenda) === 'ordem-servico'){

            $url = 'https://app.omie.com.br/api/v1/servicos/os/';
            $call = 'ConsultarOS';
            $param = [
                "nCodOS"=>$id
            ];
        }elseif(mb_strtolower($tipoVenda) === 'contrato'){

            $url = 'https://app.omie.com.br/api/v1/servicos/contrato/';
            $call = 'ConsultarContrato';
            $param = [
                "contratoChave"=>[
                    'nCodCtr'=>$id
                ]
            ];

        }else{

            $url = 'https://app.omie.com.br/api/v1/produtos/pedido/';
            $call = 'ConsultarPedido';

            $param = [
                "codigo_pedido"=>$id
            ];
        }

        $array = [
            'app_key' => $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => $call,
            'param'=> [$param],
        ];
        
        $json = json_encode($array, JSON_UNESCAPED_UNICODE);

        
        return $this->omieServices->consultaVendaERP($json, $url);



    }

    public function getIdPloomesBysContractNumber($nContrato){

        $omieApp = $this->omieServices->getOmieApp();

        $contratos = $this->omieServices->listarContratos((object) $omieApp);

        

        $contrato = [];
        foreach($contratos['contratoCadastro'] as $contrato){

            if($contrato['cabecalho']['cNumCtr'] === $nContrato){
                $cContratoIntegracao = $contrato['cabecalho']['cCodIntCtr'];

                $idPloomes = explode('/', $cContratoIntegracao); 

                return $idPloomes[1];
            }

        }

        throw new WebhookReadErrorException('Não foi encontrado o Id do contrato do Ploomes no contrato do Omie, através do Número do contrato informado', 500);

    }

    public function getOrdersServicesItens(array $prdItem, int $idItemOmie, object $order, bool $isRecurrence):array
    {
        // print_r($prdItem);
        // exit;
        $serviceOrder = [];
        $pu = [];
        $service = [];
        $produtosUtilizados = [];

        //  print_r($prdItem);
        // exit;

        //verifica se tem serviço com produto junto
        if($prdItem['Product']['Group']['Family']['Name'] !== 'Serviços'){
                     
            //monts o produtos utilizados (pu)
            $pu['nCodProdutoPU'] = $idItemOmie;
            $pu['nQtdePU'] = $prdItem['Quantity'];
            
            $produtosUtilizados[] = $pu;
            
        }else{

            if($isRecurrence){
                
                $service['codServico'] = $idItemOmie;
                $service['codIntItem'] = $prdItem['Id'];
                $service['quant'] = $prdItem['Quantity'];
                $service['valorUnit'] = $prdItem['UnitPrice'];
                $service['seq'] = $prdItem['Ordination']+1;
                $service['pu'] = $produtosUtilizados;
                $service['desc'] = $order->descricaoServico;

            }else{
                //monta o serviço
                
                $service['nCodServico'] = $idItemOmie;
                $service['nQtde'] = $prdItem['Quantity'];
                $service['nValUnit'] = $prdItem['UnitPrice'];
                $service['cDescServ'] = $order->descricaoServico;
                $service['pu'] = $produtosUtilizados;
            }
            
            
            $serviceOrder = $service;
        }

        // print_r($serviceOrder);
        // exit;

        return $serviceOrder;

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

    public function insertProjectOmie(object $erp, object $order):string
    {
        $project = $this->omieServices->insertProject($erp,  $order->projeto);

        if(isset($project['faultstring'])){
            throw new WebhookReadErrorException('Erro ao cadastrar o Projeto no Omie: ' . $project['faultstring'], 500);
        }else{
            return $project['codigo'];
        }

    }

    public function deleteProject($omie, $order)
    {
        if(!isset($order->codProjeto)){
           return $delProject['faultstring'] = 'Não existia um projeto cadastrado no pedido para ser excluído';
        }
        
        $array = [
            'app_key' =>   $omie->appKey,
            'app_secret' => $omie->appSecret,
            'call' => 'ExcluirProjeto',
            'param'=>[
                [
                    'codigo'=> $order->codProjeto
                ]
            ],
        ];

        $json = json_encode($array);

        $delProject = $this->omieServices->deleteProject($json);

        if($delProject['codigo'] === "0"){
           return $delProject['descricao'];
 
         }else{
            return $delProject['faultstring'];
         }

    }

    public function getIdVendedorERP(object $omie, string $mailVendedor):string|null
    {
        return $this->omieServices->vendedorIdErp($omie, $mailVendedor);
    }

    //clientes

    //cadastra obj cliente com dados vindos do erp para enviar ao crm
    public function createObjectCrmContactFromErpData(array $args):object
    {
        // print_r($args['body']);
        // exit;
        $cliente = new stdClass();
        $decoded=$args['body'];
        $cliente->codigoClienteOmie= $decoded['event']['codigo_cliente_omie'];
       
        $omieApp = $this->omieServices->getOmieApp();

        $c = $this->omieServices->getClientById($cliente);
        $caracteristicas = $this->omieServices->getCaracteristicasClienteById($cliente);
        
       
        
        //as características são um array nome do campo e conteudo
        //a chave campo vai ser parte da sendExternalKey do campo do ploomes
        
        if($caracteristicas){
       
            foreach($caracteristicas['caracteristicas'] as $caracteristica){
                
                
                $chaveNome = mb_strtolower($caracteristica['campo']);
                
                if(mb_strpos($chaveNome, ' ')){
                    $chaveNome = str_replace(' ', '_', $chaveNome);
                }
                
                $cliente->$chaveNome = $caracteristica['conteudo'];
            }
            
        }
        
        
        $array = DiverseFunctions::achatarArray($c);

        $chave = 'id_cliente_erp_' . $omieApp['app_name'];
        $cliente->$chave = $array['codigo_cliente_omie'];
        $keyIntegrar ='integrar_base_'.$omieApp['app_name'];
    
        //$cliente->messageId = $array['messageId'];
        // $cliente->topic = $array['topic'];
        $cliente->bairro = $array['bairro'] ?? null;
        $cliente->bloqueado = $array['bloqueado']  ?? null;
        $cliente->bloquearFaturamento = ($array['bloquear_faturamento'] === 'S') ? true : false;
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
        $contribuinte = (isset($array['contribuinte']) && $array['contribuinte'] === 'S') ? true : false;
        $cliente->contribuinte = $contribuinte  ?? null;
        $cliente->agencia_dados_bancarios = $array['dadosBancarios_agencia']  ?? null;
        $cliente->banco_dados_bancarios = $array['dadosBancarios_codigo_banco']  ?? null;
        $cliente->conta_corrente_dados_bancarios = $array['dadosBancarios_conta_corrente']  ?? null;
        $cliente->cnpj_cpf_titular_dados_bancarios = $array['dadosBancarios_doc_titular']  ?? null;
        $cliente->chave_pix_dados_bancarios = $array['dadosBancarios_cChavePix']  ?? null;
        $cliente->nome_titular_dados_bancarios = $array['dadosBancarios_nome_titular']  ?? null;
        $cliente->email = $array['email']  ?? null;
        $cliente->endereco = $array['endereco']  ?? null;
        $cliente->enderecoNumero = $array['endereco_numero']  ?? null;
        $cliente->estado = $array['estado']  ?? null;
        $cliente->exterior = $array['exterior']  ?? null;
        $cliente->faxDdd = $array['fax_ddd']  ?? null;
        $cliente->faxNumero = $array['fax_numero']  ?? null;
        $cliente->homepage = $array['homepage']  ?? null;
        $cliente->inativo = $array['inativo']  ?? null;
        $cliente->$keyIntegrar = ($cliente->inativo === 'N') ? true : false;
        $cliente->inscricao_estadual = $array['inscricao_estadual']  ?? null;
        $cliente->inscricao_municipal = $array['inscricao_municipal']  ?? null;
        $cliente->inscricao_suframa = $array['inscricao_suframa']  ?? null;
        $cliente->logradouro = $array['logradouro']  ?? null;
        $cliente->nif = $array['nif']  ?? null;
        $cliente->nomeFantasia = htmlspecialchars_decode($array['nome_fantasia'])  ?? null;
        $cliente->obsDetalhadas = $array['obs_detalhadas']  ?? null;
        $cliente->observacao = $array['observacao']  ?? null;
        $simplesNacional = (isset($array['optante_simples_nacional']) && $array['optante_simples_nacional'] === 'S') ? true : false;
        $cliente->simples_nacional = $simplesNacional ?? null;
        $cliente->pessoaFisica = $array['pessoa_fisica']  ?? null;
        $produtorRural = (isset($array['produtor_rural']) && $array['produtor_rural'] === 'S') ? true : false;
        $cliente->produtor_rural = $produtorRural ?? null;
        $cliente->razaoSocial = htmlspecialchars_decode($array['razao_social'])  ?? null;
        $cliente->recomendacao_atraso = $array['recomendacao_atraso']  ?? null;
        $cliente->codigo_vendedor = $array['recomendacoes_codigo_vendedor'] ?? null;
        $cliente->emailFatura = $array['recomendacoes_email_fatura'] ?? null;
        $cliente->gerarBoletos = $array['recomendacoes_gerar_boletos'] ?? null;
        $cliente->numeroParcelas = $array['recomendacoes_numero_parcelas'] ?? null;
        $cliente->idTranspPadrao = $array['recomendacoes_codigo_transportadora'] ?? null;
        $transp = new stdClass();
        $transp->codigoClienteOmie = $cliente->idTranspPadrao;
        $transp = $this->omieServices->getClientByid($transp );
        $cliente->idTranspPadraoPloomes = $transp['codigo_cliente_integracao'] ?? null;
        
        $tags=[];
        if(isset($decoded['event']['tags'])){
            foreach($decoded['event']['tags'] as $t=>$v){
                $tags[$t]=$v;
               
            }
        }
        $cliente->tags = $tags;
        
        $cliente->telefoneDdd1 = $array['telefone1_ddd'];
        $cliente->telefoneNumero1 = $array['telefone1_numero'];
        $cliente->telefoneDdd2 = $array['telefone2_ddd'];
        $cliente->telefoneNumero2 = $array['telefone2_numero'];
        $cliente->tipo_atividade = $array['tipo_atividade'];
        $cliente->limite_credito = $array['valor_limite_credito'];

        $cliente->nome_endereco_entrega = $array['enderecoEntrega_entRazaoSocial'] ?? null;
        $cliente->cpf_cnpj_recebedor = $array['enderecoEntrega_entCnpjCpf'] ?? null;
        $cliente->endereco_endereco_entrega = $array['enderecoEntrega_entEndereco'] ?? null;
        $cliente->numero_endereco_entrega = $array['enderecoEntrega_entNumero'] ?? null;
        $cliente->complemento_endereco_entrega = $array['enderecoEntrega_entComplemento'] ?? null;
        $cliente->bairro_endereco_entrega = $array['enderecoEntrega_entBairro'] ?? null;
        $cliente->cep_endereco_entrega = $array['enderecoEntrega_entCEP'] ?? null;
        $cliente->estado_endereco_entrega = $array['enderecoEntrega_entEstado'] ?? null;
        $cliente->cidade_endereco_entrega = $array['enderecoEntrega_entCidade'] ?? null;
        $cliente->telefone_endereco_entrega = $array['enderecoEntrega_entTelefone'] ?? null;
        $cliente->inscricao_estadual_endereco_entrega = $array['enderecoEntrega_entIE'] ?? null;
      
        // $cliente->authorEmail = $array['author_email'];
        // $cliente->authorName = $array['author_name'];
        // $cliente->authorUserId = $array['author_userId'];
        $cliente->appKey = $decoded['appKey'];
        // $cliente->appHash = $array['appHash'];
        // $cliente->origin = $array['origin'];
        
        
        return $cliente;
    }

    // createPloomesContactFromErpObject - cria o json no formato do ploomes para enviar pela API do Ploomes
    public function createPloomesContactFromErpObject(object $contact, PloomesServices $ploomesServices):string
    {
      
        
        $omie = new stdClass();
        $omieApp = $this->omieServices->getOmieApp();
        
        $omie->appName = $omieApp['app_name'];
        $omie->appSecret = $omieApp['app_secret'];
        $omie->appKey = $omieApp['app_key'];
        $omie->ncc = $omieApp['ncc'];
        $omie->tenancyId = $omieApp['tenancy_id'];
        $custom = $_SESSION['contact_custom_fields'][$omie->tenancyId];
        // print_r($contact);
        // print_r($custom['Cliente']);
        // exit;
        $chaveTable = "tabela_financeiro_{$omie->appName}";
        $chaveStatus = "status_financeiro_{$omie->appName}";
        $contact->$chaveTable = $contact->tabela_financeiro;
        $contact->$chaveStatus = $contact->status_financeiro;
        // print_r($contact);
        // exit;
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

        if(!empty($contact->tipo_atividade) || $contact->tipo_atividade == 0){
            
            $atividade = $this->omieServices->getTipoAtividade( $omie, $contact->tipo_atividade, $name = null);
            
            foreach($custom['Cliente'] as $c){
                if($c['SendExternalKey'] === 'bicorp_api_tipo_atividade_out'){
                    
                    foreach($c['Options'] as $optAtividade){
                        
                        if($optAtividade['Name'] === $atividade['cDescricao']){
                        
                            $contact->tipo_atividade = $optAtividade['Id'];
                            break;
                        }
                    }
                }
            }

        }
        
     
        $ploomesTags = $ploomesServices->getTagsByEntityId(1);//id da entidade
        // print_r($ploomesTags);
        $tags = [];
        $tag = [];
        // print_r($contact->tags);
        // exit;
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
        // print'tipo de atividade'.PHP_EOL;
        // print_r($contact->tipo_atividade );
         
        $op = CustomFieldsFunction::createOtherPropertiesByEntity($custom['Cliente'], $contact, $ploomesServices);

        // print_r($op);
        // exit;
        
        $data['OtherProperties'] = $op;
        
        $json = json_encode($data,JSON_UNESCAPED_UNICODE);
        
        // print_r($json);
        // exit;
     
        return $json;

    }

    // createObjectErpClientFromCrmData - cria obj cliente vindo do Ploomes ao ERP Omie
    public function createObjectErpClientFromCrmData(array $args, PloomesServices $ploomesServices):object
    {
 
        $decoded = $args['body'];
        
           
       
        //aqui ele busca o cliente no Ploomes pelo id, se for tipo 2 (contato) ele vai atualizar o cliente no omie buscando as informações da empresa no ploomes através do companyId do contato do cliente 
        /*quando um cliente é pessoa física o sistema busca o id da empresa em company Id e transforma a empresa no contato a ser integrado e a pessoa como contato, se a pessoa não tiver company id cadastra como pessoa*/
        ($decoded['New']['TypeId'] === 2 && isset($decoded['New']['CompanyId']) && $decoded['New']['CompanyId'] !== null)? $cliente = $ploomesServices->getClientById($decoded['New']['CompanyId']):$cliente = $ploomesServices->getClientById($decoded['New']['Id']);
      
        //$cliente = $ploomesServices->getClientById($decoded['New']['Id']);
        $omie = new stdClass();
        //este app omie só pode servir para buscar campos fixos do omie os dados dos usuários devem vir do ploomes
        $omieApp =$args['Tenancy']['erp_bases'][0];
        
        $omie->appName = $omieApp['app_name'];
        $omie->appSecret = $omieApp['app_secret'];
        $omie->appKey = $omieApp['app_key'];
        $omie->ncc = $omieApp['ncc'];
        $omie->tenancyId = $omieApp['tenancy_id'];
        
        $contact = new stdClass(); 
        
        $custom = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($cliente['OtherProperties'],'Cliente',$args['Tenancy']['tenancies']['id']); 
        
        

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
                
                foreach($options as $opt)
                {
                    if(isset($custom['bicorp_api_tipo_atividade_out']) && $opt['Id'] === $custom['bicorp_api_tipo_atividade_out']){
                
                        $atividade = $this->omieServices->getTipoATividade($omie, $id = null, $opt['Name']);
                        $contact->tipo_atividade = $atividade['cCodigo'];
                        break;
                    }
                }
            }
        }
               
        

             
        $contact->bloquearFaturamento = $custom['bicorp_api_bloquear_faturamento_out'] ?? null;
        $contact->cpf_empresa = $custom['bicorp_api_cpf_empresa_out'] ?? null;
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
        $contact->inscricao_estadual = $custom['bicorp_api_inscricao_estadual_out'] ?? null;
        //contact_D21FAEED-75B2-40E4-B169-503131EB3609 = inscrição municipal
        // $contact->inscricaoMunicipal = $prop['contact_D21FAEED-75B2-40E4-B169-503131EB3609'] ?? null;
        $contact->inscricao_municipal = $custom['bicorp_api_inscricao_municipal_out'] ?? null;
        //contact_3094AFFE-4263-43B6-A14B-8B0708CA1160 = inscrição suframa
        // $contact->inscricaoSuframa = $prop['contact_3094AFFE-4263-43B6-A14B-8B0708CA1160'] ?? null;
        $contact->inscricao_suframa = $custom['bicorp_api_inscricao_suframa_out'] ?? null;
        //contact_9BB527FD-8277-4D1F-AF99-DD88D5064719 = Simples nacional?(s/n)  
        // $contact->simplesNacional = $prop['contact_9BB527FD-8277-4D1F-AF99-DD88D5064719'] ?? null;//é obrigatório para emissão de nf
        $contact->simples_nacional = $custom['bicorp_api_simples_nacional_out'] ?? null;//é obrigatório para emissão de nf
        (isset($contact->simples_nacional) && $contact->simples_nacional !== false) ? $contact->simples_nacional = 'S' : $contact->simples_nacional = 'N';
        //contact_3C521209-46BD-4EA5-9F41-34756621CCB4 = contato1
        // $contact->contato1 = $prop['contact_3C521209-46BD-4EA5-9F41-34756621CCB4'] ?? null; //estava com esta linha aqui ativa
        $contact->contato1 = $cliente['Contacts'][0]['Name'] ?? null;
        //contact_F9B60153-6BDF-4040-9C3A-E23B1469894A = Produtor Rural
        // $contact->produtorRural = $prop['contact_F9B60153-6BDF-4040-9C3A-E23B1469894A'] ?? null;
        $contact->produtor_rural = $custom['bicorp_api_produtor_rural_out'] ?? null;
        (isset($contact->produtor_rural) && $contact->produtor_rural !== false) ? $contact->produtor_rural = 'S' : $contact->produtor_rural = 'N';
        //contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453 = Contribuinte(s/n)
        // $contact->contribuinte = $prop['contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453'] ?? null;
        $contact->contribuinte = $custom['bicorp_api_contribuinte_out'] ?? null;
        (isset($contact->contribuinte) && $contact->contribuinte !== false) ? $contact->contribuinte = 'S' : $contact->contribuinte = 'N';
        //contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD = limite de crédito
        // $contact->limiteCredito = $prop['contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD'] ?? null;
        $contact->limite_credito = $custom['bicorp_api_limite_credito_out'] ?? null;
        //contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4 = inativo (s/n)
        // $contact->inativo = $prop['contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4'] ?? null;
        $contact->inativo = strtolower($cliente['Status']['Name']) ?? null;
        (isset($contact->inativo) && $contact->inativo === 'inativo') ? $contact->inativo = 'S' : $contact->inativo = 'N';
        //contact_C613A391-155B-42F5-9C92-20C3371CC3DE = bloqueia excusão (s/n)
        // $contact->bloquearExclusao = $prop['contact_C613A391-155B-42F5-9C92-20C3371CC3DE'] ?? null;
        $contact->bloquearExclusao = $custom['bicorp_api_bloquear_exclusao_out'] ?? null;
        (isset($contact->bloquearExclusao) && $contact->bloquearExclusao !== false) ? $contact->bloquearExclusao = 'S' : $contact->bloquearExclusao = 'N';
        //contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33 = transportadora padrão
        // $contact->cTransportadoraPadrao = $prop['contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33'] ?? null;
        $contact->cTransportadoraPadrao = $custom['bicorp_api_transportadora_padrao_out'] ?? null;
        //contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9 = codigo do banco
        // $contact->cBanco = $prop['contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9'] ?? null;
        $contact->cBanco = $custom['bicorp_api_banco_dados_bancarios_out'] ?? null;
        //contact_1F1E1F00-34CB-4356-B852-496D62A90E10 = Agência
        // $contact->agencia = $prop['contact_1F1E1F00-34CB-4356-B852-496D62A90E10'] ?? null;
        $contact->agencia = $custom['bicorp_api_agencia_dados_bancarios_out'] ?? null;
        //contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80 = Num conta corrente
        // $contact->nContaCorrente = $prop['contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80'] ?? null;
        $contact->nContaCorrente = $custom['bicorp_api_conta_corrente_dados_bancarios_out'] ?? null;
        //contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50 = CNPJ/CPF titular
        // $contact->docTitular = $prop['contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50'] ?? null;
        $contact->docTitular = $custom['bicorp_api_cnpj_cpf_titular_dados_bancarios_out'] ?? null;
        //contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066 = Nome di titular
        // $contact->nomeTitular = $prop['contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066'] ?? null;
        $contact->nomeTitular = $custom['bicorp_api_nome_titular_dados_bancarios_out'] ?? null;
        //contact_847FE760-74D0-462D-B464-9E89C7E1C28E = chave pix
        // $contact->chavePix = $prop['contact_847FE760-74D0-462D-B464-9E89C7E1C28E'] ?? null;
        $contact->chavePix = $custom['bicorp_api_chave_pix_dados_bancarios_out'] ?? null;
        //contact_3E075EA9-320C-479E-A956-5A3734C55E51 = Transportadora Padrão (código cliente ploomes)
        // $contact->idTranspPadrao = $prop['contact_3E075EA9-320C-479E-A956-5A3734C55E51'] ?? null;
        // $contact->idTranspPadrao = $custom['contact_3E075EA9-320C-479E-A956-5A3734C55E51'] ?? null; este campo não existe mais no cadastro do cliente
   
        //transportadora padrão não é obrigatória, mas se for selecionado, osistema vai pegar o codigo do ploomes, buscar o cliente/transportadora cadastrado e em seguida os campos personalizados dele. Depois pega a quantidade de bases do Omie para poder pegar o id da transportadora de cada base, para isso, a transportadora deve ter cadastro em todos os aplicativos omie do cliente. Caso contrário o id da transportadora para a base d edestino pode ser nulo. 
        $contact->transpOmie = [];
        if($contact->cTransportadoraPadrao !== null){
            $c = $ploomesServices->getClientById($contact->cTransportadoraPadrao);
            
            $transpCustom = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($c['OtherProperties'],'Cliente',$args['Tenancy']['tenancies']['id']);
            
            
            $transpOmie = [];
            foreach($args['Tenancy']['erp_bases'] as $bTransp){
                $nBase = strtolower($bTransp['app_name']);
                $transpKey = 'bicorp_api_id_cliente_erp_'.$nBase.'_out';

                $transpOmie['id'] = $transpCustom[$transpKey] ?? null;
                $transpOmie['appKey'] = $bTransp['app_key'] ?? null;
                $transpOmie['appname'] = $nBase ?? null;

                $contact->transpOmie[] = $transpOmie;
            }

            // foreach($c['OtherProperties']  as $cOthers){

            //     $fk = $cOthers['FieldKey'];
            //     $vl = $cOthers['StringValue'];

            //         $transpOP[$fk] = $vl;
            // }
            
            // $contact->cTranspOmie = [];
            // $contact->cTranspOmie[0] = $transpOP['contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3'] ?? null;
            // $contact->cTranspOmie[1] = $transpOP['contact_6DB7009F-1E58-4871-B1E6-65534737C1D0'] ?? null;
            // $contact->cTranspOmie[2] = $transpOP['contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2'] ?? null;
            // $contact->cTranspOmie[3] = $transpOP['contact_07784D81-18E1-42DC-9937-AB37434176FB'] ?? null;

        }

        //contact_33015EDD-B3A7-464E-81D0-5F38D31F604A = Transferência Padrão
        // $contact->transferenciaPadrao = $prop['contact_33015EDD-B3A7-464E-81D0-5F38D31F604A'] ?? null; este campo não existe mais no cadastro do cliente do Omie
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
            }else{
                $base['integrar'] = null;
                
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

        $enderecoEntrega = [
            'entRazaoSocial'=>$custom['bicorp_api_nome_endereco_entrega_out'] ?? null,
            'entCnpjCpf'=>$custom['bicorp_api_cpf_cnpj_recebedor_out'] ?? null,
            'entEndereco'=>$custom['bicorp_api_endereco_endereco_entrega_out'] ?? null,
            'entNumero'=>$custom['bicorp_api_numero_endereco_entrega_out'] ?? null,
            'entComplemento'=>$custom['bicorp_api_complemento_endereco_entrega_out']?? null,
            'entBairro'=>$custom['bicorp_api_bairro_endereco_entrega_out'] ?? null,
            'entCEP'=>$custom['bicorp_api_cep_endereco_entrega_out'] ?? null,
            'entEstado'=>$custom['bicorp_api_estado_endereco_entrega_out'] ?? null,
            // 'entEstado'=>'PR',
            'entCidade'=>$custom['bicorp_api_cidade_endereco_entrega_out'] ?? null,
            'entTelefone'=>$custom['bicorp_api_telefone_endereco_entrega_out'] ?? null,
            'entIE'=>$custom['bicorp_api_inscricao_estadual_endereco_entrega_out'] ?? null,
            'entSepararEndereco'=>'S',
        ];

        $contact->enderecoEntrega = $enderecoEntrega;
        

        return $contact;
    }

    // updateContactCRMToERP - atualiza um contato do CRM para o ERP ok
    public function updateContactCRMToERP(object $contact, PloomesServices $ploomesServices, object $tenant):array
    {
       
        $json = $this->createJsonClienteCRMToERP($contact, $tenant); 

        $alterar = $this->omieServices->alteraClienteCRMToERP($json);

        if(isset($alterar['codigo_status']) && $alterar['codigo_status'] == "0") 
        {
            $json = $this->insertIdClienteERPinContactCRM($alterar, $tenant);
            //insere o id do omie no campo correspondente do cliente Ploomes
            ($ploomesServices->updatePloomesContact($json, $contact->id) !== null) ?
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
    public function createContactCRMToERP(object $contact, PloomesServices $ploomesServices, object $tenant):array
    { 
        $messages=['success'=>[],'error'=>[]];

        $json = $this->createJsonClienteCRMToERP($contact, $tenant);
        
        $criaClienteERP = $this->omieServices->criaClienteERP($json);

        //verifica se criou o cliente no ERP (No caso do Omie, o prórpio retorn o traz o status da inserção e o ID do cliente)
        if (isset($criaClienteERP['codigo_status']) && $criaClienteERP['codigo_status'] == "0") {

            //atualiza contact ploomes com o id do cliente no ERP id omie no ploomes teste voltar a ele 1940698872
            $json = $this->insertIdClienteERPinContactCRM($criaClienteERP, $tenant);
            
            //insere o id do omie no campo correspondente do cliente Ploomes
            ($ploomesServices->updatePloomesContact($json, $contact->id) !== null) ?
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
    public function createContactERP(string $json, PloomesServices $ploomesServices):array
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
    public function updateContactERP(string $json, object $contact, PloomesServices $ploomesServices):array
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
        $clienteJson['cnpj_cpf'] = $contact->cnpj ?? $contact->cpf ?? $contact->cpf_empresa ?? null;
        $clienteJson['bloquear_faturamento'] = ($contact->bloquearFaturamento) ? 'S' : 'N';
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
        $clienteJson['inscricao_estadual'] = $contact->inscricao_estadual ?? null;
        $clienteJson['inscricao_municipal'] = $contact->inscricao_municipal ?? null;
        $clienteJson['inscricao_suframa'] = $contact->inscricao_suframa ?? null;
        $clienteJson['optante_simples_nacional'] = $contact->simples_nacional ?? null;
        $clienteJson['produtor_rural'] = $contact->produtor_rural ?? null;
        $clienteJson['contribuinte'] = $contact->contribuinte ?? null;
        $clienteJson['tipo_atividade'] = $contact->tipo_atividade ?? null;
        $clienteJson['valor_limite_credito'] = $contact->limite_credito ?? null;
        $clienteJson['observacao'] = $contact->observacao ?? null;
        //fim aba CNAE e Outros
        //inicio de enderecos
        $clienteJson['enderecoEntrega'] = [];
        $clienteJson['enderecoEntrega'][] = $contact->enderecoEntrega ?? null;
        //fim de endereços
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
  
        $recomendacoes['codigo_transportadora']= $contact->cTranspOmie ?? null;//6967396742;// $contact->ownerId ?? null;
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

    //cria um objeto do produto vindo do omie para enviar ao ploomes
    public function createObjectCrmProductFromErpProduct($prd, $ploomesServices)
    {      
        //cria o objeto de produtos
        $product = new stdClass();
        $omieApp = $this->omieServices->getOmieApp();

        $chave = 'idProductOmie' . $omieApp['app_name'];
        $product->$chave = $prd['codigo_produto'];

        $product->appKey = $omieApp['app_key'];
        $product->appSecret = $omieApp['app_secret'];
        // $pFamily = explode('.' , $array['topic']);
        $nameFamily= 'Produtos';//o s é para o topic Produto ficar no plural
        $verifyFamily = $ploomesServices->getFamilyByName($nameFamily);
        if(isset($verifyFamily['Id'])){
            $product->idFamily =  $verifyFamily['Id'];
        }else{
            $createFamily = $ploomesServices->createNewFamily($nameFamily);
            $product->idFamily =  $createFamily['Id'];
        }        
       
        $product->altura = $prd['altura'];
        $product->bloqueado = $prd['bloqueado'];
        $product->cnpj_fabricante = $prd['recomendacoes_fiscais']['cnpj_fabricante'];
        $product->codigo = $prd['codigo'];
        $product->codigo_familia = $prd['codigo_familia'];
        ($product->codigo_familia == 0 || $product->codigo_familia == null) ? $product->nome_familia = $nameFamily : $product->nome_familia = $this->omieServices->getFamiliaById($product);
        $verifyGroup = $ploomesServices->getGroupByName($product->nome_familia);
        if(isset($verifyGroup['Id'])){
            $product->idGroup =  $verifyGroup['Id'];
        }else{
            $createGroup = $ploomesServices->createNewGroup($product->nome_familia, $product->idFamily);
            $product->idGroup =  $createGroup['Id'];
        }
        $product->codigo_produto = $prd['codigo_produto'];
        $product->codigo_produto_integracao = $prd['codigo_produto_integracao'];
        $product->combustivel_codigo_anp = $prd['combustivel']['codigo_anp'] ?? null;
        $product->combustivel_descr_anp = $prd['combustivel']['descr_anp'] ?? null;
        $product->cupom_fiscal = $prd['recomendacoes_fiscais']['cupom_fiscal'];
        $product->descr_detalhada = $prd['descr_detalhada'];
        $product->descricao = $prd['descricao'];
        $product->dias_crossdocking = $prd['dias_crossdocking'];
        $product->dias_garantia = $prd['dias_garantia'];
        $product->ean = $prd['ean'];
        $product->estoque_minimo = $prd['estoque_minimo'];
        $product->id_cest = $prd['recomendacoes_fiscais']['id_cest'];
        $product->id_preco_tabelado = $prd['recomendacoes_fiscais']['id_preco_tabelado'];
        $product->inativo = $prd['inativo'];
        $product->indicador_escala = $prd['recomendacoes_fiscais']['indicador_escala'];
        $product->largura = $prd['largura'];
        $product->marca = $prd['marca'];
        $product->market_place = $prd['recomendacoes_fiscais']['market_place'];
        $product->modelo = $prd['modelo'];
        $product->ncm = $prd['ncm'];
        $product->obs_internas = $prd['obs_internas'];
        $product->origem_mercadoria = $prd['recomendacoes_fiscais']['origem_mercadoria'];
        $product->peso_bruto = $prd['peso_bruto'];
        $product->peso_liq = $prd['peso_liq'];
        $product->profundidade = $prd['profundidade'];
        $product->quantidade_estoque = $prd['quantidade_estoque'];
        $product->tipoItem = $prd['tipoItem'];
        $product->unidade = $prd['unidade'];
        $product->valor_unitario = $prd['valor_unitario'];

        $k = 'tabela_estoque_'.strtolower($omieApp['app_name']);
        $product->$k = $this->getStock($product, $omieApp); 
        //$product->stock = $this->getStock($product, $omieApp);      
        
        return $product;
    }

    public function getStock(object $product, array $omieApp){
       
        // $arrayStockLocation = [
        //             'app_key' => $omieApp['app_key'],
        //             'app_secret' => $omieApp['app_secret'],
        //             'call' => 'ListarLocaisEstoque',
        //             'param' => [
        //                 [
        //                     'nPagina'=>1,
        //                     'nRegPorPagina'=>50,
                        
        //                 ]
        //             ]
        //         ];

        // $arrayPosicao = [
        //             'app_key' => $omieApp['app_key'],
        //             'app_secret' => $omieApp['app_secret'],
        //             'call' => 'PosicaoEstoque',
        //             'param' => [
        //                 [
        //                     'codigo_local_estoque' => 5272991510,
        //                     'id_prod'=>607108934,
        //                     'data'=>date('d/m/Y'),
        //                 ]
        //             ]
        //         ];
        $arrayEstoque = [
            'app_key' => $omieApp['app_key'],
            'app_secret' => $omieApp['app_secret'],
            'call' => 'ObterEstoqueProduto',
            'param' => [
                [
                    'nIdProduto'=>$product->codigo_produto,
                    'dDia' => date('d/m/Y'),
                ]
            ]
        ];


        $stock = [

        ];

        // $location = $this->omieServices->getStockLocation(json_encode($arrayStockLocation));
        // print_r($location);
        // $stock = $this->omieServices->getStockById(json_encode($arrayPosicao));
        // print_r($stock);
        $stock = $this->omieServices->getStock(json_encode($arrayEstoque));
        $table = $this->createTableStock($stock);

        return $table;

    }

    public function createTableStock($stock)
    {   
       
        // $local = ($stock['codigo_local_estoque'] === 6879399409)? 'Padrão' : $stock['codigo_local_estoque'];
        $html = file_get_contents('https://integracao.dev-webmurad.com.br/src/views/pages/gerenciador.pages.stockTable.php');
        $tLinha = file_get_contents('https://integracao.dev-webmurad.com.br/src/views/pages/gerenciador.pages.dataStockTable.php');
        // $html = file_get_contents('http://middleware/src/views/pages/gerenciador.pages.stockTable.php');
        // $tLinha = file_get_contents('http://middleware/src/views/pages/gerenciador.pages.dataStockTable.php');
        
        // $html = file_get_contents('https://integracao.dev-webmurad.com.br/src/views/pages/gerenciador.pages.stockTable.php');
        $linhas = '';
        if(is_array($stock) && !empty($stock['listaEstoque'])){
            
            foreach($stock['listaEstoque'] as $st){
            
                $linha =  str_replace(['{local}','{saldo}','{minimo}','{previsaoSaida}' ],[$st['cDescricaoLocal'],$st['nSaldo'],$st['nEstoqueMinimo'],$st['nPrevisaoSaida']],$tLinha);
                $linhas .= $linha; 

            }
        }else{
            $linha =  str_replace(['{local}','{saldo}','{minimo}','{previsaoSaida}' ],['indefinido', 0, 0, 0],$tLinha);
                $linhas .= $linha; 
        }
        // $html = str_replace('{local}', $st['cDescricaoLocal'], $html);
        // $html = str_replace('{saldo}', $st['nSaldo'], $html);
        // $html = str_replace('{minimo}', $st['nEstoqueMinimo'], $html);
        // $html = str_replace('{previsaoSaida}', $st['nPrevisaoSaida'], $html);
        // $html = str_replace('{reservado}', $st['reservado'], $html);
        $html = str_replace('{dataStock}', $linhas, $html);
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
        if(isset($service->idGroup)){
            
            $data['GroupId'] =  $service->idGroup;
        }
        
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
        $op = (isset($custom['Produto'])) ? CustomFieldsFunction::createOtherPropertiesByEntity($custom['Produto'], $service) : [];
       
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

    public function createPersonArrays(object $contact): array
    {
        $array = [];
        $jsonPerson =[];

        if(isset($contact->contato)){
            $jsonPerson['TypeId'] = 2;
            $jsonPerson['Name'] = $contact->contato;
            $jsonPerson['CompanyId'] = $contact->companyId;

            $jsonPerson['Phones'] = [];
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
            $jsonPerson['Phones'][] = $phone1 ?? null;
            $jsonPerson['Phones'][] = $phone2 ?? null;
     
        }

        $array[] = json_encode($jsonPerson);

        return $array;
    }

    public function getFinHistory($contact){
     
        $tableFin = '';
        $omieApp = $this->omieServices->getOmieApp();
        $url = 'https://app.omie.com.br/api/v1/financas/pesquisartitulos/';
        $array = [
            'app_key' =>   $omieApp['app_key'],
            'app_secret' => $omieApp['app_secret'] ,
            'call' => 'PesquisarLancamentos',
            'param'=>[
                [
                    'cCPFCNPJCliente'=> $contact->cnpjCpf,
                ]
            ],
        ];

        $json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $financeiro = $this->omieServices->getFinaceiro($json, $url) ?? null;
        
        if(count($financeiro) > 0){
            
            $tableFin = $this->createTableFin($financeiro);
            
            $status = $this->getStatusFinanceito($financeiro);
            
        }else{
            $tableFin = 'Sem contas a pagar/receber no ERP';
            $status = 'adimplente';
        }

        return ['table'=>$tableFin, 'status'=>$status];

        
    }

    public function createTableFin($financeiro)
    {
        $origem = [];
        $origem['APBP'] = 'Integração de Pagamento de Conta';
        $origem['APBR'] = 'Integração de Recebimento de Conta';
        $origem['APEP'] = 'Integração de Lançamento de Despesa';
        $origem['APER'] = 'Integração de Lançamento de Receita';
        $origem['APIP'] = 'Integração de Conta a Pagar';
        $origem['APIR'] = 'Integração de Conta a Receber';
        $origem['BARP'] = 'Conta a Pagar Importada por Código de Barras';
        $origem['BARR'] = 'Conta a Receber Importada por Código de Barras';
        $origem['BAXP'] = 'Pagamento de Conta a Pagar';
        $origem['BAXR'] = 'Recebimento de Conta a Receber';
        $origem['COMP'] = 'Parcela a Pagar de Compras';
        $origem['DEVP'] = 'Conta a Pagar da Devolução de Venda';
        $origem['DEVR'] = 'Conta a Receber da Devolução ao Fornecedor';
        $origem['EXTP'] = 'Lançamento Manual de Despesa';
        $origem['EXTR'] = 'Lançamento Manual de Receita';
        $origem['IMPP'] = 'Parcela a Pagar de uma Nota de Importação';
        $origem['MANP'] = 'Lançamento Manual de Conta a Pagar';
        $origem['MANR'] = 'Lançamento Manual de Conta a Receber';
        $origem['NFEP'] = 'Conta a Pagar Importada de uma NF-e';
        $origem['NFER'] = 'Conta a Receber Importada de uma NF-e';
        $origem['OFXP'] = 'Pagamento Importado de um arquivo OFX';
        $origem['OFXR'] = 'Recebimento Importado de um arquivo OFX';
        $origem['RPTP'] = 'Repetição de Contas a Pagar';
        $origem['RPTR'] = 'Repetição de Contas a Receber';
        $origem['TRAP'] = 'Débito de Transf. entre Contas Correntes';
        $origem['TRAR'] = 'Crédito de Transf. entre Contas Correntes';
        $origem['VENR'] = 'Parcela a Receber de Vendas';
        $origem['XMLP'] = 'Conta a Pagar Importada de um arquivo XML';
        $origem['XMLR'] = 'Conta a Receber Importada de um arquivo XML';

        $tr ='';   
        // $html = file_get_contents('http://middleware/src/views/pages/gerenciador.pages.finTable.php');
        $html = file_get_contents('https://integracao.dev-webmurad.com.br/src/views/pages/gerenciador.pages.finTable.php');
        foreach($financeiro as $fin){

            $tipo = $fin['cabecTitulo']['cNatureza'];

            if($tipo !== 'R' ){
                continue;
            }else{

                $o = $origem[$fin['cabecTitulo']['cOrigem']];
                $status = $fin['cabecTitulo']['cStatus'];
            
                if($status == 'RECEBIDO' && !empty($fin['lancamentos']))
                {
                
                    $totalLancado = 0;

                    foreach($fin['lancamentos'] as $lancamento){
                        $totalLancado += $lancamento['nValLanc'];
                }

                    $porcentagem = ($totalLancado / $fin['cabecTitulo']['nValorTitulo'])*100;

                    $statusRecebido = ($porcentagem === 100) ? 'LIQUIDADO' : round($porcentagem,2) . '% Recebido';

                }else{
                    $statusRecebido = $status;
                }
                    $statusClass = match($statusRecebido)
                {    
                    'A VENCER' => 'titulo-a-vencer',
                    'LIQUIDADO' => 'titulo-pago',
                    'PAGO' => 'titulo-pago',
                    'ATRASADO' => 'titulo-vencido',
                    default=>'titulo-a-vencer'              
                };
                $obs = $fin['cabecTitulo']['observacao'] ?? null;
                $tr .= "<tr>";
                $tr .= "<td >{$fin['cabecTitulo']['cNumParcela']}</td>
                <td class='{$statusClass}' style='min-width: 150px;'>{$statusRecebido}</td>
                <td style='min-width: 150px;'>{$o}</td>
                <td style='min-width: 150px;'>{$fin['cabecTitulo']['dDtRegistro']}</td>
                <td style='min-width: 150px;'>{$fin['cabecTitulo']['dDtEmissao']}</td>
                <td style='min-width: 150px;'>{$fin['cabecTitulo']['dDtPrevisao']}</td>
                <td style='min-width: 150px;' class='{$statusClass}'>{$fin['cabecTitulo']['dDtVenc']}</td>
                <td style='min-width: 150px;'>". number_format($fin['cabecTitulo']['nValorTitulo'],2,',','.') ."</td>
                <td style='min-width: 150px;'>{$obs}</td>";
                $tr .= "</tr>";
                    
            }

        }
        $html = str_replace('{tr}', $tr, $html);
        $html = str_replace('{data}', date('d/m/Y H:i:s'), $html);

        return $html;
    }

    public function getStatusFinanceito($financeiro)
    {
        $origem = [];

        $origem[] ='APBR';//'Integração de Recebimento de Conta';
        $origem[] ='APER';//'Integração de Lançamento de Receita';
        $origem[] ='APIR';//'Integração de Conta a Receber';
        $origem[] ='BARR';//'Conta a Receber Importada por Código de Barras';
        $origem[] ='BAXR';//'Recebimento de Conta a Receber';
        $origem[] ='DEVR';//'Conta a Receber da Devolução ao Fornecedor';
        $origem[] ='EXTR';//'Lançamento Manual de Receita';
        $origem[] ='MANR';//'Lançamento Manual de Conta a Receber';
        $origem[] ='NFER';//'Conta a Receber Importada de uma NF-e';
        $origem[] ='OFXR';//'Recebimento Importado de um arquivo OFX';
        $origem[] ='RPTR';//'Repetição de Contas a Receber';
        $origem[] ='TRAR';//'Crédito de Transf. entre Contas Correntes';
        $origem[] ='VENR';//'Parcela a Receber de Vendas';
        $origem[] ='XMLR';//'Conta a Receber Importada de um arquivo XML';

        $total = ['adimplente'=>0,'inadimplente'=>0];

        foreach($financeiro as $fin){
            //print_r($fin['resumo']['cLiquidado']);
            $today = new DateTime();
            $today->format('d/m/Y');
            $vencimento = DateTime::createFromFormat('d/m/Y', $fin['cabecTitulo']['dDtVenc']);
            // Adiciona, por exemplo, 5 dias
            //$vencimento->modify('+5 days');

            if(in_array($fin['cabecTitulo']['cOrigem'],$origem) && $fin['resumo']['cLiquidado'] === 'N' && $today > $vencimento){
                ++$total['inadimplente'];
            }else{
                ++$total['adimplente'];
            }
        }
        
        if($total['inadimplente'] > 0){
            return 'inadimplente';
        }else{
            return 'adimplente';
        }
    }

    public function createObjectCrmContactFinancialFromErpData($args, $ploomesServices)
    {

        $cliente = new stdClass();
        $decoded=$args['body'];
        $cliente->codigoClienteOmie = $decoded['event']['codigo_cliente_fornecedor'] ?? $decoded['event'][0]['codigo_cliente_fornecedor'];
       
        
        $omieApp = $this->omieServices->getOmieApp();
        
        $c =  $this->omieServices->getClientById($cliente);
         
        $array = DiverseFunctions::achatarArray($c);
        
        $chave = 'id_cliente_erp_' . $omieApp['app_name'];
        $cliente->$chave = $array['codigo_cliente_omie'];
        $contact = new stdClass();
        $contact->cnpjCpf =  $c['cnpj_cpf'];
        $contact->nomeFantasia = htmlspecialchars_decode($array['nome_fantasia'])  ?? null;

        $dataFinancial = $this->getFinHistory($contact);

        // print_r($contact);
        // exit;

        $contact->tabela_financeiro = $dataFinancial['table'];
        $contact->status_financeiro = $dataFinancial['status'];

        return $contact;
       
    }

        // createPloomesContactFinancialFromErpObject - cria o json no formato do ploomes para enviar pela API do Ploomes
    public function createPloomesContactFinancialFromErpObject(object $contact, PloomesServices $ploomesServices):string
    {
        $omie = new stdClass();
        $omieApp = $this->omieServices->getOmieApp();
        
        $omie->appName = $omieApp['app_name'];
        $omie->appSecret = $omieApp['app_secret'];
        $omie->appKey = $omieApp['app_key'];
        $omie->ncc = $omieApp['ncc'];
        $omie->tenancyId = $omieApp['tenancy_id'];
        $custom = $_SESSION['contact_custom_fields'][$omie->tenancyId];
        $chaveTable = "tabela_financeiro_{$omie->appName}";
        $chaveStatus = "status_financeiro_{$omie->appName}";
        $contact->$chaveTable = $contact->tabela_financeiro;
        $contact->$chaveStatus = $contact->status_financeiro;

        $data = [];
         
        $op = CustomFieldsFunction::createOtherPropertiesByEntity($custom['Cliente'], $contact);

    
        $data['OtherProperties'] = $op;
        
        $json = json_encode($data,JSON_UNESCAPED_UNICODE);
     
        return $json;

    }

    public function createInvoiceObject($args):object
    {
        $decoded = $args['body'];//decodifica o json em array
        $invoicing = new stdClass();//monta objeto da nota fiscal

        $omieApp = $this->omieServices->getOmieApp();
   
        $omie = new stdClass();
        $omie->appKey = $omieApp['app_key'];
        $omie->appSecret = $omieApp['app_secret'];

        if(isset($decoded['event']['id_pedido']) && $decoded['event']['id_pedido'] !== null){
            //consulta pedido de venda para pegar o id de integração 
            $nfe = $this->omieServices->consultaPedidoErp($omie, $decoded['event']['id_pedido']);
        }else{
            
            $nfse = $this->omieServices->consultaNotaServico($omie, $decoded['event']['id_os']);            

        }
        
        $invoicing->authorId = $decoded['author']['userId'];//Id de quem faturou
        $invoicing->authorName = $decoded['author']['name'];//nome de quem faturou
        $invoicing->authorEmail = $decoded['author']['email'];//email de quem faturou
        $invoicing->appKey = $decoded['appKey'];//id do app que faturou (base de faturamento)
        $invoicing->acao = $decoded['event']['acao']; // etapa do processo 60 = faturado
        $invoicing->status = ($invoicing->acao === 'autorizada') ? '🟢' : '🔴';
        $invoicing->ambiente = $decoded['event']['ambiente'] ?? null; // descrição da etapa 
        $invoicing->cidade = $decoded['event']['cidade'] ?? null; // cidade da prestação de serviço NFSe 
        $invoicing->codVerificacao = $decoded['event']['cod_verif'] ?? null; // cidade da prestação de serviço NFSe 
        $invoicing->danfe = $decoded['event']['danfe'] ?? null; // descrição da etapa 
        $invoicing->dataEmissao = $decoded['event']['data_emis'] ?? null; // data do faturamento
        $invoicing->empresaCnpj = $decoded['event']['empresa_cnpj'] ?? null; // hora do faturamento
        $invoicing->empresaIe = $decoded['event']['empresa_ie'] ?? null; // Id do Cliente Omie
        $invoicing->empresaIm = $decoded['event']['empresa_im'] ?? null; // Inscrição Municipal da empresa NFSe
        $invoicing->empresaUf = $decoded['event']['empresa_uf'] ?? null; // Valor Faturado
        $invoicing->horaEmissao = $decoded['event']['hora_emis'] ?? null; // Valor Faturado
        $invoicing->idNf = $decoded['event']['id_nf']; // Valor Faturado
        $invoicing->idPedido = $decoded['event']['id_pedido'] ?? null; // Id do Pedido Omie
        $invoicing->idOs = $decoded['event']['id_os'] ?? null; // Id do Pedido Omie
        $invoicing->chaveNfe = $decoded['event']['nfe_chave'] ?? null; // chave nfe
        $invoicing->nfeDanfe = $decoded['event']['nfe_danfe'] ?? null; // danfe
        $invoicing->nfeXml = $decoded['event']['nfe_xml'] ?? null; //xml nfe
        $invoicing->nfseXml = $decoded['event']['nfse_xml'] ?? null; // xml nfse
        $invoicing->numNfe = $decoded['event']['numero_nf'] ?? null; // numero nfe
        $invoicing->numNfse = $decoded['event']['numero_nfs'] ?? null; // numero nfse
        $invoicing->numOs = $decoded['event']['numero_os'] ?? null; // numero OS
        $invoicing->numRps = $decoded['event']['numero_rps'] ?? null; // numero RPS
        $invoicing->serie = $decoded['event']['serie'] ?? $decoded['event']['serie_nfs']; // Valor Faturado
        $invoicing->todas = $nfse['nfseEncontradas'] ?? 'pegar os dados da nfe';
        $invoicing->valor = $nfse['nfseEncontradas'][0]['Cabecalho']['nValorNFSe'] ?? 'pegar os dados da nfe';
        $invoicing->cnpjDestinatario = $nfse['nfseEncontradas'][0]['Cabecalho']['cCNPJDestinatario'] ?? $nfse['nfeEncontradas'][0]['Cabecalho']['cCNPJDestinatario'] ?? null;
        $invoicing->cpfDestinatario = $nfse['nfseEncontradas'][0]['Cabecalho']['cCPFDestinatario'] ?? $nfse['nfeEncontradas'][0]['Cabecalho']['cCPFDestinatario'] ?? null;
           
        $omieApp = $this->omieServices->getOmieApp();
   
        $omie = new stdClass();
        $omie->appKey = $omieApp['app_key'];
        $omie->appSecret = $omieApp['app_secret'];

        if($invoicing->idPedido !== null){
            //consulta pedido de venda para pegar o id de integração 
            $invoicing->pedido = $this->omieServices->consultaPedidoErp($omie, $invoicing->idPedido);
        }else{
            $invoicing->os = $this->omieServices->consultaOSErp($omie, $invoicing->idOs);

            if(isset($invoicing->os['InformacoesAdicionais']) && !empty($invoicing->os['InformacoesAdicionais'])){
          
                $invoicing->nContrato = $invoicing->os['InformacoesAdicionais']['cNumContrato']; //$this->omieServices->consultaContrato($omie, $nContrato);
            }
        }

        $invoicing->idPedidoInt = $invoicing->pedido['pedido_venda_produto']['cabecalho']['codigo_pedido_integracao'] ?? $invoicing->os['Cabecalho']['cCodIntOS'];
        $invoicing->baseFaturamento = $omieApp['app_name'];
        
        return $invoicing;

    }
    //REcibo de venda
        public function createOrderInvoicedObject($args):object
    {
        $decoded = $args['body'];//decodifica o json em array
        $receipt = new stdClass();//monta objeto da nota fiscal

        $omieApp = $this->omieServices->getOmieApp();
   
        $omie = new stdClass();
        $omie->appKey = $omieApp['app_key'];
        $omie->appSecret = $omieApp['app_secret'];

        $receipt->authorId = $decoded['author']['userId'];//Id de quem faturou
        $receipt->authorName = $decoded['author']['name'];//nome de quem faturou
        $receipt->authorEmail = $decoded['author']['email'];//email de quem faturou
        $receipt->appKey = $decoded['appKey'];//id do app que faturou (base de faturamento)
        $receipt->faturada = $decoded['event']['faturada']; // etapa do processo 60 = faturado
        $receipt->status = ($receipt->faturada === 'S') ? '🟢' : '🔴';
        $receipt->dataFaturamento = $this->current ?? null; // descrição da etapa 
        $receipt->dataPrevisaoFaturamento = $decoded['event']['dataPrevisao'] ?? null; // data do faturamento
        $receipt->etapa = $decoded['event']['etapa'] ?? null; // hora do faturamento
        $receipt->clientId = $decoded['event']['idCliente'] ?? null; // Id do Cliente Omie
        $receipt->idOS = $decoded['event']['idOrdemServico'] ?? null; // Inscrição Municipal da empresa NFSe
        $receipt->idPedido = $decoded['event']['idPedido'] ?? null;
        $receipt->numOS = $decoded['event']['numeroOrdemServico'] ?? null; // Valor Faturado

        $receipt->numReceipt = $decoded['event']['numeroRecibo'] ?? null; // Valor Faturado
        $receipt->amountOS = $decoded['event']['valorOrdemServico']; // Valor Faturado
        $receipt->numPedidoCliente = $decoded['event']['numeroPedidoCliente']; // Valor Faturado
                   
        $omieApp = $this->omieServices->getOmieApp();
   
        $omie = new stdClass();
        $omie->appKey = $omieApp['app_key'];
        $omie->appSecret = $omieApp['app_secret'];

        $client = new stdClass();
        $client->codigoClienteOmie = $decoded['event']['idCliente'];

        $receipt->client = $this->omieServices->getClientById($client);
        $receipt->docDestinatario = $receipt->client['cnpj_cpf'] ?? null;


        if($receipt->idPedido !== null){
            //consulta pedido de venda para pegar o id de integração 
            $receipt->pedido = $this->omieServices->consultaPedidoErp($omie, $receipt->idPedido);
        }else{
            $receipt->os = $this->omieServices->consultaOSErp($omie, $receipt->idOS);

            if(isset($receipt->os['InformacoesAdicionais']) && !empty($receipt->os['InformacoesAdicionais'])){
          
                $receipt->nContrato = $receipt->os['InformacoesAdicionais']['cNumContrato']; //$this->omieServices->consultaContrato($omie, $nContrato);
            }
        }

        $receipt->idPedidoInt = $receipt->pedido['pedido_venda_produto']['cabecalho']['codigo_pedido_integracao'] ?? $receipt->os['Cabecalho']['cCodIntOS'];
        $receipt->baseFaturamento = $omieApp['app_name'];

        return $receipt;

    }

    public function getProductStructureERP($args):array|null
    {

        $decoded = $args['body'];
        
        $omieApp = $this->omieServices->getOmieApp();
       
        $url = 'https://app.omie.com.br/api/v1/geral/malha/';
        $array = [
            'app_key' =>   $omieApp['app_key'],
            'app_secret' => $omieApp['app_secret'] ,
            'call' => 'ConsultarEstrutura',
            'param'=>[
                [
                    'idProduto'=> $decoded['event']['codigo_produto'],
                ]
            ],
        ];

        $json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->omieServices->getProductStructure($json, $url);
        
    }

    public function createProductPloomesPartsFromErpObject(array $structure, object $ploomesServices, array $pProduct): array
    {
        $parts = [];
        $items = $structure['itens'];
       
        if(isset($pProduct['Parts']) && !empty($pProduct['Parts'])){

            $codigosOmie = array_column($items, 'codProdMalha');
            $codigosPloomes = [];

            $idParts = [];
            foreach ($pProduct['Parts'] as $part) {
               
                if (isset($part['ProductPart']['Code'])) {
                     $idParts[$part['ProductPart']['Code']] = $part['Id'];//usado para alterar caso já exista vículo no ploomes
                    $codigosPloomes[] = $part['ProductPart']['Code'];
                }
            }
            //códigos que não estão cadastrados no vinculo de produtos
            $codigosNaoCadastrados = array_diff($codigosOmie, $codigosPloomes);

            //$codigosComuns = array_intersect($codigosOmie, $codigosPloomes);
            //itens na estrutura não foram cadastrados anteriormente
            if(!empty($codigosNaoCadastrados)){
                foreach($codigosNaoCadastrados as $code){
                    $pItem = $ploomesServices->getProductByCode($code);
                    if(isset($pItem) && $pItem !== null){
                        
                        foreach($items as $item){

                            if($item['codProdMalha'] === $code){
                                $array = [
                                    "ProductId" => $pProduct['Id'],
                                    "Name" => "{$pProduct['Name']} -  {$pItem['Name']}",
                                    "ProductPartId" => $pItem['Id'],
                                    "GroupPartId" => null,
                                    "ContactProductId" => null,
                                    "ListPartId" => null,
                                    "Default" => true,
                                    "MinimumQuantity" => null,
                                    "MaximumQuantity" => $item['quantProdMalha'],
                                    "DefaultQuantity" => $item['quantProdMalha'],
                                    "CurrencyId" => 1,
                                    "MinimumUnitPrice" => null,
                                    "MaximumUnitPrice" => null,
                                    "DefaultUnitPrice" => $pItem['UnitPrice'],
                                    //"GroupId" => 40019945,
                                    "Required" => true,
                                    "Editable" => true,
                    
                                ];
                                $json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                
                                $parts['success'][] = $ploomesServices->createPloomesParts($json);
                            }

                        }

                    }else{
                        $parts['erro'][] = "ERRO: Produto {$code} não cadastrado no ploomes";
                    }

                }
            }else{
                
                foreach($codigosPloomes as $code){

                    $pItem = $ploomesServices->getProductByCode($code);
          
                    if(isset($pItem) && $pItem !== null){

                        foreach($items as $item){

                            if($item['codProdMalha'] === $code){
                                $array = [
                                    "ProductId" => $pProduct['Id'],
                                    "Name" => "{$pProduct['Name']} -  {$pItem['Name']}",
                                    "ProductPartId" => $pItem['Id'],
                                    "GroupPartId" => null,
                                    "ContactProductId" => null,
                                    "ListPartId" => null,
                                    "Default" => true,
                                    "MinimumQuantity" => null,
                                    "MaximumQuantity" => $item['quantProdMalha'],
                                    "DefaultQuantity" => $item['quantProdMalha'],
                                    "CurrencyId" => 1,
                                    "MinimumUnitPrice" => null,
                                    "MaximumUnitPrice" => null,
                                    "DefaultUnitPrice" => $pItem['UnitPrice'],
                                    //"GroupId" => 40019945,
                                    "Required" => true,
                                    "Editable" => true,
                    
                                ];
                                $json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                                $idPart = $idParts[$code];
                                                            
                                $parts['success'][] = $ploomesServices->updatePloomesParts($json, $idPart);
                            }

                        }

                    }else{
                        $parts['erro'][] = "ERRO: Produto {$code} não cadastrado no ploomes";
                    }

                    

                }
                // throw new WebhookReadErrorException('Estrutura de Produtos já cadastrada', 500);
            }


        }else{
            
            foreach ($items as $item){
                $pItem = $ploomesServices->getProductByCode($item['codProdMalha']);
                if(isset($pItem) && $pItem !== null){

                    $array = [
                        "ProductId" => $pProduct['Id'],
                        "Name" => "{$pProduct['Name']} -  {$pItem['Name']}",
                        "ProductPartId" => $pItem['Id'],
                        "GroupPartId" => null,
                        "ContactProductId" => null,
                        "ListPartId" => null,
                        "Default" => true,
                        "MinimumQuantity" => null,
                        "MaximumQuantity" => $item['quantProdMalha'],
                        "DefaultQuantity" => $item['quantProdMalha'],
                        "CurrencyId" => 1,
                        "MinimumUnitPrice" => null,
                        "MaximumUnitPrice" => null,
                        "DefaultUnitPrice" => $pItem['UnitPrice'],
                        //"GroupId" => 40019945,
                        "Required" => true,
                        "Editable" => true,
        
                    ];

                    $json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    $parts['success'][] = $ploomesServices->createPloomesParts($json);
                }else{
                    
                    $omieApp = $this->omieServices->getOmieApp();
       
                    $url = 'https://app.omie.com.br/api/v1/geral/produtos/';

                    $array = [
                        'app_key' =>   $omieApp['app_key'],
                        'app_secret' => $omieApp['app_secret'] ,
                        'call' => 'ConsultarProduto',
                        'param'=>[
                            [
                                'codigo_produto'=> $item['idProdMalha'],
                            ]
                        ],
                    ];
                 

                    $json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $omieProduct = $this->omieServices->getProductById($json, $url);
                                 
                    $product = $this->createObjectCrmProductFromErpProduct($omieProduct, $ploomesServices);
                    
                    $jsonProduct = $this->createPloomesProductFromErpObject($product, $ploomesServices);
                    $newProductId = $ploomesServices->createPloomesProduct($jsonProduct);
                    $newProduct = $ploomesServices->getProductById($newProductId);
                    
                    if($newProduct){
                        $array = [
                            "ProductId" => $pProduct['Id'],
                            "Name" => "{$pProduct['Name']} -  {$newProduct['Name']}",
                            "ProductPartId" => $newProduct['Id'],
                            "GroupPartId" => null,
                            "ContactProductId" => null,
                            "ListPartId" => null,
                            "Default" => true,
                            "MinimumQuantity" => null,
                            "MaximumQuantity" => $item['quantProdMalha'],
                            "DefaultQuantity" => $item['quantProdMalha'],
                            "CurrencyId" => 1,
                            "MinimumUnitPrice" => null,
                            "MaximumUnitPrice" => null,
                            "DefaultUnitPrice" => $newProduct['UnitPrice'],
                            //"GroupId" => 40019945,
                            "Required" => true,
                            "Editable" => true,
                    ];

                    $json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    $parts['success'][] = $ploomesServices->createPloomesParts($json);
                    }else{
                        $parts['erro'][] = "ERRO: Produto {$item['descrProdMalha']} não cadastrado no ploomes";
                    }

                }

            }
        }

        return $parts;

 
        
        
    }

    public function productStructure(array $args, object $ploomesServices, array $pProduct)
    {
        //03 - Produto em Processo / 04 - Produto Acabado
        if($args['body']['event']['tipoItem'] === '03' ||  $args['body']['event']['tipoItem'] === '04')
        {
            $structure = self::getProductStructureERP($args);
         
            if($structure){

                $parts = $this->createProductPloomesPartsFromErpObject($structure, $ploomesServices, $pProduct);

                if(!isset($parts['success'] ) || empty($parts['success'])){
                    $frase = '';
                    foreach($parts['erro'] as $erro){
                        $frase .= $erro . '<br>';
                    }
                    throw new WebhookReadErrorException($frase, 500);
                }elseif(!empty($parts['erro'] )){

                    $message['success'] = 'Integração concluída com sucesso! Estrutura do produto Ploomes id: '.$pProduct['Id'].' vinculada parcialmente. Total de '. count($parts['success']) .' Produtos vinculados e '. count($parts['erro']) .'produtos com erro. '.PHP_EOL;

                    $frase = '';
                    foreach($parts['erro'] as $erro){
                        $frase .= $erro . '<br>';
                    }

                    $message['success'] .= $frase;

                }else{

                     $message['success'] = 'Integração concluída com sucesso! Estrutura do produto Ploomes id: '.$pProduct['Id'].' vinculada. Total de '. count($parts['success']);

                }                

            }else{

                $message['success'] = 'Integração concluída com sucesso! Produto Ploomes id: '.$pProduct['Id'].' alterado no Ploomes CRM com sucesso. Produto sem estrutura cadastrada no ERP em: ';
            }

        }else{
            $message['success'] = 'Integração concluída com sucesso! Produto Ploomes id: '.$pProduct['Id'].' alterado no Ploomes CRM com sucesso. Produto do tipo '.$args['body']['event']['tipoItem'].' não contém estrutura: ';
        }

        return $message;
    }

    public function getDeptoByName(object $omie)
    {
        $url = "https://app.omie.com.br/api/v1/geral/departamentos/";
        $call = "ListarDepartamentos";
//         {
//     "app_key": "{{appk-rhoma}}",
//     "app_secret": "{{secrets-rhoma}}",
//     "call": "ListarDepartamentos",
//     "param": [
//         {
//             "pagina": 1,
//             "registros_por_pagina": 50
//         }
//     ]
// }
    $array = [
        'app_key'=> $omie->appKey,
        'app_secret'=>$omie->appSecret,
        'call'=>$call,
        'param'=>[
            [
                'pagina'=>1,
                'registros_por_pagina'=>50
            ]
        ]
            ];
    $json = json_encode($array);   
    
    return $this->omieServices->listDeptos($json, $url);

    }
}