<?php

namespace src\handlers;

use Exception;
use PDOException;
use src\exceptions\DealNaoEncontradoBDException;
use src\exceptions\EstagiodavendaNaoAlteradoException;
use src\exceptions\FaturamentoNaoCadastradoException;
use src\exceptions\InteracaoNaoAdicionadaException;
use src\exceptions\NotaFiscalNaoCadastradaException;
use src\exceptions\NotaFiscalNaoCanceladaException;

use src\exceptions\PedidoNaoEncontradoOmieException;
use src\exceptions\WebhookReadErrorException;
use src\functions\DiverseFunctions;
use src\models\Deal;
use src\models\Homologacao_invoicing;
use src\models\Manospr_invoicing;
use src\models\Manossc_invoicing;
use src\models\Webhook;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use stdClass;

class InvoiceHandler
{
    private $current;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;

    public function __construct(PloomesServices $ploomesServices, OmieServices $omieServices, DatabaseServices $databaseServices)
    {
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
        $this->ploomesServices = $ploomesServices;
        $this->omieServices = $omieServices;
        $this->databaseServices = $databaseServices;
    }

    public function saveInvoiceHook($json){

        $decoded = json_decode($json, true);
          
        //infos do webhook
        $webhook = new Webhook();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity'] ?? 'Invoices';
        $webhook->origem = (!isset($decoded['Entity']))?'Omie':'Ploomes';
        //salva o hook no banco
        return ($id = $this->databaseServices->saveWebhook($webhook)) ? ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current] : 0;
    }

    //LÊ O WEBHOOK E COM A NOTA FATURADA
    public function startProcess($json)
    {   
        //data atual
        $current = $this->current;
        // Array de retorno
        $message = [];
        
        $decoded = json_decode($json, true);//decodifica o json em array
        $invoicing = new stdClass();//monta objeto da nota fiscal
        $invoicing->authorId = $decoded['author']['userId'];//Id de quem faturou
        $invoicing->authorName = $decoded['author']['name'];//nome de quem faturou
        $invoicing->authorEmail = $decoded['author']['email'];//email de quem faturou
        $invoicing->appKey = $decoded['appKey'];//id do app que faturou (base de faturamento)
        $invoicing->etapa = $decoded['event']['etapa']; // etapa do processo 60 = faturado
        $invoicing->etapaDescr = $decoded['event']['etapaDescr'] ?? null; // descrição da etapa 
        $invoicing->dataFaturado = $decoded['event']['dataFaturado'] ?? null; // data do faturamento
        $invoicing->horaFaturado = $decoded['event']['horaFaturado'] ?? null; // hora do faturamento
        $invoicing->idCliente = $decoded['event']['idCliente']; // Id do Cliente Omie
        $invoicing->idPedido = $decoded['event']['idPedido'] ?? $decoded['event']['idOrdemServico']; // Id do Pedido Omie
        $invoicing->numeroPedido = $decoded['event']['numeroPedido'] ?? $decoded['event']['numeroOrdemServico']; // Numero do pedido
        $invoicing->valorPedido = $decoded['event']['valorPedido'] ?? $decoded['event']['valorOrdemServico']; // Valor Faturado

        $omie = new stdClass;//monta um objeto com informações a enviar ao omie
        $omie->appKey = $decoded['appKey'];
        $omie->codCliente = $decoded['event']['idCliente'];
        //verifica se tem informações no array e se a nota está faturada (etapa 60)
        if(empty($decoded) && $decoded['event']['etapa'] != 60){
            throw new WebhookReadErrorException('Não foi possível ler o Webhook ou não existe nota fiscal emitida! - '. $current,1020);
        }    
        

        //pega a chave secreta para a base de faturamento vinda no faturamento
        // switch($decoded['appKey']){
        //     case 4194053472609:               
        //         $omie->appSecret = $_ENV['SECRETS_DEMO'];
        //         break;
        //     }
        switch($decoded['appKey']){
            case 1120581879417:               
                $omie->appSecret = $_ENV['SECRETS_EPT'];
                $invoicing->baseFaturamentoTitle = 'Engeparts';
                break;
            case 146532853467:               
                $omie->appSecret = $_ENV['SECRETS_GTC'];
                $invoicing->baseFaturamentoTitle = 'Gamatermic';
                break;
            case 146571186762:               
                $omie->appSecret = $_ENV['SECRETS_SMN'];
                $invoicing->baseFaturamentoTitle = 'Semin';
                break;
            case 171250162083:               
                $omie->appSecret = $_ENV['SECRETS_GSU'];
                $invoicing->baseFaturamentoTitle = 'GSU';
                break;
            }
            

        // busca o pedido através id do pedido no omie retorna exceção se não encontra 
        // if(!$pedidoOmie = $this->omieServices->consultaPedidoOmie($omie, $decoded['event']['idPedido'])){throw new WebhookReadErrorException('Pedido '.$decoded['event']['idPedido'].' não encontrado no Omie ERP',1023);     

        if($decoded['topic'] === 'VendaProduto.Faturada')
        {
            //consulta a nota fiscal no omie para retornar o numero da nota.            
            $nfe = $this->omieServices->consultaNotaOmie($omie, $decoded['event']['idPedido']);
            ($nfe) ? $nfe : throw new Exception('NF-e não encontrada (ou não foi faturada), para o pedido: '.$decoded['event']['idPedido'], 1022); 
            $invoicing->nNF =intval($nfe);
            $content ='NF-e ('. $invoicing->nNF .') emitida no Omie ERP na base: '.$invoicing->baseFaturamentoTitle;
            
        }elseif($decoded['topic'] === 'OrdemServico.Faturada')
        {
            //consulta a nota fiscal no omie para retornar o numero da nota.            
            $nfse = $this->omieServices->consultaNotaServico($omie, $decoded['event']['idOrdemServico']);
            ($nfse) ? $nfse : throw new Exception('NFS-e não encontrada (ou não foi faturada), OS número : '.$decoded['event']['idOrdemServico'], 1022);  
            $invoicing->nNF =intval($nfse);
            $content ='NFS-e ('. intval($nfse).') emitida no Omie ERP na base: '.$invoicing->baseFaturamentoTitle;
        }

        //busca o cnpj do cliente para consultar o contact id no ploomes
        $cnpjClient = $this->omieServices->clienteCnpjOmie($omie);

        if (!empty($cnpjClient)){
            //busca o contact_id artravés do cnpj do cliente do omie
            $contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjClient);

            $frase = 'Interação de nota fiscal adicionada no cliente '. $contactId .' em: '.$current;

            //monta a mensagem para retornar ao ploomes
            $msg = [
                'ContactId'=>  $contactId,
                'TypeId'=> 1,
                'Title'=> 'Nota Fiscal emitida',
                'Content'=> $content,
            ];
           
            //Cria interação no card específico 
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))? $message['success'] = $frase : throw new WebhookReadErrorException('Não foi possível adicionar a interação de nota fiscal emitida no card, possívelmente a venda foi criada direto no omie - '.$current,1025);
            //muda a etapa da venda específica para NF-Emitida stage Id 40042597

        }
        else{

            //RETORNA excessão caso não tenha o $deal
            throw new WebhookReadErrorException('CNPJ do cliente não encontrado. Não foi possível consultra a nota fiscal.',1024);
        }
        
        return $message;
    }

    //RECEBE O WEBHOOK DE NOTA CANCELADA, CANCELA NO BANCO E ENVIA INTERAÇÃO NO PLOOMES CRM
    // public function isDeletedInvoice($json)
    // {
    //     $message = [];
    //     $current = date('d/m/Y H:i:s');
    //     $decoded = json_decode($json, true);
    //     $omie = new stdClass;//monta um objeto com informações a enviar ao omie
    //     $omie->appKey = $decoded['appKey'];
    //     //$omie->codCliente = $decoded['event']['idCliente'];


    //     if( $decoded['topic'] !== "NFe.NotaCancelada" && $decoded['event']['acao'] !== 'cancelada'){
    //         throw new WebhookReadErrorException('Não foi possível ler o Webhook ou não existe nota fiscal cancelada!',1020);
    //     }
            
    //         //VERIFICA PARA QUAL BASE DE NOTAS FISCAIS SERÁ EDITADA A NOTA
    //         switch($decoded['appKey'])
    //         {
    //             case 2337978328686: //MHL    
    //                 $omie->target = 'MHL';
    //                 $target = 'Manos Homologação';
    //                 break;
                    
    //             case 2335095664902: // MPR
    //                 $omie->target = 'MPR'; 
    //                 $target = 'Manos-PR';
    //                 break;
                    
    //             case 2597402735928: // MSC
    //                 $omie->target = 'MSC';                 
    //                 $target = 'Manos-SC';
    //                 break;           
    //         }

    //         try{
    //             $id = $this->databaseServices->isIssetInvoice($omie, $decoded['event']['id_pedido']);
    //             if(is_string($id)){
    //                 throw new NotaFiscalNaoCadastradaException('Erro ao consultar a base de dados de '.$target.'. Erro: '.$id. 'em '.$current, 1030);
    //                 }elseif(empty($id)){
    //                 throw new NotaFiscalNaoCadastradaException('Nota fiscal não cadastrada na base de dados de notas de '.$target.', ou já foi cancelada em '.$current, 1030);
    //             }else{$message['invoice']['issetInvoice'] = 'Nota fiscal do pedido '. $decoded['event']['id_pedido'] .'encontrada na base de '.$target.' em '.$current;
    //             }

    //         }catch(NotaFiscalNaoCadastradaException $e){
    //             //$message['invoice']['error']['notRegistered'] =;
    //             throw new NotaFiscalNaoCadastradaException( $e->getMessage());
    //         }
                    
    //         //altera a nota fiscal no banco para cancelada
    //         try{
    //             //Altera a nota para cancelada no banco MHL
    //             $altera = $this->databaseServices->alterInvoice($omie, $decoded['event']['id_pedido']);

    //             if(is_string($altera)){
    //                 throw new NotaFiscalNaoCanceladaException('Erro ao consultar a base de dados de '.$target.'. Erro: '.$altera. 'em '.$current, 1030);                     
    //             }

    //             $message['invoice']['iscanceled'] = 'Nota fiscal do pedido '. $decoded['event']['id_pedido'] .'cancelada com sucesso em '.$current;
                
    //         }catch(NotaFiscalNaoCanceladaException $e){          
    //             throw new NotaFiscalNaoCanceladaException($e->getMessage(), 1031);
    //         }
        
    //         //busca o contact id do cliente no P`loomes CRM através do cnpj do cliente no Omie ERP
    //         $contactId = $this->ploomesServices->consultaClientePloomesCnpj($decoded['event']['empresa_cnpj']);
    //         //monta a mensadem para atualizar o card do ploomes
    //         $msg=[
    //             'ContactId' => $contactId,
    //             'Content' => 'Nota fiscal '.intval($decoded['event']['numero_nf']).' cancelada no Omie ERP em: '.$current,
    //             'Title' => 'Nota Fiscal Cancelada no Omie ERP'
    //         ];

    //         //cria uma interação no card
    //         ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['interactionMessage'] = 'Integração de cancelamento de nota fiscal concluída com sucesso!<br> Nota Fiscal: '.intval($decoded['event']['numero_nf']).' foi cancelada no Omie ERP e interação criada no cliente id: '.$contactId.' em: '.$current : throw new InteracaoNaoAdicionadaException('Não foi possível gravar a mensagem de nota cancelada no Ploomes CRM',1032);

    //         return $message;

    // }

}