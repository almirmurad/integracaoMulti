<?php

namespace src\services;

use Exception;
use PDOException;
use src\contracts\DatabaseManagerInterface;
use src\exceptions\FaturamentoNaoCadastradoException;
use src\exceptions\PedidoDuplicadoException;
use src\exceptions\WebhookReadErrorException;
use src\models\Cliente;
use src\models\Deal;
use src\models\Homologacao_invoicing;
use src\models\Homologacao_order;
use src\models\Log;
use src\models\Manospr_invoicing;
use src\models\Manospr_order;
use src\models\Manossc_invoicing;
use src\models\Manossc_order;
use src\models\Omie_base;
use src\models\Ploomes_base;
use src\models\Tenancie;
use src\models\Tenancy;
use src\models\Vhost;
use src\models\Webhook;

class DatabaseServices implements DatabaseManagerInterface{
    //SALVA NO BANCO DE DADOS AS INFORMAÇÕES DO WEBHOOK
    public function saveWebhook(object $webhook):string
    {   
        try{

            $id=Webhook::insert(
                [
                    'entity'=>$webhook->entity,
                    'json'=>$webhook->json,
                    'status'=>$webhook->status,
                    'user_id'=>$webhook->user_id,
                    'result'=>$webhook->result,
                    'origem'=>$webhook->origem,
                    'created_at'=>date("Y-m-d H:i:s"),
                    ]
            )->execute();
            
            return $id;

        }catch(PDOException $e){
            throw new WebhookReadErrorException('Erro ao gravar o webhook na base de dados: '.$e->getMessage(). 'Data: '.date('d/m/Y H:i:s'), 552);
        }
        
    }
    //BUSCA NO BANCO DE DADOS INFORMAÇÕES DO WEBHOOK A SER PROCESSADO.
    public function getWebhook($status, $entity){
        try{
            $hook = Webhook::select()->where('status', $status)->where('entity',$entity)->orderBy('created_at')->one();
            return (!$hook ? throw new WebhookReadErrorException('Não existem '.$entity.' pendentes a serem processados no momento. Data: '.date('d/m/Y H:i:s').PHP_EOL, 552) : $hook);
            return $hook;
        }catch(PDOException $e){
            throw new WebhookReadErrorException('Erro ao buscar o webhook na base de dados: '.$e->getMessage(). 'Data: '.date('d/m/Y H:i:s'), 552);
        }
      
    }
    //ALTERA O STATUS DO WEBHOOK
    public function alterStatusWebhook($id, $statusId){
        // print 'id do webhook no bd = '.$id. PHP_EOL;
        // print 'Id do status para mudar na base = '.$statusId. PHP_EOL;

        $status = [];
        $status[1] = 'Recebido';
        $status[2] = 'Processando';
        $status[3] = 'Sucesso';
        $status[4] = 'ERRO';
        $status[5] = 'Parcial';
        

        try{
            //Cliente::update()
            Webhook::update()
            ->set('status', $statusId)
            ->set('result', $status[$statusId])
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->where('id',$id)
            ->execute();

            return true;
            
        }catch(PDOException $e){
            throw new WebhookReadErrorException('Não foi possível alterar o status do webhook para '.$status[$statusId].'. Motivo: '.$e->getMessage(). 'Data: '.date('d/m/Y H:i:s'), 552);
        }
      
    }

