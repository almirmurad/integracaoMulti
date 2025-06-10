<?php

namespace src\handlers;

use src\exceptions\WebhookReadErrorException;
use src\functions\DiverseFunctions;
use src\models\Webhook;
use src\services\DatabaseServices;
use src\services\PloomesServices;
use src\contracts\ErpFormattersInterface;
use src\functions\InvoicesFunctions;
class InvoiceHandler
{
    private ErpFormattersInterface $formatter;
    private PloomesServices $ploomesServices;
    private DatabaseServices $databaseServices;
    private $current;
    
    public function __construct(PloomesServices $ploomesServices, DatabaseServices $databaseServices, ErpFormattersInterface $formatter)
    {       
        $this->formatter = $formatter;
        $this->ploomesServices = $ploomesServices;
        $this->databaseServices = $databaseServices;
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
    }

    public function saveInvoiceHook($json, $idUser){

        $decoded = json_decode($json, true);
        
        $origem = (!isset($decoded['Entity']))?'Erp':'Ploomes';  
        //infos do webhook
        $webhook = new Webhook();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->user_id = $idUser;
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity'] ?? 'Invoices';
        $webhook->origem = $origem;
        //salva o hook no banco
        return ($id = $this->databaseServices->saveWebhook($webhook)) ? ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current] : 0;
    }

    //LÊ O WEBHOOK E COM A NOTA FATURADA
    public function startProcess($args)
    {   
        $action = DiverseFunctions::findAction($args);
        $current = $this->current;
        // Array de retorno
        $message = [];

        $invoicing = $this->formatter->createInvoiceObject($args);
        
        if(empty($action['action'])){
            throw new WebhookReadErrorException('Não foi possível ler o Webhook ou não existe nota fiscal emitida! - '. $current,1020);
        } 

        if($action['action'] === 'nfeAutorizada')
        { 
            $content ='NF-e ('. intval($invoicing->numNfe) .') emitida no ERP na base: '.$invoicing->baseFaturamento; 
        }
        elseif($action['action'] === 'nfseAutorizada')
        {            
            $content ='NFS-e ('. intval($invoicing->numNfse) .') emitida no ERP na base: '.$invoicing->baseFaturamento;
        }
        
        if (!empty($invoicing->empresaCnpj))
        {
            //busca o contact_id artravés do cnpj do cliente do ERP mas antes precisa tirar pontos e barra 
            $cnpjCpf = DiverseFunctions::limpa_cpf_cnpj($invoicing->empresaCnpj);
            $contactId = $this->ploomesServices->consultaClientePloomesCnpj($cnpjCpf);

            if(!isset($contactId)){
                throw new WebhookReadErrorException('Não foi possível encontrar o cliente no Ploomes - '.$current,500);                
            }

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

            $alterStage = InvoicesFunctions::alterStageInvoiceIssue($invoicing, $this->ploomesServices);
            if(!$alterStage)
            {
               throw new WebhookReadErrorException('Não foi possível alterar o estágio da venda - '.$current, 500);
            }     
        }
        else{
            //RETORNA excessão caso não tenha o cliente
            throw new WebhookReadErrorException('CNPJ do cliente não encontrado.', 500);
        }
        
        return $message;
    }

}