<?php

namespace src\functions;

use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
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

        $agente = [];
        $toAgente = [];
        $thisTeam = [];
        $team = [];
        $chatBot = false;
        $supervisor = [];
        $pipeline = [];
        $owner = new stdClass();
        $owner->owner_sdr = $decoded['data']['steps'][2]['agent']['name'];
        // $originPipeline = $decoded['data']['steps'][1]['toTeam'];
        $destinePipeline = 'Vendas';

      
        if ($action['type'] === 'ASSIGN') {
            
            for($x = 0; $x < count($decoded['data']['steps']); $x++)
            {
               $i = $x - 1;
                
               if($decoded['data']['steps'][$x]['type'] === 'assignment'){                
                    if($decoded['data']['steps'][$i]['type'] === 'transferTeam'){
                        $team = $decoded['data']['steps'][$i]['toTeam'];
                        $agente = $decoded['data']['steps'][$x]['agent'];;
                    }else{
                        $idTeam = $decoded['data']['steps'][$i]['toAgent']['teams'][0];
                        $team = $omnismartServices->getTeamById($idTeam);
                        $agente = $decoded['data']['steps'][$x]['agent'];

                        if($team['name'] === 'DEFAULT')
                        {
                            $team['name'] = 'Vendas';
                        }
                    }
                }
           
            }
            
            // foreach ($decoded['data']['steps'] as $step) {

            //     switch ($step['type']) {
            //         case 'transferTeam':
                        
            //             print_r($x);
            //             exit;
            //             $chatBot = ($step['agent']) ? false : true;
            //             $team = $originPipeline;
            //             break;
            //         case 'assignment':
            //             $agente = $step['agent'];
            //             break;
            //     }
            // }
            // if($chatBot){   
                
            // }

        } elseif ($action['type'] === 'SUPToAGENT') {

            foreach ($decoded['data']['steps'] as $step) {

                switch ($step['type']) {
                    case 'transferAgent':
                        $agente =  $step['toAgent'];
                        $supervisor = $step['agent'];
                        break;
                    case 'transferTeam':
                        $team = $step['toTeam'];
                        break;
                }
            }

        } elseif ($action['type'] === 'AGENTToAGENT') {

            foreach ($decoded['data']['steps'] as $step) 
            {
                switch($step['type']){
                    case 'transferTeam':
                        $thisTeam = $step['toTeam'];
                        break;
                    case 'transferAgent':
                        $toAgente =  $step['toAgent'];
                        $allTeams = $step['toAgent']['teams'];
                       
                        foreach($allTeams as $oneTeam){
                            $dataTeam = $omnismartServices->getTeamById($oneTeam);
                            if(mb_strtolower($destinePipeline) === (mb_strtolower($dataTeam['name']))){

                                $team['name'] = $dataTeam['name'];
                        
                            }
                        }
                        
                        $agente =  $step['agent'];
                        break;   
                }
                               
                
            }
            
        } elseif ($action['type'] === 'BOTToAGENT') {

            foreach ($decoded['data']['steps'] as $step) {

                switch ($step['type']) {
                    case 'transferAgent':
                        $toAgente =  $step['toAgent'];
                        break;
                    case 'transferTeam':
                        $team = $step['toTeam'];
                        $agente = $step['agent'];
                        break;
                }
            }
        }
        // print_r($action['type']);
        // print_r($thisTeam['name']);
        // print_r($team['name']);
        // print_r($agente['name']);
        // print_r($toAgente['name']);
        // PHP_EOL;
        // exit;

        $idChat = $decoded['data']['_id'];

        if ($idChat !== null) {
            $chat = $omnismartServices->chatGetOne($idChat);
            $contact = $chat['contact'];
        } else {
            throw new WebhookReadErrorException('Chat inexistente', 500);
        }
        
        //verifica se o cliente já existe no ploomes
        $pContact = $ploomesServices->getClientByPhone(DiverseFunctions::formatarTelefone($contact['telephones'][0]));
        

        //titulo do card
        $title = match ($action['type']) {
            'SUPToAGENT' => "Integração Omnismart - [Cliente {$contact['name']}] Transferência do supervisor {$supervisor['name']} para o agente {$agente['name']} do time {$team['name']}",
            'ASSIGN' => "Integração Omnismart - [Cliente {$contact['name']}] Atendimento atribuído ao Agente {$agente['name']} do time {$team['name']}",
            'BOTToAGENT' => "Integração Omnismart - [Cliente {$contact['name']}] Transferência do Bot para agente {$toAgente['name']} do time {$team['name']}",
            'AGENTToAGENT' => "Integração Omnismart - [Cliente {$contact['name']}] Transferência do agente {$agente['name']} para agente {$toAgente['name']} do time {$team['name']}",
        };

        // print_r($title);
        // exit;
        
        $contact['ownerId'] = null;
        if ($pContact) {
            //recompra
            $owner->mailVendedor = $toAgente['email'] ?? $agente['email'];
            $owner->ploomesOwnerId = $ploomesServices->ownerId($owner) ?? null;
            $contact['ownerId'] = $owner->ploomesOwnerId ?? null;
            
            //se for recompra atualiza os dados do cliente ou se o email foi alterado pelo sdr no omnismart.
            $contactJson = self::contactPloomesJson($contact);
            $ploomesServices->updatePloomesContact($contactJson, $pContact['Id']);

            //verifica se o card já existe no ploomes
            $ploomesCard = self::getCardByIdChat($idChat, $ploomesServices);
            
            if ($ploomesCard !== null) {
                //se existir e o nome do time for igual ao nome do pipeline
                // print 'card já existe';
                // print_r($ploomesCard);
                // print_r($team['name']);
                // exit;
                $cardCustomFields = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($ploomesCard['OtherProperties'], 'Negócio');
                
                $dealOrigin = [];
                $dealOrigin['dealOrigemId'] = $ploomesCard['Id'];
                $dealOrigin['notes'] = $cardCustomFields['bicorp_api_observacoes_negocio_out'] ?? null;

                // print_r($dealOrigin);
                // exit;
                if (mb_strtolower($team['name']) !== mb_strtolower($ploomesCard['Pipeline']['Name'])) {
                    
                    //pega o pipeline pelo nome do time em caso de transbordo para outro funil
                    $pipeline['pipelineId'] = $ploomesServices->getPipelineByName($team['name']);
                    $pipeline['stageId'] = $ploomesServices->getPipelineStagesByPipelineId($pipeline['pipelineId']);
                    
                    $cardJson = self::dealsPloomesJson($pContact['Id'], $owner, $title, $idChat, $pipeline, $dealOrigin);

                    //procura o id chat no pipelne de vendas
                    $ploomesCard = self::getCardByIdChat($idChat, $ploomesServices, $pipeline);
                    if($ploomesCard !== null)
                    {
                        $card = $ploomesServices->updatePloomesDeal($cardJson, $ploomesCard['Id']);
        
                        if ($card) {
                            $message['success'] = 'Card ' . $ploomesCard['Id'] . ' alterado no Ploomes! Em: ' . $current;
                        }

                    }else{

                        $card = $ploomesServices->createPloomesDeal($cardJson);

                        if($card) 
                        {
                            $message['success'] = 'Cliente ' . $contact['name'] . ' criado(a) no Ploomes CRM com sucesso! Foi criado uma oportunidade de negócio para ele(a) no funil: ' . $card['Pipeline']['Name'] . '. Card Id: ' . $card['Id'] . ' Título: ' . $card['Title'] . ' Estágio: ' . $card['Stage']['Name'] . ' Status: ' . $card['Status']['Name'] . 'em: ' . $current;
                        }
                    }
                    
                }else{

                    //pipeline é igual ao time do transbordo
                    $pipeline['pipelineId'] = $ploomesCard['Pipeline']['Id'];
                    $pipeline['stageId'] = $ploomesCard['Stage']['Id'];
              
                    $cardJson = self::dealsPloomesJson($pContact['Id'], $owner, $title, $idChat, $pipeline, $dealOrigin);
                    if (!$cardJson) {
                        throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
                    }
    
                    $card = $ploomesServices->updatePloomesDeal($cardJson, $ploomesCard['Id']);
    
                    if ($card) {
                        $message['success'] = 'Card ' . $ploomesCard['Id'] . ' alterado no Ploomes! Em: ' . $current;
                    }
                }

                return $message;

            } else {

                //pega o pipeline pelo nome do time deve ser SDR
                $pipeline['pipelineId'] = $ploomesServices->getPipelineByName($team['name']);
                $pipeline['stageId'] = $ploomesServices->getPipelineStagesByPipelineId($pipeline['pipelineId']);

                $cardJson = self::dealsPloomesJson($pContact['Id'], $owner, $title, $idChat, $pipeline);
                // print_r($cardJson);
                // exit;
                if (!$cardJson) {
                    throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
                }

                $card = $ploomesServices->createPloomesDeal($cardJson);

                if ($card) {
                    $message['success'] = 'Cliente ' . $contact['name'] . ' já cadastrado(a) no Ploomes! Ainda assim, foi criado uma oportunidade de negócio para ele(a) no funil: ' . $card['Pipeline']['Name'] . '. Card Id: ' . $card['Id'] . ' Título: ' . $card['Title'] . ' Estágio: ' . $card['Stage']['Name'] . ' Status: ' . $card['Status']['Name'] . 'em: ' . $current;
                }
            }

            return $message;

        } 
        else 
        {
            //primeira compra
            $contactJson = self::contactPloomesJson($contact);

            if (!$contactJson) {
                throw new WebhookReadErrorException('Não foi possível montar os dados do contato', 500);
            }

            $createContactPloomes = $ploomesServices->createPloomesPerson($contactJson);

            if ($createContactPloomes <= 0) {
                throw new WebhookReadErrorException('Não foi possível cadastrar contato no Ploomes', 500);
            }

            $owner->mailVendedor = $toAgente['email'] ?? $agente['email'];
            $owner->ploomesOwnerId = $ploomesServices->ownerId($owner);

            //pega o pipeline pelo nome do time em caso de transbordo para outro funil
            $pipeline['pipelineId'] = $ploomesServices->getPipelineByName($team['name']);
            $pipeline['stageId'] = $ploomesServices->getPipelineStagesByPipelineId($pipeline['pipelineId']);

            $cardJson = self::dealsPloomesJson($createContactPloomes, $owner, $title, $idChat, $pipeline);

            if (!$cardJson) {
                throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
            }

            $card = $ploomesServices->createPloomesDeal($cardJson);

            if ($card) {
                $message['success'] = 'Cliente ' . $contact['name'] . ' criado(a) no Ploomes CRM com sucesso! Foi criado uma oportunidade de negócio para ele(a) no funil: ' . $card['Pipeline']['Name'] . '. Card Id: ' . $card['Id'] . ' Título: ' . $card['Title'] . ' Estágio: ' . $card['Stage']['Name'] . ' Status: ' . $card['Status']['Name'] . 'em: ' . $current;
            }

            return $message;
        }
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

    public static function dealsPloomesJson($pContact, $owner, $title, $idChat, $pipeline = null, $dealOrigin = null)
    {
        
        $keys = self::getFieldKey();

        

        $otherProperties = [
                [
                    'FieldKey' => $keys['id_chat'],
                    'StringValue' => $idChat,
                ],
                [
                    'FieldKey' => $keys['owner_sdr'],
                    'StringValue' => $owner->owner_sdr,
                ],
                [
                    'FieldKey' => $keys['notes_sdr'],
                    'BigStringValue' => $dealOrigin['notes'] ?? null,
                ]

            ];

         

        $array = [
            'Title' => $title,
            'ContactId' => $pContact,
            'PipelineId' => $pipeline['pipelineId'],
            'StageId' => $pipeline['stageId'],
            'OwnerId' => $owner->ploomesOwnerId,
            'OriginDealId' => $dealOrigin['dealOrigemId'] ?? null,
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
                case 'bicorp_api_responsavel_sdr_out':
                    $keys['owner_sdr'] = $custom['Key'];
                    break;
                case 'bicorp_api_observacoes_negocio_out':
                    $keys['notes_sdr'] = $custom['Key'];
                    break;
            }
        }
        return $keys;
    }
}