    //SALVA LOG NO BANCO DE DADOS COM AS INFORMAÇÕES DO ERRO
    public function registerLog($idWebhook, $message, $entity)
    {   
        try{

            $id=Log::insert(
                [
                    'entity'=>$entity,
                    'message'=>$message,
                    'webhook_id'=>$idWebhook,
                    'created_at'=>date("Y-m-d H:i:s"),
                    ]
            )->execute();
            
            return $id;

        }catch(PDOException $e){
            throw new WebhookReadErrorException('Erro ao gravar log na base de dados: '.$e->getMessage(). 'Data: '.date('d/m/Y H:i:s'), 552);
        }
        
    }
    //SALVA NO BANCO DE DADOS AS INFORMAÇÕES DO DEAL
    public function saveDeal(object $deal):string
    {

        try{
            $id = Deal::insert(
                [
                    'billing_basis'=>$deal->baseFaturamento,
                    'billing_basis_title'=>$deal->baseFaturamentoTitle,
                    'deal_id'=>$deal->id,
                    'omie_order_id' => $deal->omieOrderId,
                    'contact_id'=>$deal->contactId,
                    'person_id'=>$deal->personId,
                    'pipeline_id'=>$deal->pipelineId,
                    'stage_id'=>$deal->stageId,
                    'status_id'=>$deal->statusId,
                    'won_quote_id'=>$deal->wonQuoteId,
                    'create_date'=>$deal->createDate,
                    'last_order_id'=>$deal->lastOrderId,
                    'creator_id'=>$deal->creatorId,
                    'webhook_id'=>$deal->webhookId,
                    'created_at'=>date('Y-m-d H:i:s'),
                    ]
            )->execute();
            
            return $id;
        }catch(PDOException $e){
            throw new WebhookReadErrorException('Erro ao gravar o card na base de dados: '.$e->getMessage(). 'Data: '.date('d/m/Y H:i:s'), 552);
        }
        
    }
    //BUSCA UM DEAL PELO LASTORDER ID
    public function getDealByLastOrderId(int $idPedidoIntegracao)
    {
       $deal = Deal::select()->where('last_order_id', $idPedidoIntegracao)->one();
      
       return $deal;
    }


    //deleta um deal da base de dados
    public function deleteDeal(int $id): int
    {
        $delete = Deal::delete()->where('deal_id', $id)->execute();
        $total = $delete->rowCount();

        return $total;
    }

    //SALVA UMA ORDER NO BANCO DE DADOS
    public function saveOrder(object $order):int
    {   
        switch($order->target){
            case 'MHL':
                $database = new Homologacao_order();
                break;
            case 'MPR':
                $database = new Manospr_order();
                break;
            case 'MSC':
                $database = new Manossc_order();
                break;
        }

        try{

            $id = $database::insert(
                [
                    'id_omie'=>$order->idOmie,
                    'cod_cliente'=>$order->codCliente,
                    'cod_pedido_integracao'=>$order->codPedidoIntegracao ?? null,
                    'num_pedido_omie'=>$order->numPedidoOmie,
                    'cod_cliente_integracao'=>$order->codClienteIntegracao ?? null,
                    'data_previsao'=>$order->dataPrevisao,
                    'num_conta_corrente'=>$order->ncc,
                    'cod_vendedor_omie'=>$order->codVendedorOmie,
                    'id_vendedor_ploomes'=>$order->idVendedorPloomes ?? null,   
                    'app_key'=>$order->appKey ?? null,             
                    'created_at'=>date('Y-m-d H:i:s'),
                ]
            )->execute();
    
            return $id;

        }
        catch(PDOException $e){

            throw new PedidoDuplicadoException('Erro ao gravar a venda na base de dados: '.$e->getMessage(). 'Data: '.date('d/m/Y H:i:s'), 552);
        }

        
        
    }
    //VERIFICAR SE EXISTE A ORDEM NA BASE DE DADOS
    public function isIssetOrder(int $orderNumber, string $target){

        switch($target){
            case 'MHL':
                $database = new Homologacao_order();
                break;
            case 'MPR':
                $database = new Manospr_order();
                break;
            case 'MSC':
                $database = new Manossc_order();
                break;
        }

        try{

        $id = $database::select('id')
                ->where('id_omie',$orderNumber)
                ->one(); 

        return $id;

        }catch(PDOException $e){
            return $e->getMessage();
        }

    }
    //EXCLUI A ORDEM DA BASE DE DADOS
    public function excluiOrder(int $orderNumber, string $target):bool
    {

        switch($target){
            case 'MHL':
                $database = new Homologacao_order();
                break;
            case 'MPR':
                $database = new Manospr_order();
                break;
            case 'MSC':
                $database = new Manossc_order();
                break;
        }

        try{
            $database::delete()
            ->where('id_omie', $orderNumber)
            ->execute();
    
        }catch(PDOException $e){
                return $e->getMessage();
        }
            
            return true;
    }
    //ALTERA A ORDEM PARA CANCELADA
    public static function alterOrder(int $orderNumber, string $target):bool 
    {
        switch($target){
            case 'MHL':
                $database = new Homologacao_order();
                break;
            case 'MPR':
                $database = new Manospr_order();
                break;
            case 'MSC':
                $database = new Manossc_order();
                break;
        }

        try{

            $database::update()
                ->set('is_canceled', 1)
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->where('id_omie',$orderNumber)
                ->execute();
                
                return true;

        }catch(PDOException $e){
            return $e->getMessage();
        }
        
        return true;
    }

