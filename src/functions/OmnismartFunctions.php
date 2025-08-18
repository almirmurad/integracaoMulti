<?php

namespace src\functions;

use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\services\OmnismartServices;
use src\services\PloomesServices;
use stdClass;


class OmnismartFunctions
{
    //processa o contato do OmniChannel para o CRM
    public static function processOminsmartPloomes($args, $ploomesServices, $omnismartServices, $action): array
    {
        
        $title = '';
        $message = [];
        $current = date('d/m/Y H:i:s');
        $decoded = $args['body'];
        $idChat = null;

        $assignAgent = [];
        $pipeline = [];
        $owner = new stdClass();

        $idChat = $decoded['data']['_id'];
        //atendimento atribuido ao agente independente de quem enviou
        if ($action['type'] !== 'ASSIGN') {
            throw new WebhookReadErrorException('Não era uma atribuição', 500);       
        }

        $chat = $omnismartServices->chatGetOne($idChat);

        // queue - fila atendimento
        // finished - finalizado
        // attendance - em atendimento

        if($chat['status'] === 'finished') 
        {
            throw new WebhookReadErrorException('Chat já finalizado', 500);
        }
        
        $contact = $chat['contact'];
        $assignAgent = $chat['agent'];
        $assignTeam  = $chat['team'];
    
        $owner->owner = $assignAgent['name'] ?? null;
        $owner->mailVendedor = $assignAgent['email'] ?? null;
        ($owner->mailVendedor) ? $owner->ploomesOwnerId = $ploomesServices->ownerId($owner) : $owner->ploomesOwnerId = null;
        $contact['ownerId'] = $owner->ploomesOwnerId;

        $contactJson = self::contactPloomesJson($contact);
        if (!$contactJson) {
            throw new WebhookReadErrorException('Não foi possível montar os dados do contato', 500);
        }

        $pipeline['pipelineId'] = $ploomesServices->getPipelineByName($assignTeam['name']);
        $pipeline['stageId'] = $ploomesServices->getPipelineStagesByPipelineId($pipeline['pipelineId']);
        
        //verifica se o cliente já existe no ploomes
        $pContact = $ploomesServices->getClientByPhone(DiverseFunctions::formatarTelefone($contact['telephones'][0]));

        //titulo do card
        $title =  "Integração Omnismart - [Cliente {$contact['name']}] Atendimento atribuído ao Agente {$assignAgent['name']} do time {$assignTeam['name']}";
        
        //primeiro contato 
        if(!$pContact) 
        {   
            $createContactPloomes = $ploomesServices->createPloomesPerson($contactJson);
            if ($createContactPloomes <= 0) {
                throw new WebhookReadErrorException('Não foi possível cadastrar contato no Ploomes', 500);
            }

            $cardJson = self::dealsPloomesJson($createContactPloomes, $owner, $title, $idChat, $pipeline);

            if (!$cardJson) {
                throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
            }

            $card = $ploomesServices->createPloomesDeal($cardJson);

            if ($card) {
                $message['success'] = 'Cliente ' . $contact['name'] . ' criado(a) no Ploomes CRM com sucesso! Foi criado uma oportunidade de negócio para ele(a) no funil: ' . $card['Pipeline']['Name'] . '. Card Id: ' . $card['Id'] . ' Título: ' . $card['Title'] . ' Estágio: ' . $card['Stage']['Name'] . ' Status: ' . $card['Status']['Name'] . ' em: ' . $current;
            }

            return $message;
        } 
        else //recompra ou transferência entre agentes do time de vendas
        {  
                    
            //1) atualiza os dados do cliente ou se o email foi alterado pelo sdr no omnismart.
            $ploomesServices->updatePloomesContact($contactJson, $pContact['Id']);
         
            //2) verifica se o card já existe no ploomes no pipeline vendas
            $ploomesCard = self::getCardByIdChat($idChat, $ploomesServices, $pipeline); 
                                                             
            //2.2) se o card existir no pipeline de origem, verifica se existe OriginId;
            if ($ploomesCard !== null) 
            {             

                //3) verificar se o card está fechado
                $status = match($ploomesCard['StatusId'])
                {
                    1 => 'aberto',
                    2 => 'ganho',
                    3 => 'perdido'
                };
                
                $cardJson = self::dealsPloomesJson($pContact['Id'], $owner, $title, $idChat, $pipeline);
                    
                if($status !== 'aberto'){
                    //3.1) se estiver fechado precisa mandar uma interação no cliente informando que entrou em contato após o fechamento do card e mensagem no omnisamart pra informar que o card precisa ser reaberto

                    $content = "ATENÇÃO! - Card [{$ploomesCard['Id']}] se encotra {$status}, mas houve uma interação no chat do Omnismart e o atendimento foi atribuido ao agente {$assignAgent['name']} do time {$assignTeam['name']}. Reabra o card e atribua o responsável manualmente para dar continuidade ao atendimento.";

                    $arrayInteractionMessage=[
                        'DealId' => $ploomesCard['Id'],
                        'ContactId' => $pContact['Id'],
                        'Content' => $content,
                        'Title' => "Interação em cliente com o card {$status}"
                    ];

                    ($ploomesServices->createPloomesIteraction(json_encode($arrayInteractionMessage))) ? $message['success'] = "Card {$ploomesCard['Id']} se encontra {$status}. Foi enviado uma interação no card e no cliente solicitando reabertura. {$current} " : throw new WebhookReadErrorException("Card {$ploomesCard['Id']} se encontra {$status}. Houve um erro ao enviar interação no card e no cliente solicitando reabertura. {$current}");

                }else{
                    //3.2) se estiver aberto pode atualizar tudo.

                    $card = $ploomesServices->updatePloomesDeal($cardJson, $ploomesCard['Id']);
    
                    if ($card) {
                        $message['success'] = 'Card ' . $ploomesCard['Id'] . ' alterado no Ploomes! Em: ' . $current;
                    }
                }           
            }
            else
            {                  

                $cardJson = self::dealsPloomesJson($pContact['Id'], $owner, $title, $idChat, $pipeline);

                if (!$cardJson) {
                    throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
                }

                $card = $ploomesServices->createPloomesDeal($cardJson);

                if ($card) {
                    $message['success'] = 'Cliente ' . $contact['name'] . ' já estava csadastrado no Ploomes CRM. Foi criado uma oportunidade de negócio para ele(a) no funil: ' . $card['Pipeline']['Name'] . '. Card Id: ' . $card['Id'] . ' Título: ' . $card['Title'] . ' Estágio: ' . $card['Stage']['Name'] . ' Status: ' . $card['Status']['Name'] . ' em: ' . $current;
                }

            }

            return $message;
           
        }
    }

