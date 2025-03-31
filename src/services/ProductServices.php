<?php

namespace src\services;

use src\functions\ProductsFunctions;
use src\services\OmieServices;
use src\services\PloomesServices;


class ProductServices
{
    // public static function createContact($contact)
    // {
    //     $omieServices = new OmieServices();
    //     $ploomesServices = new PloomesServices();
    //     $messages = [
    //         'success'=>[],
    //         'error'=>[],
    //     ];
   
    //     $current = date('d/m/Y H:i:s');
   
    //     foreach($contact->basesFaturamento as $k => $bf)
    //     {

    //         $omie[$k] = new stdClass();
            
    //         if($bf['integrar'] > 0){
    //             $omie[$k]->baseFaturamentoTitle = $bf['title'];
    //             $omie[$k]->target = $bf['sigla']; 
    //             $omie[$k]->appSecret = $bf['appSecret'];
    //             $omie[$k]->appKey = $bf['appKey'];
    //             $contact->cVendedorOmie = $omieServices->vendedorIdOmie($omie[$k],$contact->ownerEmail); 
    //             $criaClienteOmie = $omieServices->criaClienteOmie($omie[$k], $contact);

    //             //verifica se criou o cliente no omie
    //             if (isset($criaClienteOmie['codigo_status']) && $criaClienteOmie['codigo_status'] == "0") {
    //                 $match = match ($k) {
    //                      0=> 'contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3',
    //                      1=> 'contact_6DB7009F-1E58-4871-B1E6-65534737C1D0',
    //                      2=> 'contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2',
    //                      3=> 'contact_07784D81-18E1-42DC-9937-AB37434176FB',
    //                 };
    //                 $codigoOmie = $criaClienteOmie['codigo_cliente_omie'];
    //                 $array = [
                        
    //                     'TypeId'=>1,
    //                     'OtherProperties'=>[
    //                         [
    //                             'FieldKey'=>$match,
    //                             'StringValue'=>"$codigoOmie",
    //                         ]
    //                     ]
    //                 ];
    //                 $json = json_encode($array);
    //                 $insertIdOmie = $ploomesServices->updatePloomesContact($json, $contact->id);
    //                 if($insertIdOmie){

    //                     //monta a mensagem para atualizar o cliente do ploomes
    //                     $msg=[
    //                         'ContactId' => $contact->id,
    //                         'Content' => 'Cliente '.$contact->name.' criada no OMIE via API BICORP na base '.$omie[$k]->baseFaturamentoTitle,
    //                         'Title' => 'Pedido Criado'
    //                     ];
                        
    //                     //cria uma interação no card
    //                     ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o numero: '.$criaClienteOmie['codigo_cliente_omie'].' e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' gravados no Omie ERP com o numero: '.$criaClienteOmie['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;

    //                 }
                   

    //                 //inclui o id do pedido no omie na tabela deal
    //                 // if($criaClienteOmie['codigo_cliente_omie']){
    //                 //     //salva um deal no banco
    //                 //     $deal->omieOrderId = $incluiPedidoOmie['codigo_pedido'];
    //                 //     $dealCreatedId = $this->databaseServices->saveDeal($deal);   
    //                 //     $message['winDeal']['dealMessage'] ='Id do Deal no Banco de Dados: '.$dealCreatedId;  
    //                 //     if($dealCreatedId){

    //                 //         $omie[$k]->idOmie = $deal->omieOrderId;
    //                 //         $omie[$k]->codCliente = $idClienteOmie;
    //                 //         $omie[$k]->codPedidoIntegracao = $deal->lastOrderId;
    //                 //         $omie[$k]->numPedidoOmie = intval($incluiPedidoOmie['numero_pedido']);
    //                 //         $omie[$k]->codClienteIntegracao = $deal->contactId;
    //                 //         $omie[$k]->dataPrevisao = $deal->finishDate;
    //                 //         $omie[$k]->codVendedorOmie = $codVendedorOmie;
    //                 //         $omie[$k]->idVendedorPloomes = $deal->ownerId;   
    //                 //         $omie[$k]->appKey = $omie[$k]->appKey;             
                    
