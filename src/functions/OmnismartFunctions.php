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

        $assignAgent = [];
        $pipeline = [];
        $owner = new stdClass();
        //owner SDR é o agent que o bot transborda  a primeira vez
        // $owner->owner_sdr = $decoded['data']['steps'][2]['agent']['name'] ?? $decoded['data']['steps'][2]['toAgent']['name'];
        $originTeam = $decoded['data']['steps'][1]['toTeam'] ?? 'PROSPECÇÃO';

        $idChat = $decoded['data']['_id'];
        //atendimento atribuido ao agente independente de quem enviou
        if ($action['type'] !== 'ASSIGN') {
            throw new WebhookReadErrorException('Não era uma atribuição', 500);       
        }
        if($idChat == null) 
        {
            throw new WebhookReadErrorException('Chat inexistente', 500);
        }

        $chat = $omnismartServices->chatGetOne($idChat);
        $contact = $chat['contact'];
        $transfers = $chat['transfers'];
        $assignAgent = $chat['agent'];
        $assignTeam  = $chat['team'];
      
        //  elseif ($action['type'] === 'SUPToAGENT') {

        //     foreach ($decoded['data']['steps'] as $step) {

        //         switch ($step['type']) {
        //             case 'transferAgent':
        //                 $agente =  $step['toAgent'];
        //                 $supervisor = $step['agent'];
        //                 break;
        //             case 'transferTeam':
        //                 $thisTeam = $step['toTeam'];
        //                 break;
        //         }
        //     }

        // }
        // elseif ($action['type'] === 'AGENTToAGENT') {

        //     foreach ($decoded['data']['steps'] as $step) 
        //     {
        //         switch($step['type']){
        //             case 'transferTeam':
        //                 $thisTeam = $step['toTeam'];
        //                 break;
        //             case 'transferAgent':
        //                 $toAgente =  $step['toAgent'];
        //                 $allTeams = $step['toAgent']['teams'];
        //                 foreach($allTeams as $oneTeam){
        //                     $dataTeam = $omnismartServices->getTeamById($oneTeam);
        //                     //print $dataTeam['name'];
        //                     if(mb_strtolower($dataTeam['name']) === mb_strtolower($thisTeam['name']))
        //                     {
        //                         $team['name'] = $dataTeam['name'];
        //                     }
                    
        //                 }
        //                 $agente =  $step['agent'];
        //                 break;   
        //         }
        //     }           
        // } elseif ($action['type'] === 'BOTToAGENT') {

        //     foreach ($decoded['data']['steps'] as $step) {

        //         switch ($step['type']) {
        //             case 'transferAgent':
        //                 $toAgente =  $step['toAgent'];
        //                 break;
        //             case 'transferTeam':
        //                 $team = $step['toTeam'];
        //                 $agente = $step['agent'];
        //                 break;
        //         }
        //     }
        // }
        $owner->owner_sdr = $decoded['data']['steps'][2]['agent']['name'] ?? $decoded['data']['steps'][2]['toAgent']['name'];
        $owner->mailVendedor = $assignAgent['email'] ?? null;
        ($owner->mailVendedor) ? $owner->ploomesOwnerId = $ploomesServices->ownerId($owner) : $owner->ploomesOwnerId = null;
        $contact['ownerId'] = $owner->ploomesOwnerId;
        $contactJson = self::contactPloomesJson($contact);

        if (!$contactJson) {
            throw new WebhookReadErrorException('Não foi possível montar os dados do contato', 500);
        }

        //verifica se o cliente já existe no ploomes
        $pContact = $ploomesServices->getClientByPhone(DiverseFunctions::formatarTelefone($contact['telephones'][0]));        

        //primeiro contato time de PROSPECÇÃO
        if(!$pContact) 
        {
            // print 'não existe a pessoa vai criar a pessoa e o card no funil de prospecção através do assignTeam (nome do time do agente que aceitouo chamado)';
            
            $createContactPloomes = $ploomesServices->createPloomesPerson($contactJson);

            if ($createContactPloomes <= 0) {
                throw new WebhookReadErrorException('Não foi possível cadastrar contato no Ploomes', 500);
            }
            //busca o id do pipeline de prospecção
            $pipeline['pipelineId'] = $ploomesServices->getPipelineByName($originTeam['name']);
            $pipeline['stageId'] = $ploomesServices->getPipelineStagesByPipelineId($pipeline['pipelineId']);
            //titulo do card
            $title =  "Integração Omnismart - [Cliente {$contact['name']}] Atendimento atribuído ao Agente {$assignAgent['name']} do time {$originTeam['name']}";

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
        else //recompra ou transferência ao time de vendas
        {
            //se o contato já existe:
            //1)Atualiza os dados do contato caso seja alterado pelo operador do Omnismart;
            //2) verifica se o card existe no pipeline de destino;(Se for um transbordo para equipe de vendas)
            //2.1) Se não existir é um transbordo para equipe de vendas, buscar o card em prospecção pegar as informações de origem
            //2.2) se o card existir no pipeline de destino, verifica se existe OriginId;
            //2.3) se existir OriginId verificar se é o mesmo Pipeline se for não pode alterar o OriginId se Não for pode incluir
            //2.4) Se for um transbordo para equipe de prospecção, ele deve ignorar o OriginId
            //3) verificar se o card está fechado
            //3.1) se estiver fechado precisa mandar uma interação no cliente informando que entrou em contato após o fechamento do card e mensagem no omnisamart pra informar que o card precisa ser reaberto
            //3.2) se estiver aberto pode atualizar tudo.
        
            //1) atualiza os dados do cliente ou se o email foi alterado pelo sdr no omnismart.
            $ploomesServices->updatePloomesContact($contactJson, $pContact['Id']);

            //se for um transbordo de origem para venda
         
            $total = count($transfers) - 1;

            for($x = 0; $x <= $total; $x++)
            {
                if($x === $total){
                            
                    $i = $x - 2;
                    $c = $i - 1;

                    if($transfers[$i]['type'] === 'agent'){       
                        
                        if(isset($transfers[$i]['teamForced'])){

                            $forwardTeam = $omnismartServices->getTeamById($transfers[$i]['teamForced']);

                        }else{

                            if($transfers[$c]['type'] === 'agent'){
                               
                                if(isset($transfers[$c]['teamForced'])){

                                    $forwardTeam = $omnismartServices->getTeamById($transfers[$c]['teamForced']);
                                }else{
                                     $forwardTeam = $omnismartServices->getTeamById($transfers[$c]['to']);
                                }
        
                            }else{

                                $forwardTeam = $omnismartServices->getTeamById($transfers[$c]['to']);
                            }
                        }
                    }else{
                        
                        $forwardTeam = $omnismartServices->getTeamById($transfers[$c]['to']);
                    }
                }
           
            }

            $pipeline['pipelineId'] = $ploomesServices->getPipelineByName($forwardTeam['name']);
            $pipeline['stageId'] = $ploomesServices->getPipelineStagesByPipelineId($pipeline['pipelineId']);
         
            //2) verifica se o card já existe no ploomes no pipeline prospeccção (time de origem)
            $ploomesCardOrigin = self::getCardByIdChat($idChat, $ploomesServices, $pipeline); 
                                                             
            //2.2) se o card existir no pipeline de destino, verifica se existe OriginId;
            if ($ploomesCardOrigin !== null) 
            {             
                //se houver um card com id do chat no time de origem
                $cardCustomFields = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($ploomesCardOrigin['OtherProperties'], 'Negócio');

                $pipeline['pipelineId'] = $ploomesServices->getPipelineByName($assignTeam['name']);
                $title =  "Integração Omnismart - [Cliente {$contact['name']}] Atendimento atribuído ao Agente {$assignAgent['name']} do time {$assignTeam['name']}";
                
                $ploomesCardDestin = self::getCardByIdChat($idChat, $ploomesServices, $pipeline);
                
                if($ploomesCardDestin !== null)
                {
                    //mantém o card no mesmo estágio em que já estava
                    $pipeline['stageId'] = $ploomesCardDestin['StageId'];
                    $cardCustomFields = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($ploomesCardOrigin['OtherProperties'], 'Negócio');

                    $dealOrigin = [];

                    //2.3) se existir OriginId verificar se é o mesmo card se for não pode alterar o OriginId se Não for pode incluir
                    if(!isset($ploomesCardDestin['OriginDealId'])){
                        $dealOrigin['dealOrigemId'] = null;
                    }elseif(isset($ploomesCardDestin['OriginDealId']) && $ploomesCardDestin['OriginDealId'] === $ploomesCardDestin['Id'] ){
                        //2.3) se existir OriginId verificar se é o mesmo Pipeline
                        $dealOrigin['dealOrigemId'] = null;
                    }elseif(isset($ploomesCardDestin['OriginDealId']) && $ploomesCardDestin['OriginDealId'] !== $ploomesCardDestin['Id'] ){
                        //2.3) se existir OriginId verificar se é o mesmo Pipeline
                        $dealOrigin['dealOrigemId'] = $ploomesCardDestin['OriginDealId'];
                    }

                    $dealOrigin['notes'] = $cardCustomFields['bicorp_api_observacoes_negocio_out'] ?? null;

                    //3) verificar se o card está fechado
                    $dealOrigin['status'] = match($ploomesCardDestin['StatusId'])
                    {
                        1 => 'aberto',
                        2 => 'ganho',
                        3 => 'perdido'
                    };
                
                    $cardJson = self::dealsPloomesJson($pContact['Id'], $owner, $title, $idChat, $pipeline, $dealOrigin);
                    
                    if($dealOrigin['status'] !== 'aberto'){
                        //3.1) se estiver fechado precisa mandar uma interação no cliente informando que entrou em contato após o fechamento do card e mensagem no omnisamart pra informar que o card precisa ser reaberto

                        $content = "ATENÇÃO! - Card [{$ploomesCardDestin['Id']}] se encotra {$dealOrigin['status']}, mas houve uma interação no chat do Omnismart e o atendimento foi atribuido ao agente {$assignAgent['name']} do time {$assignTeam['name']}. Reabra o card e atribua o responsável manualmente para dar continuidade ao atendimento.";

                        $arrayInteractionMessage=[
                            'DealId' => $ploomesCardDestin['Id'],
                            'ContactId' => $pContact['Id'],
                            'Content' => $content,
                            'Title' => "Interação em cliente com o card {$dealOrigin['status']}"
                        ];

                        ($ploomesServices->createPloomesIteraction(json_encode($arrayInteractionMessage))) ? $message['success'] = "Card {$ploomesCardDestin['Id']} se encontra {$dealOrigin['status']}. Foi enviado uma interação no card e no cliente solicitando reabertura. {$current} " : throw new WebhookReadErrorException("Card {$ploomesCardDestin['Id']} se encontra {$dealOrigin['status']}. Hpuve um erro ao enviae interação no card e no cliente solicitando reabertura. {$current}");

                    }else{
                        //3.2) se estiver aberto pode atualizar tudo.

                        $card = $ploomesServices->updatePloomesDeal($cardJson, $ploomesCardDestin['Id']);
        
                        if ($card) {
                            $message['success'] = 'Card ' . $ploomesCardDestin['Id'] . ' alterado no Ploomes! Em: ' . $current;
                        }
                    }

                }else
                {
                   
                    //pega os campos customizados
                    $cardCustomFields = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($ploomesCardOrigin['OtherProperties'], 'Negócio');

                    $dealOrigin = [];
                    //2.3) inclui o id do card de prospecção como origem do card de vendas bem como as observações
                    $dealOrigin['dealOrigemId'] = $ploomesCardOrigin['Id'];
                    $dealOrigin['notes'] = $cardCustomFields['bicorp_api_observacoes_negocio_out'] ?? null;

                    //coloca o pipeline sempre no primeiro estágio pq ele ainda não existe no pipeline de destino
                    $pipeline['stageId'] = $ploomesServices->getPipelineStagesByPipelineId($pipeline['pipelineId']);
                    $title =  "Integração Omnismart - [Cliente {$contact['name']}] Atendimento atribuído ao Agente {$assignAgent['name']} do time {$assignTeam['name']}";
                    
                    //cria o card no pipeline de vendas com os dados do card de prospecção
                    $cardJson = self::dealsPloomesJson($pContact['Id'], $owner, $title, $idChat, $pipeline, $dealOrigin);
                    if (!$cardJson) {
                        throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
                    }

                    $card = $ploomesServices->createPloomesDeal($cardJson);

                    if ($card) {
                        $message['success'] = 'Cliente ' . $contact['name'] . ' já cadastrado(a) no Ploomes! Ainda assim, foi criado uma oportunidade de negócio para ele(a) no funil: ' . $card['Pipeline']['Name'] . '. Card Id: ' . $card['Id'] . ' Título: ' . $card['Title'] . ' Estágio: ' . $card['Stage']['Name'] . ' Status: ' . $card['Status']['Name'] . 'em: ' . $current;
                    }
                }                   
            }
            else
            {
                throw new WebhookReadErrorException('Não foi possível encontrar o card de origem no Ploomes CRM', 500);
                
                // //pega os campos customizados
                // $cardCustomFields = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($ploomesCardOrigin['OtherProperties'], 'Negócio');

                // $dealOrigin = [];
                // //2.3) inclui o id do card de prospecção como origem do card de vendas bem como as observações
                // $dealOrigin['dealOrigemId'] = $ploomesCard['Id'];
                // $dealOrigin['notes'] = $cardCustomFields['bicorp_api_observacoes_negocio_out'] ?? null;

                // //busca o id do pipeline de vendas
                // $pipeline['pipelineId'] = $ploomesServices->getPipelineByName($assignTeam['name']);
                // $pipeline['stageId'] = $ploomesServices->getPipelineStagesByPipelineId($pipeline['pipelineId']);
                // $title =  "Integração Omnismart - [Cliente {$contact['name']}] Atendimento atribuído ao Agente {$assignAgent['name']} do time {$assignTeam['name']}";
                
                // //cria o card no pipeline de vendas com os dados do card de prospecção
                // $cardJson = self::dealsPloomesJson($pContact['Id'], $owner, $title, $idChat, $pipeline, $dealOrigin);
                // if (!$cardJson) {
                //     throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
                // }

                // $card = $ploomesServices->createPloomesDeal($cardJson);

                // if ($card) {
                //     $message['success'] = 'Cliente ' . $contact['name'] . ' já cadastrado(a) no Ploomes! Ainda assim, foi criado uma oportunidade de negócio para ele(a) no funil: ' . $card['Pipeline']['Name'] . '. Card Id: ' . $card['Id'] . ' Título: ' . $card['Title'] . ' Estágio: ' . $card['Stage']['Name'] . ' Status: ' . $card['Status']['Name'] . 'em: ' . $current;
                // }
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

         
        if(!isset($dealOrigin['dealOrigemId'])){

            $array = [
            'Title' => $title,
            'ContactId' => $pContact,
            'PipelineId' => $pipeline['pipelineId'],
            'StageId' => $pipeline['stageId'],
            'OwnerId' => $owner->ploomesOwnerId,
            'OtherProperties' => $otherProperties,
        ];

        }else{

            $array = [
                'Title' => $title,
                'ContactId' => $pContact,
                'PipelineId' => $pipeline['pipelineId'],
                'StageId' => $pipeline['stageId'],
                'OwnerId' => $owner->ploomesOwnerId,
                'OriginDealId' => $dealOrigin['dealOrigemId'],
                'OtherProperties' => $otherProperties,
            ];
        }
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
