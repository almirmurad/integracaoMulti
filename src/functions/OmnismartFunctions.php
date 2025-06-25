<?php
namespace src\functions;

use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\services\PloomesServices;
use stdClass;


class OmnismartFunctions{
    //processa o contato do OmniChannel para o CRM
    public static function processOminsmartPloomes($args, $ploomesServices, $omnismartServices, $action):array
    {    
        $title = '';       
        $message = [];
        $current = date('d/m/Y H:i:s');
        $decoded = $args['body'];
    
        $idChat = null;

        if($action['type'] === 'ASSIGN'){
            
            foreach($decoded['data']['steps'] as $step){
                if($step['type'] === 'assignment'){
                    $agente =  $step['agent'];
                    
                }
            }

        }elseif($action['type'] === 'SUPToAGENT'){
            foreach($decoded['data']['steps'] as $step){
                if($step['type'] === 'transferAgent'){
                    $agente =  $step['toAgent'];
                    $supervisor = $step['agent'];
                }
            }
        }
        
        $idChat = $decoded['data']['_id'];

        if($idChat !== null){
            $chat = $omnismartServices->chatGetOne($idChat);
            $contact = $chat['contact'];
        }else{
            throw new WebhookReadErrorException('Chat inexistente', 500);
        }
        //verifica se o cliente já existe no ploomes
        $pContact = $ploomesServices->getClientByPhone(DiverseFunctions::formatarTelefone($contact['telephones'][0]));
      
        $title = match($action['type']){
            'SUPToAGENT'=>"Integração Omnismart - [Cliente {$contact['name']}] Transferência do supervisor {$supervisor['name']} para o agente {$agente['name']}",
            'ASSIGN'=>"Integração Omnismart - [Cliente {$contact['name']}] Atendimento atribuído ao Agente {$agente['name']}"
        };
        if($pContact){

            $owner = new stdClass();
            $owner->mailVendedor = $agente['email'];
            $agente['ploomesOwnerId'] = $ploomesServices->ownerId($owner);
            
            //verifica se o card já existe no ploomes
            $idPloomesCard = self::getCardByIdChat($idChat, $ploomesServices);

            if($idPloomesCard !== null){
                //altera o card               
                $cardJson = self::dealsPloomesJson($pContact['Id'], $agente, $title, $idChat);
                if(!$cardJson){
                    throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
                }              

                $card = $ploomesServices->updatePloomesDeal($cardJson, $idPloomesCard);

                if($card){
                    $message['success'] = 'Card '.$idPloomesCard.' alterado no Ploomes! Em: '.$current;
                }

            }else{
                //cria o card                
                $cardJson = self::dealsPloomesJson($pContact['Id'], $agente, $title, $idChat);

                if(!$cardJson){
                    throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
                }
    
                $card = $ploomesServices->createPloomesDeal($cardJson);
    
                if($card){
                    $message['success'] = 'Cliente '.$contact['name'].' já cadastrado(a) no Ploomes! Ainda assim, foi criado uma oportunidade de negócio para ele(a) no funil: '. $card['Pipeline']['Name'].'. Card Id: '. $card['Id'] .' Título: '.$card['Title'].' Estágio: '.$card['Stage']['Name'].' Status: '.$card['Status']['Name'].'em: '.$current;
                }
            }
            
            return $message;

        }else{

            $contactJson = self::contactPloomesJson($contact);

            if(!$contactJson){
                throw new WebhookReadErrorException('Não foi possível montar os dados do contato', 500);
            }
        
            $createContactPloomes = $ploomesServices->createPloomesPerson($contactJson);

            if($createContactPloomes <= 0){
                throw new WebhookReadErrorException('Não foi possível cadastrar contato no Ploomes', 500);
            }

            $owner = new stdClass();
            $owner->mailVendedor = $agente['email'];
            $agente['ploomesOwnerId'] = $ploomesServices->ownerId($owner);

            $cardJson = self::dealsPloomesJson($createContactPloomes, $agente, $title, $idChat);

            if(!$cardJson){
                throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
            }

            $card = $ploomesServices->createPloomesDeal($cardJson);

            if($card){
                $message['success'] = 'Cliente '.$contact['name'].' criado(a) no Ploomes CRM com sucesso! Foi criado uma oportunidade de negócio para ele(a) no funil: '. $card['Pipeline']['Name'].'. Card Id: '. $card['Id'] .' Título: '.$card['Title'].' Estágio: '.$card['Stage']['Name'].' Status: '.$card['Status']['Name'].'em: '.$current;
            }

            return $message;

        }        

        
    }

    public static function contactPloomesJson($contact)
    {
        $array = [
            'Name'=> $contact['name'],
            'TypeId'=> 2,
            'Email'=> $contact['emails'][0],
            'Phones'=> [
                [
                    'PhoneNumber'=>DiverseFunctions::formatarTelefone($contact['telephones'][0]),
                    'TypeId'=>1,
                ],
            ],
        ];

        return json_encode($array);

    }

    public static function dealsPloomesJson($pContact, $agente, $title, $idChat)
    {        
        $key = self::getFieldKey();
        $op = [
                'FieldKey'=>$key,
                'StringValue'=>$idChat
        ];
        $array = [
            'Title'=>$title,
            'ContactId' => $pContact,
            'OwnerId'=>$agente['ploomesOwnerId'],
            'OtherProperties'=>[$op],
        ];

        return json_encode($array);

    }

  

    public static function getCardByIdChat($idChat, $ploomesServices)
    {
        
        $key = self::getFieldKey();
        return $ploomesServices->getCardByIdChat($idChat, $key);
    }

    public static function getFieldKey()
    {
        $customFields = CustomFieldsFunction::getCustomFieldsByEntity('Negócio');
        $key = '';
        foreach($customFields as $custom){
            if($custom['SendExternalKey'] === 'bicorp_api_id_chat_out'){
                $key = $custom['Key'];
            }
        }

        return $key;

    }
    

    

}