    //                 //         $id = $this->databaseServices->saveOrder($omie[$k]);
    //                 //         $message['winDeal']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos '.$omie[$k]->baseFaturamentoTitle.' id '.$id.'em: '.$current;
    //                 //     }
                        
    //                 // }

    //                 $messages['success'][]=$message;
                    
    //             }else{
    //                 //monta a mensagem para atualizar o card do ploomes
    //                 $msg=[
    //                     'ContactId' => $contact->id,
    //                     'Content' => 'Erro ao gravar cliente no Omie: '. $criaClienteOmie['faultstring'].' na base '.$omie[$k]->baseFaturamentoTitle.' Data = '.$current,
    //                     'Title' => 'Erro ao Gravar cliente'
    //                 ];
                    
    //                 //cria uma interação no card
    //                 ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Erro ao gravar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $criaClienteOmie['faultstring'].' Data = '.$current: $message = 'Erro ao gravar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $criaClienteOmie['faultstring'].' e erro ao enviar mensagem no card do cliente do Ploomes Data = '.$current;

    //                 $messages['error'][]=$message;
    //             }       
    //         }
    //     }   
    //     return $messages;
    // }

    // public static function updateContact($diff, $contact)
    // {
    //     $omieServices = new OmieServices();
    //     $ploomesServices = new PloomesServices();
    //     $messages = [
    //         'success'=>[],
    //         'error'=>[],
    //     ];
    //     $total = 0;
   
    //     $current = date('d/m/Y H:i:s');
    //     if(!empty($diff)){
    //         foreach($contact->basesFaturamento as $k => $bf)
    //         {
    //             $omie[$k] = new stdClass();

                
                    
    //                 if($bf['integrar'] > 0){
    //                     $total ++;
    //                     $omie[$k]->baseFaturamentoTitle = $bf['title'];
    //                     $omie[$k]->target = $bf['sigla']; 
    //                     $omie[$k]->appSecret = $bf['appSecret'];
    //                     $omie[$k]->appKey = $bf['appKey'];
                        
    //                     $diff['idIntegracao'] = $contact->id;
    //                     $diff['idOmie'] = $contact->codOmie[$k];
    //                     $diff['cVendedorOmie'] = (isset($diff['ownerEmail']['new']) && $diff['ownerEmail']['new'] !== null) ? $omieServices->vendedorIdOmie($omie[$k],$diff['ownerEmail']['new']) : null;
    //                     $alterar = $omieServices->alteraCliente($omie[$k], $diff);

    //                     //verifica se criou o cliente no omie
    //                     if (isset($alterar['codigo_status']) && $alterar['codigo_status'] == "0") {
    //                         //monta a mensagem para atualizar o cliente do ploomes
    //                         $msg=[
    //                             'ContactId' => $contact->id,
    //                             'Content' => 'Cliente '.$contact->name.' alterado no OMIE via API BICORP na base '.$omie[$k]->baseFaturamentoTitle,
    //                             'Title' => 'Pedido Criado'
    //                         ];
                            
    //                         //cria uma interação no card
    //                         ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' alterado no Omie ERP ('.$omie[$k]->baseFaturamentoTitle.') com o numero: '.$alterar['codigo_cliente_omie'].' e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' alterado no Omie ERP com o numero: '.$alterar['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;

    //                         //aqui atualizaria a base de dados com sql de update
                        
    //                         $messages['success'][] = $message;
                            
    //                     }else{
    //                         //monta a mensagem para atualizar o card do ploomes
    //                         $msg=[
    //                             'ContactId' => $contact->id,
    //                             'Content' => 'Erro ao alterar cliente no Omie: '. $alterar['faultstring'].' na base '.$omie[$k]->baseFaturamentoTitle.' Data = '.$current,
    //                             'Title' => 'Erro ao alterar cliente'
    //                         ];
                            
