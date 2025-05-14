

    private function createNewOrder(array $decoded):object
    {

        $order = new Order();
        $order->id = $decoded['New']['Id']; //Id da order
        $order->orderNumber = $decoded['New']['OrderNumber']; // numero da venda
        $order->contactId = $decoded['New']['ContactId']; // Id do Contato,
        $order->contactName = $decoded['New']['ContactName']; // Nome do Contato no Deal
        $order->personId = $decoded['New']['PersonId']; // Id do Contato
        $order->personName = $decoded['New']['PersonName']; // nome do contato
        $order->stageId = $decoded['New']['StageId']; // Estágio
        $order->dealId = $decoded['New']['DealId']; // Id do card
        $order->createDate = $decoded['New']['CreateDate']; // data de criação da order
        $order->ownerId = $decoded['New']['OwnerId']; // Responsável
        $order->amount = $decoded['New']['Amount']; // Valor


        return $order;
    }

    private function getContactPloomesByContactId(int $id):array
    {

        $contact = $this->ploomesServices->getClientById($id);

        if(!isset($contact) || empty($contact))
        {
            throw new WebhookReadErrorException('Erro ao montar pedido pra enviar ao omie: Não foi encontrado os dados do cliente no Ploomes', 500);
        }
        
        return $contact;

    }

    private function getIdCustomerOmieFromContactPloomes(array $otherProperties):array
    {

        $ids = [];
        foreach($otherProperties as $op){
            foreach($op as $k => $v){
                switch ($v) {
                    case "contact_6DB7009F-1E58-4871-B1E6-65534737C1D0":
                        $ids['IdGTC'] = $op['StringValue'];
                        break;
                    case "contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3":
                        $ids['IdEPT'] = $op['StringValue'];
                        break;
                    case "contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2":
                        $ids['IdSMN'] = $op['StringValue'];
                        break;
                    case "contact_07784D81-18E1-42DC-9937-AB37434176FB":
                        $ids['IdGSU'] = $op['StringValue'];
                        break;
                }
            }
        }
        return $ids;
    }

    private function getMailOwnerFromOrder(object $order):string
    {

        $mail = $this->ploomesServices->ownerMail($order);

        if(!isset($mail) || empty($mail))
        {
            throw new WebhookReadErrorException('Erro ao montar pedido pra enviar ao omie: Não foi encontrado o e-mail do vendedor no ploomes', 500);
        }
        
        return $mail;

    }

    private function createOmieObjectSetDetailsOrder(object $omie, object $order):object
    {

        $omie = new Omie();
        $baseFaturamentoTitle = null;

        switch ($order->baseFaturamento) {
            case '420197140':
                $baseFaturamentoTitle = 'ENGEPARTS';
                $order->idClienteOmie = $order->ids['IdEPT'];
                $omie->target = 'EPT'; 
                break;
            case '420197141':
                $baseFaturamentoTitle = 'GAMATERMIC';
                $order->idClienteOmie = $order->ids['IdGTC'];
                $omie->target = 'GTC'; 
                break;
            case '420197143':
                $baseFaturamentoTitle = 'SEMIN';
                $order->idClienteOmie = $order->ids['IdSMN'];
                $omie->target = 'SMN'; 
                break;
            case '420197142':
                $baseFaturamentoTitle = 'GSU';
                $order->idClienteOmie = $order->ids['IdGSU'];
                $omie->target = 'MHL'; 
                break;
            default:
                throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Base de faturamento inexistente, não foi possível montar dados do App Omie', 500);
        }

        $omie->baseFaturamentoTitle = $baseFaturamentoTitle;
        $omie->ncc = $_ENV["NCC_{$omie->target}"];
        $omie->appSecret = $_ENV["SECRETS_{$omie->target}"];
        $omie->appKey = $_ENV["APPK_{$omie->target}"];

        return $omie;
    }

    private function setAdditionalOrderProperties(object $order, array $customFields)
    {
        //previsão de faturamento
        $order->previsaoFaturamento =(isset($customFiels['Previsao de Faturamento']) && !empty($customFiels['Previsao de Faturamento']))? $customFiels['Previsao de Faturamento'] : date('Y-m-d');

        //template id (tipo de venda produtos ou serviços) **Obrigatório
        $order->templateId =(isset($customFields['Template Id']) && !empty($customFields['Template Id']))? $customFields['Template Id'] : $m[] = 'Erro: não foi possível identificar o tipo de venda (Produtos ou serviços)';

        //numero do pedido do cliente (preenchido na venda) localizado em pedidos info. adicionais
        $order->numPedidoCliente = (isset($customFields['Numero do Pedido do Cliente']) && !empty($customFields['Numero do Pedido do Cliente']))?$customFields['Numero do Pedido do Cliente']:null;

        $order->descricaoServico = (isset($customFields['Descricao do Servico']) && !empty($customFields['Descricao do Servico']))?htmlspecialchars_decode(strip_tags($customFields['Descricao do Servico'],'\n')):null;

        //Numero pedido de compra (id da customFieldsosta) localizado em item da venda info. adicionais
        $order->numPedidoCompra = (isset($customFields['numero pedido de compra']) && !empty($customFields['numero pedido de compra'])? $customFields['numero pedido de compra']: null); 

        //id modalidade do frete
        ((isset($customFields['id modalidade frete'])) && (!empty($customFields['id modalidade frete']) || $customFields['id modalidade frete'] === "0")) ? $order->modalidadeFrete = $customFields['id modalidade frete'] : $order->modalidadeFrete = null;
      
        //projeto ***Obrigatório
        $order->projeto = ($customFields['projeto']) ?? $m[]='Erro ao montar pedido para enviar ao Omie ERP: Não foi informado o Projeto';

        //observações da nota
        $order->notes = (isset($customFields['dados adicionais NF']) ? htmlspecialchars_decode(strip_tags($customFields['dados adicionais NF'])): null);  

        $order->idParcelamento = $customFields['codigo do parcelamento'] ?? null;

        if(!empty($m)){
            throw new WebhookReadErrorException('Erro ao preencher campos obrigatórios do pedido (Template ID ou Projeto)', 500);
        }

    }

    private function insertProjectOmie(object $omie, object $order):string
    {

        $project = $this->omieServices->insertProject($omie,  $order->projeto);

        if(isset($project['faultstring'])){
            throw new WebhookReadErrorException('Erro ao cadastrar o Projeto no Omie: ' . $project['faultstring'], 500);
        }else{
            return $project['codigo'];
        }


    }

    private function getIdVendedorOmieFromMail(object $omie, string $mail): int | null
    {
        return $this->omieServices->vendedorIdErp($omie, $mail);

    }

    private function getDetailsOrderFromPloomes(object $order):array
    {
        $arrayRequestOrder = $this->ploomesServices->requestOrder($order);

        if(!isset($arrayRequestOrder) || empty($arrayRequestOrder) )
        {
            throw new WebhookReadErrorException('Erro ao montar pedido para enviar ao Omie: Não foi encontrada a venda no Ploomes', 500);
        }

        return $arrayRequestOrder;
    }

    private function isService(object $order):bool
    {
        $type = match($order->templateId){
            '40130624' => "servicos",
            '40124278' => "produtos"
        };
        //verifica se é um serviço
        return ($type === 'servicos') ? true : false;
    }

    private function setIdItemOmie(object $order, array $customFields){

        $idItemOmie =  match(strtolower($order->baseFaturamentoTitle)){
            'gamatermic'=> $customFields['gamatermic'],
            'semin'=> $customFields['semin'],
            'engeparts'=> $customFields['engeparts'],
            'gsu'=> $customFields['gsu'],
        };

        return $idItemOmie;
    }

    private function distinctProductsServicesFromOmieOrders(array $arrayRequestOrder, bool $isService, mixed $idItemOmie, object $order):array
    {
        //separa e monta os arrays de produtos e serviços
        $productsOrder = []; 
        $det = [];  
        $det['ide'] = [];
        $det['produto'] = [];
        $opServices = [];
        $serviceOrder = [];
        $pu = [];
        $service = [];
        $produtosUtilizados = [];
        $contentServices = [];
        
        foreach($arrayRequestOrder['Products'] as $prdItem)
        {   
            foreach($prdItem['Product']['OtherProperties'] as $otherp){
                $opServices[$otherp['FieldKey']] = $otherp['ObjectValueName'] ?? 
                $otherp['BigStringValue'] ?? $otherp['StringValue'] ??  $otherp['IntegerValue'] ?? $otherp['DateTimeValue'];
            }
            //verifica se é venda de serviço 
            if($isService){
                //verifica se tem serviço com produto junto
                if($prdItem['Product']['Group']['Name'] !== 'Serviços'){
                    
                    //monts o produtos utilizados (pu)
                    $pu['nCodProdutoPU'] = $opServices[$idItemOmie];
                    $pu['nQtdePU'] = $prdItem['Quantity'];
                    
                    $produtosUtilizados[] = $pu;
                    
                }else{
                    
                    //monta o serviço
                    $service['nCodServico'] = $opServices[$idItemOmie];
                    $service['nQtde'] = $prdItem['Quantity'];
                    $service['nValUnit'] = $prdItem['UnitPrice'];
                    $service['cDescServ'] = $order->descricaoServico;
                    
                    $serviceOrder[] = $service;
                }

                $contentServices['servicos'] = $serviceOrder;
                $contentServices['produtosServicos'] = $produtosUtilizados;
            }else{

                $det['ide']['codigo_item_integracao'] = $prdItem['Id'];
                $det['produto']['quantidade'] = $prdItem['Quantity'];
                //$det['produto']['tipo_desconto'] = 'P';
                //$dicount =$prdItem['Discount'] ?? 0;
                //$det['produto']['percentual_desconto'] = number_format($dicount, 2, ',', '.');
                $det['produto']['valor_unitario'] = $prdItem['UnitPrice'];
                $det['produto']['codigo_produto'] = $opServices[$idItemOmie];

                $det['inf_adic'] = [];
                $det['inf_adic']['numero_pedido_compra'] = $order->numPedidoCompra;
                $det['inf_adic']['item_pedido_compra'] =$prdItem['Ordination']+1;

                $productsOrder[] = $det;
            }
        }

        return ['products'=>$productsOrder, 'services'=>$contentServices];
    }

    private function createStructureOrderOmie(object $order, object $omie, array $productsOrder):array
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
        $informacoes_adicionais['codproj']= $omie->codProjeto ?? null;
        $informacoes_adicionais['dados_adicionais_nf'] = $order->notes;

        //observbacoes
        $observacoes =[];
        $observacoes['obs_venda'] = $order->notes;

        $newPedido = [];//array que engloba tudo
        $newPedido['cabecalho'] = $cabecalho;
        $newPedido['det'] = $productsOrder;
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

            return $newPedido;       
        }else{
            throw new WebhookReadErrorException('Erro ao montar o pedido para enviar ao Omie: Estrutura de pedido com preblema',500);
        }

    }

    private function createRequestNewOrder(object $omie, object $order, array $pedido):array
    {
        $incluiPedidoOmie = $this->omieServices->criaPedidoErp($omie, $order, $pedido);

        //verifica se criou o pedido no omie
        if(isset($incluiPedidoOmie['codigo_status']) && $incluiPedidoOmie['codigo_status'] == "0") 
        {
            $message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoOmie['numero_pedido']).' e mensagem enviada com sucesso em: '.$current;

            //monta a mensagem para atualizar o card do ploomes
            $msg=[
                'DealId' => $order->dealId,
                'Content' => 'Venda ('.intval($incluiPedidoOmie['numero_pedido']).') criada no OMIE via API BICORP na base '.$order->baseFaturamentoTitle.'.',
                'Title' => 'Pedido Criado'
            ];

            //cria uma interação no card
            ($this->createInteractionPloomes(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoOmie['numero_pedido']).' e mensagem enviada com sucesso em: '.$current
            :throw new WebhookReadErrorException('Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoOmie['numero_pedido']).'Porém houve um erro ao enviar a mensagem ao Ploomes. '.$current);

            $message['winDeal']['returnPedidoOmie'] ='Pedido criado no Omie via BICORP INTEGRAÇÃO pedido numero: '.intval($incluiPedidoOmie['numero_pedido']);

        }else{ 

            $deleteProject = $this->deleteProjectOmie($omie);
            
            //monta a mensagem para atualizar o card do ploomes
            $msg=[
                'DealId' => $order->dealId,
                'Content' => 'Pedido não pode ser criado no OMIE ERP. '.$incluiPedidoOmie['faultstring'],
                'Title' => 'Erro na integração'
            ];
        
            //cria uma interação no card
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' - '.$incluiPedidoOmie['faultstring']. 'Mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);

            $message['winDeal']['error'] ='Não foi possível gravar o peddido no Omie! '. $incluiPedidoOmie['faultstring'] . $deleteProject;
           
        }  
        return $message;

    }

    private function createInteractionPloomes(string $msg):bool{

        if($this->ploomesServices->createPloomesIteraction($msg)){
            return true;
        }else{
           return false;
        }

    }

    private function createStructureOSOmie(object $omie, object $os, array $contentOrder):array
    {

        $cabecalho = [];//cabeçalho do pedido (array)
        $cabecalho['nCodCli'] = $os->idClienteOmie;//int
        $cabecalho['cCodIntOS'] = 'VEN_SRV/'.$os->id;//string
        $cabecalho['dDtPrevisao'] = DiverseFunctions::convertDate($os->previsaoFaturamento);//string
        $cabecalho['cEtapa'] = '10';//string
        $cabecalho['cCodParc'] =  $os->idParcelamento ?? '000';//string'qtde_parcela'=>2
        $cabecalho['nQtdeParc'] = 3;//string'qtde_parcela'=>2
        $cabecalho['nCodVend'] = $os->codVendedorOmie;//string'qtde_parcela'=>2

        $InformacoesAdicionais = []; //informações adicionais por exemplo codigo_categoria = 1.01.02 p/ serviços
        $InformacoesAdicionais['cCodCateg'] = '1.01.02';//string
        $InformacoesAdicionais['nCodCC'] = $omie->ncc;//int
        $InformacoesAdicionais['cDadosAdicNF'] = $os->notes;//string
        $InformacoesAdicionais['cNumPedido']=$os->numPedidoCliente ?? "0";
        $InformacoesAdicionais['nCodProj']= $omie->codProjeto;

        $pu = [];

        $pu['cAcaoProdUtilizados'] = 'EST';
        $pu['produtoUtilizado'] = $contentOrder['produtosUtilizados'];
    
        $newOS = [];//array que engloba tudo
        $newOS['cabecalho'] = $cabecalho;
        $newOS['InformacoesAdicionais'] = $InformacoesAdicionais;
        $newOS['servicosPrestados'] = $contentOrder['servicos'];
        $newOS['produtosUtilizados'] = $pu;

        if(
            !empty($newPedido['cabecalho']) || !empty($newPedido['InformacoesAdicionais']) ||
            !empty($newPedido['servicosPrestados']) 
        )
        {

            return $newOS;       
        }else{
            throw new WebhookReadErrorException('Erro ao montar a OS para enviar ao Omie: Estrutura de pedido com preblema',500);
        }
 

    }

    private function createRequestOS(object $omie, object $os, array $structureOS):array
    {
        //inclui a ordem de serviço
        $incluiOS = $this->omieServices->criaOSErp($omie, $os, $structureOS);

        /**
         * array de retorno da inclusão de OS
        * [cCodIntOS] => SRV/404442017
        * [nCodOS] => 6992578495
        * [cNumOS] => 000000000000018
        * [cCodStatus] => 0
        * [cDescStatus] => Ordem de Serviço adicionada com sucesso!
        */

        //se incluiu a OS
        if(isset($incluiOS['cCodStatus']) && $incluiOS['cCodStatus'] == "0"){

            $message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).' em: '.$current ;

            //monta mensagem pra enviar ao ploomes
            $msg=[
                'DealId' => $os->dealId,
                'Content' => 'Ordem de Serviço ('.intval($incluiOS['cNumOS']).') criada no OMIE via API BICORP na base '.$os->baseFaturamentoTitle.'.',
                'Title' => 'Ordem de Serviço Criada'
            ];

            $message['winDeal']['incluiOS']['Success'] = $incluiOS['cDescStatus']. 'Numero: ' . intval($incluiOS['cNumOS']);
            //cria uma interação no card
            ($this->createInteractionPloomes(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).' e mensagem enviada com sucesso em: '.$current:throw new WebhookReadErrorException('Integração concluída com sucesso!<br> Pedido Ploomes: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).'Porém houve um erro ao enviar a mensagem ao Ploomes. '.$current,500);
                
            
            
        }else{
                        
            $deleteProject = $this->deleteProjectOmie($omie);
            $message['winDeal']['error'] ='Não foi possível gravar a Ordem de Serviço no Omie! '. $deleteProject;
            
            $msg=[
                'DealId' => $os->dealId,
                'Content' => 'Ordem de Serviço não pode ser criado no OMIE ERP. '.$incluiOS['faultstring'],
                'Title' => 'Erro na integração'
            ];
            
            //cria uma interação no card

            ($this->createInteractionPloomes(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis, pedido: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' - '.$incluiOS['faultstring']. 'Mensagem enviada com sucesso em: '.$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);
            

        }

        return $message;

    }

    private function deleteProjectOmie(object $omie):string
    {
       $del = $this->omieServices->deleteProject($omie);

       if($del['codigo'] === "0"){
          return $del['descricao'];

        }else{
            return $del['faultstring'];
        }
    }