    //SALVA A NOTA FISCAL NO BANCO DE DADOS
    public function saveInvoicing(object $invoicing){

        switch($invoicing->target){
            case 'MHL':
                $database = new Homologacao_invoicing();
                break;
            case 'MPR':
                $database = new Manospr_invoicing();
                break;
            case 'MSC':
                $database = new Manossc_invoicing();
                break;
        }

        try{

            $id = $database::insert(
                [
                    'stage'=>$invoicing->etapa,
                    'invoicing_date'=>$invoicing->dataFaturado,
                    'invoicing_time'=>$invoicing->horaFaturado,
                    'client_id'=>$invoicing->idCliente,
                    'order_id'=>$invoicing->idPedido,
                    'invoice_number'=>$invoicing->nNF,
                    'order_number'=>$invoicing->numeroPedido,
                    'order_amount'=>$invoicing->valorPedido,
                    'user_id'=>$invoicing->authorId,
                    'user_email'=>$invoicing->authorEmail,
                    'user_name'=>$invoicing->authorName,
                    'appkey'=>$invoicing->appKey,
                    'created_at'=>date('Y-m-d H:i:s'),
                 ]
            )->execute();

            return $id;

        }catch(PDOException $e){
            $err = explode(':',$e->getMessage());
            if($err[0] === "SQLSTATE[23000]")
            throw new PDOException($e->getMessage());
           }
           $message['invoicing']['saveInvoincing'] = 'Erro ao gravar nota'.$invoicing->nNF.' na base de dados '.print $e->getmessage();
        
    }

    public function isIssetInvoice(object $omie, int $orderNumber):int|string|null
    {
        switch($omie->target){
            case 'MHL':
                $database = new Homologacao_invoicing();
                break;
            case 'MPR':
                $database = new Manospr_invoicing();
                break;
            case 'MSC':
                $database = new Manossc_invoicing();
                break;
        }
        try{

            $id = $database::select('id')
                    ->where('order_id',$orderNumber)
                    ->where('is_canceled',0)
                    ->one();       
            return (!$id)?$id: $id['id'];
                    
        }catch(PDOException $e){
            return $e->getMessage();
        }

    }

    public function alterInvoice(object $omie, int $orderNumber):string|bool
    {
        switch($omie->target){
            case 'MHL':
                $database = new Homologacao_invoicing();
                break;
            case 'MPR':
                $database = new Manospr_invoicing();
                break;
            case 'MSC':
                $database = new Manossc_invoicing();
                break;
        }

        try{

            $database::update()
                ->set('is_canceled', 1)
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->where('order_id',$orderNumber)
                ->execute();

            return true;

        }catch(PDOException $e){
            return $e->getMessage();
        }
    }

    public function getClient($status){
        try{
            $hook = Cliente::select()->where('status', $status)->orderBy('created_at')->one();
            return (!$hook ? throw new WebhookReadErrorException('Não existem clientes pendentes a serem processados no momento. Data: '.date('d/m/Y H:i:s').PHP_EOL, 552) : $hook);
            return $hook;
        }catch(PDOException $e){
            throw new WebhookReadErrorException('Erro ao buscar o webhook na base de dados: '.$e->getMessage(). 'Data: '.date('d/m/Y H:i:s'), 552);
        }


    }