    //                         //cria uma interação no card
    //                         ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Erro ao alterar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $alterar['faultstring'].' Data = '.$current: $message = 'Erro ao alterar cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $alterar['faultstring'].' e erro ao enviar mensagem no card do cliente do Ploomes Data = '.$current;
    //                         $messages['error'][]=$message;
    //                     }       
    //                 }

                
    //         }   
    //     }else{
    //         $messages['error'][]='Esta alteração já foi feita';
    //     }
    //         return $messages;       
    // }

    // public static function deleteContact($contact)
    // {
    //     $omieServices = new OmieServices();
    //     $ploomesServices = new PloomesServices();
    //     $messages = [
    //         'success'=>[],
    //         'error'=>[],
    //     ];
    //     $total = 0;
   
    //     $current = date('d/m/Y H:i:s');

    //     foreach($contact->basesFaturamento as $k => $bf)
    //     {
    //         $omie[$k] = new stdClass();
            
    //         if($bf['integrar'] > 0){
    //             $total ++;
    //             $omie[$k]->baseFaturamentoTitle = $bf['title'];
    //             $omie[$k]->target = $bf['sigla']; 
    //             $omie[$k]->appSecret = $bf['appSecret'];
    //             $omie[$k]->appKey = $bf['appKey'];
    //             $contact->idOmie = $contact->codOmie[$k];
    //             $excluir = $omieServices->deleteClienteOmie($omie[$k], $contact);

    //             //verifica se excluiu o cliente no omie
    //             if (isset($excluir['codigo_status']) && $excluir['codigo_status'] == "0") {
    //                 //monta a mensagem para atualizar o cliente do ploomes
    //                 //comentado pq não tem como criar uma interação no usuário se ele for excluído
    //                 // $msg=[
    //                 //     'ContactId' => 40058720,
    //                 //     'Content' => 'Cliente '.$contact->name.' excluido no OMIE via API BICORP na base '.$omie[$k]->baseFaturamentoTitle,
    //                 //     'Title' => 'Usuário excluído no OMIE ERP'
    //                 // ];
                    
    //                 // //cria uma interação no card
    //                 // ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes: '.$contact->id.' - '.$contact->name.' excluido no Omie ERP ('.$omie[$k]->baseFaturamentoTitle.') e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' excluido no Omie ERP com o numero: '.$excluir['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;
    //                 $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$contact->id.' excluido no Omie ERP com o numero: '.$excluir['codigo_cliente_omie'].' porém não foi possível gravar a mensagem no card do cliente do Ploomes pois ele acaba de ser excluído. Data '.$current;
                 
    //                 $messages['success'][] = $message;
                    
    //             }else{
    //                 //monta a mensagem para atualizar o card do ploomes
    //                 $msg=[
    //                     'ContactId' => $contact->id,
    //                     'Content' => 'Erro ao excluir cliente no Omie: '. $excluir['faultstring'].' na base '.$omie[$k]->baseFaturamentoTitle.' Data = '.$current,
    //                     'Title' => 'Erro ao excluir cliente'
    //                 ];
                    
    //                 //cria uma interação no card
    //                 ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Erro ao excluir cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $excluir['faultstring'].' Data = '.$current: $message = 'Erro ao excluir cliente no Omie base '.$omie[$k]->baseFaturamentoTitle.': '. $excluir['faultstring'].' e erro ao enviar mensagem no card do cliente do Ploomes Data = '.$current;
    //                 $messages['error'][]=$message;
    //             }       
    //         }
    //     }   
   
    //     return $messages;       

    // }

