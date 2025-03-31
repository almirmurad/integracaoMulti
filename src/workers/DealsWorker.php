<?php

require_once 'C:/xampp/htdocs/gamatermic/vendor/autoload.php';
require_once 'C:/xampp/htdocs/gamatermic/src/services/RabbitMQServices.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use src\exceptions\ClienteInexistenteException;

// Conectar ao RabbitMQ
$connection = new AMQPStreamConnection('145.223.29.82', 5672, 'gamatermic', '137901');
$channel = $connection->channel();
try{
    // Declarar a exchange

    $channel->exchange_declare('deals_exc', 'x-delayed-message', false, true, false, false, false,[
        'x-delayed-type' => ['S', 'topic']
    ]);
    $channel->exchange_declare('deals_exc_trash', 'direct', false, true, false);

}catch(Exception $e){
    print $e->getMessage();
    exit;
}

// Declarar a fila de deals
$queue_name = 'ploomes_deals';
$trash_queue_name = 'ploomes_deals_trash';

    $channel->queue_declare($queue_name, false, true, false, false, false, [   // Argumentos para a DLX
        'x-dead-letter-exchange' =>  ['S', 'deals_exc_wait'],  // Define a Dead Letter Exchange
        'x-dead-letter-routing-key' => ['S', 'Ploomes.Deals.Wait'] // Define a Routing Key para a DLX
    ]);
    $channel->queue_declare('ploomes_deals_trash', false, true, false, false, false);

//binding_keys = origem.entidade.ação
$binding_key = 'Ploomes.Deals';
$trash_binding_key = 'Ploomes.Deals.Trash';
//bind entre a fila e a exchange
$channel->queue_bind($queue_name, 'deals_exc', $binding_key);
$channel->queue_bind($trash_queue_name, 'deals_exc_trash', $trash_binding_key);


// Função callback para processar as mensagens da fila
// $callback = function($msg) {
//     try {

//         if (isset($msg->get('application_headers')['x-death'])) {
//             $xDeath = $msg->get('application_headers')['x-death'];
//             $retryCount = $xDeath[0]['count']; // Quantidade de tentativas
            
//             if ($retryCount >= 3) {
//                 // Manda para fila de erro definitiva ou log deals_exc_trash

//                 // Publica a mensagem na fila
//                 $this->channel->basic_publish($msg, 'deals_exc_trash');
//                 throw new ClienteInexistenteException('Mensagem reprocessada por mais de 3x, encaminhada a fila de lixo',500);
//             }
//             //processa mensagem
//             $headers = [
    
//                 'Content-Type: application/json',
//             ];
//             // $uri = 'https://gamatermic.bicorp.online/public/processWinDeal';
//             $uri = 'http://localhost/gamatermic/public/processWinDeal';
            
//             $curl = curl_init();
            
//                     curl_setopt_array($curl, array(
//                         CURLOPT_URL => $uri,
//                         CURLOPT_RETURNTRANSFER => true,
//                         CURLOPT_ENCODING => '',
//                         CURLOPT_MAXREDIRS => 10,
//                         CURLOPT_TIMEOUT => 0,
//                         CURLOPT_FOLLOWLOCATION => true,
//                         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//                         CURLOPT_CUSTOMREQUEST => 'POST',
//                         CURLOPT_POSTFIELDS =>$msg->getBody(),
//                         CURLOPT_HTTPHEADER => $headers
            
//                     ));
            
//                     $r = curl_exec($curl);
//                     $response = json_decode($r, true);
        
//                     curl_close($curl);
        
//                     // print'worker'.PHP_EOL;
//                     // print_r($r);
//                     // print_r($response);
//                     // exit;
                    
//                     if(isset($response) && $response['status_code'] === 200){
//                         // Reconhece a mensagem
//                         print_r($response['status_message']);
//                         $msg->ack();
//                     }else{
//                         $statusMessage = isset($response['status_message']) ? $response['status_message'] : 'Menságem indefinida';
//                         throw new ClienteInexistenteException($statusMessage,500);
//                     }   

//         }
        
   
//         } catch (ClienteInexistenteException $e) {
//             // Lida com o erro
//             echo $e->getMessage() ?: 'Mensagem não disponível', "\n";
//             $msg->nack(false, false);
//         }
// };
$callback = function($msg) use ($channel,$trash_binding_key) {
    try {
        // Verifica se a mensagem já foi reprocessada anteriormente
        $application_headers = $msg->get('application_headers');
        $xDeath = isset($application_headers['x-death']) ? $application_headers['x-death'] : [];

        // Contagem de tentativas
        $retryCount = !empty($xDeath) ? $xDeath[0]['count'] : 0;
        
        if ($retryCount >= 3) {
            // Enviar para fila de trash após 3 tentativas

            // Cria uma nova mensagem para enviar para a fila trash
            $newMsg = new AMQPMessage($msg->getBody(), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]);

            $channel->basic_publish($newMsg, 'deals_exc_trash', $trash_binding_key);

            // Reconhece a mensagem na fila wait para removê-la
            $msg->ack();  // Importante! Isso remove a mensagem da fila wait

            throw new ClienteInexistenteException('Mensagem reprocessada mais de 3x, enviada para o lixo', 500);
        }
        
        
        // Processa a mensagem aqui
        $headers = ['Content-Type: application/json'];
        $uri = 'http://localhost/gamatermic/public/processWinDeal';
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $msg->getBody(),
            CURLOPT_HTTPHEADER => $headers
        ]);
        
        //$response = json_decode(curl_exec($curl), true);
        $response = curl_exec($curl);
        curl_close($curl);

        print_r($response);
        exit;
        
        if (isset($response['status_code']) && $response['status_code'] === 200) {
            // Mensagem processada com sucesso
            $msg->ack();
            echo "Mensagem processada: " . $response['status_message'] . PHP_EOL;
        } else {
            $statusMessage = $response['status_message'] ?? 'Mensagem indefinida';
            throw new ClienteInexistenteException($statusMessage, 500);
        }
    } catch (ClienteInexistenteException $e) {
        // Se o processamento falhar, não reconheça a mensagem (nack)
        echo "Erro ao processar mensagem: " . $e->getMessage() . PHP_EOL;
       // $msg->nack(false, false); // reenvia para a fila de DLX
       if ($retryCount < 3) {
        // Se a mensagem ainda não foi reprocessada 3 vezes, não reconheça e reencaminhe para a DLX
        $msg->nack(false, false); // Retorna para a DLX
    } 
    }
};

// Consumir as mensagens da fila
$channel->basic_qos(null, 1, false); //dispacha a mensagem apenas quando a anterior estiver sido processada
$channel->basic_consume($queue_name, '', false, false, false, false, $callback);

// Loop para manter o script rodando
while($channel->is_consuming()) {
    $channel->wait();
}

// Fechar conexões
$channel->close();
$connection->close();


