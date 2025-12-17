<?php

namespace src\services;
// Arquivo: RabbitMQService.php

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use src\exceptions\WebhookReadErrorException;

class RabbitMQServices {
    private $connection;
    private $channel;
    private $vhost;

    public function __construct($vhost)
    {
        // print_r($vhost);
        // exit;
        $this->vhost = $vhost;
        $this->connect();
        
    }
   
    /**
     * Tenta criar a conexão e o canal
     */
    private function connect() {
        $pass = $this->decrypt($this->vhost);

        try {
            $this->connection = new AMQPStreamConnection(
                $this->vhost['ip_vhost'], 
                $this->vhost['port_vhost'], 
                $this->vhost['user_vhost'], 
                $pass, 
                $this->vhost['name_vhost'],
                false,
                'AMQPLAIN',
                null,
                'en_US',
                30.0,   // connection_timeout
                60.0,   // read_write_timeout
                null,   // ssl_options
                false,  // keepalive
                120      // heartbeat (mantém viva a conexão)
            );

            $this->channel = $this->connection->channel();

        } catch (Exception $e) {
            error_log("Erro ao conectar no RabbitMQ: " . $e->getMessage());
            throw new WebhookReadErrorException("Erro ao conectar no RabbitMQ: " .$e->getMessage(), 500);
        }
    }

    /**
     * Reconecta caso a conexão/canal tenham caído
     */
    private function ensureConnection() {
        if (!$this->connection->isConnected()) {
            $this->connect();
        }
    }

    // public function __construct($vhost) {

    //     $pass = $this->decrypt($vhost);
      
    //     // Conecta ao RabbitMQ
    //     $this->connection = new AMQPStreamConnection(
    //         $vhost['ip_vhost'], 
    //         $vhost['port_vhost'], 
    //         $vhost['user_vhost'], 
    //         $pass, 
    //         $vhost['name_vhost'],
    //         false,
    //         'AMQPLAIN',
    //         null,
    //         'en_US',
    //         30.0,
    //         30.0
    //     );
    //     $this->channel = $this->connection->channel();
    // }

    private function decrypt($vhost){
        $cpf_cnpj = $vhost['key']; // A mesma URL usada antes
        $key = hash('sha256', $cpf_cnpj, true); // Gera a mesma chave de 32 bytes
        $encryptedData = base64_decode($vhost['pass']); // Recupera do banco
        $iv = substr($encryptedData, 0, 16); // Extrai IV
        $encryptedPass = substr($encryptedData, 16);
        $decryptedPass = openssl_decrypt($encryptedPass, 'aes-256-cbc', $key, 0, $iv);

        return $decryptedPass;
    }
    
    // public function publicarMensagem(string $exName, array $rk, string $fila, string $mensagem) 
    // {   
    //     $this->ensureConnection(); // garante que conexão esteja ativa
    //     //Routing_key
    //     $routing_key = isset($rk) && !empty($rk) ? $rk[0].'.'.$rk[1] : 'Origem.Entity';
    //     //Routing_key wait (aguardar para reprocessar)
    //     $routing_key_dlx = isset($rk) && !empty($rk) ? $rk[0].'.'.$rk[1].'.Wait' : 'Origem.Entity.Wait';
    //     $routing_key_trash = isset($rk) && !empty($rk) ? $rk[0].'.'.$rk[1].'.Trash' : 'Origem.Entity.Trash';
    //     try{

    //         //declara a exchange do tipo topic
    //         $this->channel->exchange_declare($exName, 'x-delayed-message', false, true, false, false, false,[
    //             'x-delayed-type' => ['S', 'topic']
    //         ]);
    //         //declara a exchange do tipo topic wait (aguardar para reprocessar)
    //         $this->channel->exchange_declare($exName.'_wait', 'x-delayed-message', false, true, false, false, false,[
    //             'x-delayed-type' =>  ['S', 'topic']
    //         ]);
    //         //declara a exchange do tipo direct trash (lixo)
    //         $this->channel->exchange_declare($exName.'_trash', 'direct', false, true, false, false, false);
    //     }catch(Exception $e){

