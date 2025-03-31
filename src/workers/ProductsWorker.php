<?php
require '/opt/consumers/vendor/autoload.php';
use Dotenv\Dotenv;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$dotenv = Dotenv::createUnsafeImmutable('/opt/consumers/gamatermic', '.env');
$dotenv->load();

// Função para criar e retornar a conexão RabbitMQ
function createConnection() {
    try {
        $connection = new AMQPStreamConnection(
            $_ENV['IP'],
            $_ENV['PORT'],
            $_ENV['RABBITMQ_USER'],
            $_ENV['RABBITMQ_PASS'],
            $_ENV['RABBITMQ_VHOST']
        );
        return $connection;
    } catch (Exception $e) {
        echo "Erro na conexão: " . $e->getMessage() . "\n";
        sleep(5); // Espera 5 segundos antes de tentar reconectar
        return createConnection(); // Tenta reconectar
    }
}

$connection = createConnection();
$channel = $connection->channel();

try {
    // Declarar exchanges
    $channel->exchange_declare('products_exc', 'x-delayed-message', false, true, false, false, false, [
        'x-delayed-type' => ['S', 'topic']
    ]);
    $channel->exchange_declare('products_exc_trash', 'direct', false, true, false);

    // Declarar as filas
    $queue_name = 'omie_products';
    $trash_queue_name = 'omie_products_trash';

    // Fila principal com DLX
    $channel->queue_declare($queue_name, false, true, false, false, false, [
        'x-dead-letter-exchange' => ['S', 'products_exc_wait'],
        'x-dead-letter-routing-key' => ['S', 'Omie.Products.Wait']
    ]);
    // Fila de trash
    $channel->queue_declare($trash_queue_name, false, true, false, false, false);

    // Binding entre a fila e a exchange
    $binding_key = 'Omie.Products';
    $trash_binding_key = 'Omie.Products.Trash';
    $channel->queue_bind($queue_name, 'products_exc', $binding_key);
    $channel->queue_bind($trash_queue_name, 'products_exc_trash', $trash_binding_key);

} catch (Exception $e) {
    echo "Erro na configuração: " . $e->getMessage() . "\n";
    exit;
}
$isAcked = false;
// Função callback para processar as mensagens da fila
$callback = function($msg) use ($channel, $trash_binding_key) {
	$isAcked = false;
    try {
        $application_headers = $msg->get('application_headers');
        $xDeath = isset($application_headers['x-death']) ? $application_headers['x-death'] : [];
        $retryCount = !empty($xDeath) ? $xDeath[0]['count'] : 0;

        if ($retryCount >= 3) {
            // Enviar para fila de trash após 3 tentativas
            $newMsg = new AMQPMessage($msg->getBody(), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]);

            $channel->basic_publish($newMsg, 'products_exc_trash', $trash_binding_key);
            $msg->ack(); // Reconhece a mensagem na fila wait para removê-la
	    $isAcked = true;
            throw new Exception('Mensagem reprocessada mais de 3x, enviada para o lixo', 500);
        }

        // Processa a mensagem
       $headers = ['Content-Type: application/json'];
        $uri = 'https://gamatermic.bicorp.online/public/processNewContact';
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $msg->getBody(),
            CURLOPT_HTTPHEADER => $headers
        ]);

        $response = json_decode(curl_exec($curl), true);
	//$response = curl_exec($curl);
        curl_close($curl);

	//print_r($response);
        //exit;

        if (isset($response['status_code']) && $response['status_code'] === 200) {
            $msg->ack(); // Mensagem processada com sucesso
            echo "Mensagem processada: " . $response['status_message']. PHP_EOL;
        } else {
            $statusMessage = $response['status_message'] ?? 'Mensagem indefinida';
            throw new Exception($statusMessage, 500);
        }
    } catch (Exception $e) {
        echo "Erro ao processar mensagem: " . $e->getMessage() . PHP_EOL;

        // Se a mensagem falhar e ainda não atingiu o limite de tentativas, ela volta para a DLX
        if (!$isAcked) {
    	    $msg->nack(false, false); // Retorna para a DLX
    	}
    }
};

// Consumir as mensagens da fila
$channel->basic_qos(null, 1, false); // Dispacha a mensagem apenas quando a anterior for processada
$channel->basic_consume($queue_name, '', false, false, false, false, $callback);

// Loop para manter o script rodando
while ($channel->is_consuming()) {
    $channel->wait();
}

// Fechar conexões
$channel->close();
$connection->close();