    public static function createProductFromERPToCRM($product)
    {
        $omieServices = new OmieServices();
        $ploomesServices = new PloomesServices();
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
   
        $current = date('d/m/Y H:i:s');
        $json = ProductsFunctions::createPloomesProductFromOmieObject($product, $ploomesServices, $omieServices);
        $pPloomes = $ploomesServices->getProductByCode($product->codigo);
        if($pPloomes === null)
        {
            $codIntegracao = $ploomesServices->createPloomesProduct($json);
            if(!$codIntegracao){
                $messages['error'] = 'Erro ao cadastrar o produto ('.$product->descricao.') Data:' .$current;
            }else{
                //aqui poderia enviar ao omie o codigo do produto de integração (id produto no ploomes)
                $product->idPloomes = $codIntegracao;
                $omieServices->setProductIntegrationCodeAction($product);
                $messages['success'] = 'Produto ('.$product->descricao.') Cadastrado no Ploomes CRM com sucesso! Data: '.$current;
            }

        }else{
            $product->idPloomes = $pPloomes['Id'];
            if(!$ploomesServices->updatePloomesProduct($json, $product->idPloomes)){
                $messages['error'] = 'Erro ao cadastrar/alterar o produto ('.$product->descricao.') Data:' .$current;
            }else{
                //aqui poderia enviar ao omie o codigo do produto de integração (id produto no ploomes)
                $omieServices->setProductIntegrationCodeAction($product);
                $messages['success'] = 'Produto ('.$product->descricao.') Cadastrado/alterado no Ploomes CRM com sucesso! Data: '.$current;
            }
        }

        return $messages;
    }

    public static function updateProductFromERPToCRM(string $json, object $product, PloomesServices $ploomesServices)
    {
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        $current = date('d/m/Y H:i:s');
  
        $ploomesProduct = $ploomesServices->getProductByCode($product->codigo);

        if(!$ploomesProduct){
            $messages['error'] = 'Erro: produto  não foi encontrado no Ploomes CRM - '.$current;
        }else{
            // print_r(json_decode($json));
            // print_r($ploomesProduct);
            // exit;
            //self::setProductIntegrationCode($product, new OmieServices);
            $ploomesServices->updatePloomesProduct($json, $ploomesProduct['Id']);
            $messages['success'] = 'Produto  alterado no Ploomes CRM com sucesso! - '.$current;
        }
        
        return $messages;
    }

    public static function deleteProductFromERPToCRM(object $product, PloomesServices $ploomesServices)
    {
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
   
        $current = date('d/m/Y H:i:s');

        //verificar se existe um Produto cadastrado no ploomes com o Codigo e se existir deleta
        $pProduct = $ploomesServices->getProductByCode($product->codigo);
       
        if($pProduct['Id'] === null){
            $messages['error'] = 'Erro ao exluir o produto '.$product->descricao.' Não foi encontrado no Ploomes. Data: '.$current ;
        }else{
            $ploomesServices->deletePloomesProduct($pProduct['Id']);
            $messages['success'] = 'Produto '.$product->descricao.' excluído do Omie ERP e do Ploomes CRM. Data: '.$current;
        }

        return $messages;
    }
    //seta o id de integração do produto como Id ploomes
    public static function setProductIntegrationCode(object $product, OmieServices $omieServices)
    {
        $messages = [
            'success'=>[],
            'error'=>[],
        ];
        $t = 0;
        $current = date('d/m/Y H:i:s');
        foreach($product->baseFaturamento as $bf){
            $product->baseFaturamento = [];
            $product->baseFaturamento ['appKey'] = $bf['appKey'];
            $product->baseFaturamento ['appSecret'] = $bf['appSecret'];
            $product->baseFaturamento ['idOmie'] = $bf['idOmie'];
            $product->baseFaturamento ['title'] = $bf['title'];
            $t++;

            $setProduct = $omieServices->setProductIntegrationCodeAction($product);

            if(!$setProduct){
                $messages['error'][$t] = 'Erro: código de integração ['.$product->idPloomes.'], não pode ser associado ao produto: '.$product->descricao.' ['.$product->baseFaturamento['title'].'] na base de faturamento ['.$product->baseFaturamento['idOmie'].'] - '.$current;
            }else{
                $messages['success'][$t] = 'Código de integração ['.$product->idPloomes.'] associado o produto '.$product->descricao.' com sucesso na base de faturamento ['.$product->baseFaturamento['title'].'] - '.$current;
            }

        }
  
        return $messages;
    }
}