    //         print $e->getMessage();
    //         exit;
    //     }
    //     try{
    //         // Garante que a fila existe
    //         $this->channel->queue_declare($fila, false, true, false, false, false, [   // Argumentos para a DLX
    //             'x-dead-letter-exchange' =>  ['S', $exName . '_wait'],  // Define a Dead Letter Exchange
    //             'x-dead-letter-routing-key' => ['S', $routing_key_dlx] // Define a Routing Key para a DLX
    //         ]);
    //         // Garante que a fila existe wait (aguardar para reprocessar)
    //         $this->channel->queue_declare($fila.'_wait', false, true, false, false, false,
    //         [
    //             'x-message-ttl' =>  ['I', 30000],  // A mensagem na fila DLX terá um TTL de 30 segundos
    //             'x-dead-letter-exchange' => ['S', $exName], // Volta para a exchange original
    //             'x-dead-letter-routing-key' =>['S', $routing_key], // Volta com a mesma routing key
    //         ]);
    //         // Garante que a fila existe trash (Lixo após 3 tentativas de processamento)
    //         $this->channel->queue_declare($fila.'_trash', false, true, false, false, false);
    //     }catch(Exception $e){

    //         print $e->getMessage();
    //         exit;

    //     }

    //     // Associar a Dead Letter Queue à Dead Letter Exchange
    //     $this->channel->queue_bind($fila, $exName, $routing_key);                  
    //     $this->channel->queue_bind($fila.'_wait', $exName.'_wait',$routing_key_dlx);
    //     $this->channel->queue_bind($fila.'_trash', $exName.'_trash', $routing_key_trash);                  
    //     // Cria a mensagem com atraso de 4 segundos
    //     $headers = new AMQPTable(['x-delay' => 3000]);
    //     $msg = new AMQPMessage($mensagem, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, 'application_headers' => $headers]);
        
    //     // Publica a mensagem na fila
    //     $this->channel->basic_publish($msg, $exName, $routing_key);

    // }

    public function publicarMensagem(string $exName, array $rk, string $fila, string $mensagem) {
        $this->ensureConnection(); // garante que conexão esteja ativa

        $routing_key        = !empty($rk) ? $rk[0].'.'.$rk[1] : 'Origem.Entity';
        $routing_key_dlx    = $routing_key.'.Wait';
        $routing_key_trash  = $routing_key.'.Trash';

        try {
            // Exchanges
            $this->channel->exchange_declare($exName, 'x-delayed-message', false, true, false, false, false, [
                'x-delayed-type' => ['S', 'topic']
            ]);
            $this->channel->exchange_declare($exName.'_wait', 'x-delayed-message', false, true, false, false, false, [
                'x-delayed-type' => ['S', 'topic']
            ]);
            $this->channel->exchange_declare($exName.'_trash', 'direct', false, true, false, false, false);

            // Filas
            $this->channel->queue_declare($fila, false, true, false, false, false, [
                'x-dead-letter-exchange'    => ['S', $exName . '_wait'],
                'x-dead-letter-routing-key' => ['S', $routing_key_dlx]
            ]);
            $this->channel->queue_declare($fila.'_wait', false, true, false, false, false, [
                'x-message-ttl'             => ['I', 30000],
                'x-dead-letter-exchange'    => ['S', $exName],
                'x-dead-letter-routing-key' => ['S', $routing_key],
            ]);
            $this->channel->queue_declare($fila.'_trash', false, true, false, false, false);

            // Bind
            $this->channel->queue_bind($fila, $exName, $routing_key);                  
            $this->channel->queue_bind($fila.'_wait', $exName.'_wait', $routing_key_dlx);
            $this->channel->queue_bind($fila.'_trash', $exName.'_trash', $routing_key_trash);                  

            // Mensagem com delay
            $headers = new AMQPTable(['x-delay' => 3000]);
            $msg = new AMQPMessage($mensagem, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => $headers
            ]);
            
            $this->channel->basic_publish($msg, $exName, $routing_key);

        } catch (Exception $e) {
            error_log("Erro ao publicar mensagem: " . $e->getMessage());
            // tenta reconectar uma vez
            $this->connect();
            throw new WebhookReadErrorException("Erro ao publicar mensagem: " . $e->getMessage(), 500);
        }
    }

    public function getConnection(){
        return $this->connection;
    }

    public function getChannel(){
        return $this->channel;
    }

    public function __destruct() {
    try {
        if ($this->channel && $this->channel->is_open()) {
            $this->channel->close();
        }
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
    } catch (\Exception $e) {
        // só loga, não deixa estourar fatal
        error_log("Erro ao fechar RabbitMQ: " . $e->getMessage());
        throw new WebhookReadErrorException("Erro ao fechar RabbitMQ: " . $e->getMessage(), 500);
    }
}


    // public function __destruct() {
    //     // Fecha conexões e canais
    //     $this->channel->close();
    //     $this->connection->close();
    // }
}