    public static function processPloomesOmnismart($args, $ploomesServices, $omnismartServices, $action): array
    {
        
        $message = [];
        $current = date('d/m/Y H:i:s');
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $idChat = '';
        // card perdido no Ploomes
        $customFields = CustomFieldsFunction::compareCustomFields($args['body']['New']['OtherProperties'], $idUser,'Negócio' );
        $idChat = $customFields['bicorp_api_id_chat_out'];

        $chat = $omnismartServices->chatGetOne($idChat);

        $idAgent = $chat['agent']['_id'];

        if($action['action'] !== 'lose'){
            throw new WebhookReadErrorException('Não era um webhook de card perdido', 500);
        }

        $closeChat = self::closeChatOmnichannel($idChat, $idAgent, $omnismartServices); // encerra chat omnismart
        
        if($closeChat){

            $ploomesCard = self::getCardByIdChat($idChat, $ploomesServices);
            $status = match($ploomesCard['StatusId'])
                {
                    1 => 'aberto',
                    2 => 'ganho',
                    3 => 'perdido'
                };

            $content = "ATENÇÃO! -  O atendimento do Card [{$ploomesCard['Id']}] foi encerrado no Omnismart após o card ter sido {$status}.";

            $arrayInteractionMessage=[
                'DealId' => $ploomesCard['Id'],
                'ContactId' => $ploomesCard['ContactId'],
                'Content' => $content,
                'Title' => "Atendimento finalizado no Omnismart após card {$status}"
            ];

            ($ploomesServices->createPloomesIteraction(json_encode($arrayInteractionMessage))) ? $message['success'] = "Card {$ploomesCard['Id']} foi {$status}. Foi finalizado o atendimanto no Omnismart e enviado uma interação no card e no cliente. {$current} " : throw new WebhookReadErrorException("Card {$ploomesCard['Id']} se encontra {$status}. Houve um erro ao enviar interação no card e no cliente após a finalização do atendimento no Omnismart. {$current}");
        }

        $message['success'] = $content;
       
        
        return $message;

    }

    public static function contactPloomesJson($contact)
    {
        $array = [
            'Name' => $contact['name'],
            'TypeId' => 2,
            'Email' => $contact['emails'][0],
            'Phones' => [
                [
                    'PhoneNumber' => DiverseFunctions::formatarTelefone($contact['telephones'][0]),
                    'TypeId' => 1,
                ],
            ],
            'OwnerId' => $contact['ownerId']
        ];

        return json_encode($array);
    }

    public static function dealsPloomesJson($pContact, $owner, $title, $idChat, $pipeline = null)
    {
        $keys = self::getFieldKey();

        $otherProperties = [
            [
                'FieldKey' => $keys['id_chat'],
                'StringValue' => $idChat,
            ]
        ];

        $array = [
            'Title' => $title,
            'ContactId' => $pContact,
            'PipelineId' => $pipeline['pipelineId'],
            'StageId' => $pipeline['stageId'],
            'OwnerId' => $owner->ploomesOwnerId,
            'OtherProperties' => $otherProperties,
        ];

        return json_encode($array);
    }

    public static function getCardByIdChat($idChat, $ploomesServices, $pipeline = null )
    {
        $keys = self::getFieldKey();
        return $ploomesServices->getCardByIdChat($idChat, $keys, $pipeline);
    }

    public static function getFieldKey()
    {
        $customFields = CustomFieldsFunction::getCustomFieldsByEntity('Negócio');
        $keys = [];
        foreach ($customFields as $custom) {

            switch($custom['SendExternalKey']){
                case 'bicorp_api_id_chat_out':
                    $keys['id_chat'] = $custom['Key'];
                    break;
                case 'bicorp_api_observacoes_negocio_out':
                    $keys['notes_sdr'] = $custom['Key'];
                    break;
            }
        }
        return $keys;
    }

    public static function closeChatOmnichannel($idChat, $idAgent, OmnismartServices $omnismartServices)
    {
        $array = [
            "id"=> $idChat,
            "agent" => $idAgent
        ];

        if($omnismartServices->closeChat(json_encode($array))){
            return true;
        }
        
        throw new WebhookReadErrorException('Erro ao finalizar o chat', 500);
        

    }
}