    public function createNewTenancy($data){
        
        try{
            $id=Tenancie::insert(
                [
                    'fantasy_name'=>$data['fantasy_name'],
                    'cpf_cnpj'=>$data['cpf_cnpj'],
                    'legal_name'=>$data['legal_name'],
                    'logo'=>$data['logo'] ?? null,
                    'email'=>$data['email'],
                    'url'=>$data['url'],
                    'user_id'=>$data['user_id'],
                    'created_at'=>date("Y-m-d H:i:s"),
                    ]
            )->execute();
            
            return $id;

        }catch(PDOException $e){
            return  $e->getMessage();
        }
    }

    public function getAllDataTenancyById($id){

        try{
            $tenancy = Tenancie::select('*')
            ->where('id', $id)
            ->first();

            if (!$tenancy) {
                throw new Exception('Tenancy não encontrado! ', 500);
            }

            // Buscar os apps Omie cadastrados para esse tenancy
            $omieBases = Omie_base::select('*')
            ->where('tenancy_id', $tenancy['id'])
            ->get();

            // Buscar o app Ploomes cadastrado para esse tenancy
            $ploomesBases = Ploomes_base::select('*')
            ->where('tenancy_id', $tenancy['id'])
            ->get();

            // Buscar o vhost rabbitMQ cadastrado para esse tenancy
            $vhost = Vhost::select('*')
            ->where('tenancy_id', $tenancy['id'])
            ->get();

            // Montar o array no formato desejado
            $resultado = [
                'tenancies' => $tenancy,
                'omie_bases' => $omieBases ?? null,
                'ploomes_bases' => $ploomesBases ?? null,
                'vhost' => $vhost ?? null
            ];

            return $resultado; 

        }catch(PDOException $e){

            throw new Exception('Erro ao buscar dados do Tenancy no banco de dados: ' .$e->getMessage()); 

        }

        
    }
    public function createNewAppOmie($data){
        
        try{
            $id=Omie_base::insert(
                [
                    'app_name'=>$data['app_name'],
                    'app_key'=>$data['app_key'],
                    'app_secret'=>$data['app_secret'],
                    'ncc'=>$data['ncc'] ?? null,
                    'tenancy_id'=>$data['tenancy_id'],
                    'created_at'=> date('Y-m-d H:i:s'),
                ]
            )->execute();
            
            return $id;

        }catch(PDOException $e){
            return  $e->getMessage();
        }
    }

    public function createNewAppPloomes($data){
        
        try{
            $id=Ploomes_base::insert(
                [
                    'api_key'=>$data['api_key'],
                    'base_api'=>$data['base_api'],
                    'tenancy_id'=>$data['tenancy_id'],
                    'created_at'=> date('Y-m-d H:i:s'),
                ]
            )->execute();
            
            return $id;

        }catch(PDOException $e){
            return  $e->getMessage();
        }
    }

    public function getTenancyByUserId($idUser){

        try{
            $tenancy = Tenancie::select('*')
            ->where('user_id', $idUser)
            ->first();

            if (!$tenancy) {
                throw new Exception('Tenancy não encontrado! ', 500);
            }

            return $tenancy;

        }catch(PDOException $e){
            throw new Exception('Erro ao buscar dados do Tenancy no banco de dados: ' .$e->getMessage());
        }

    }
    public function createNewVHostRabbitMQ($data)
    {
        try{
            $id=Vhost::insert(
                [
                    'ip_vhost'=>$data['ip_vhost'],
                    'port_vhost'=>$data['port_vhost'],
                    'name_vhost'=>$data['name_vhost'],
                    'user_vhost'=>$data['user_vhost'],
                    'pass'=>$data['hash'],
                    'tenancy_id'=>intval($data['tenancy_id']),
                    'created_at'=> date('Y-m-d H:i:s'),
                ]
            )->execute();
            
            return $id;

        }catch(PDOException $e){
            return  $e->getMessage();
        }

    }

    

    
}