<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;

#[Signature('app:consumer-rabbti-m-q')]
#[Description('Command description')]
class ConsumerRabbtiMQ extends Command
{
    /**
     * Execute the console command.
     */

    public function handle()
    {
        $connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST'),
            env('RABBITMQ_PORT'),
            env('RABBITMQ_USER'),
            env('RABBITMQ_PASSWORD'),
            '/',
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0,
            120.0, 
            null,
            false,
            60    
        );

        $channel = $connection->channel();

        $channel->queue_declare(
            'orders.queue',
            false,
            true,
            false,
            false
        );

        $channel->basic_qos(null, 1, null);

        $this->info('Aguardando mensagem');

        $callback = function($msg) {
            try {
                $data = json_decode($msg->body, true);

                if (!is_array($data)) {
                    echo "Mensagem inválida\n";

                    $msg->delivery_info['channel']->basic_ack(
                        $msg->delivery_info['delivery_tag']
                    );

                    return;
                }

                $order = $data['data'];
                $orderId = $order['order_id'];
                $routingKey =  $msg->delivery_info['routing_key'];

                if(Redis::exists("processed_event:$orderId")) {
                    echo "Pedido {$orderId} já processado \n ";

                    $msg->delivery_info['channel']->basic_ack(
                        $msg->delivery_info['delivery_tag']
                    );
                    return;
                }
                $lockKey = "lock:$orderId";
                $lock = Redis::set($lockKey, true, 'NX','EX', 60);
                
                if(!$lock) {
                    echo "Produto {$orderId} ja esta sendo processado\n";
                    $msg->delivery_info['channel']->basic_nack(
                        $msg->delivery_info['delivery_tag'],
                        false,
                        true,
                    );
                    return;
                }
                
                echo "Processando pedido:{$orderId}\n" ;
                echo "Name:{$order['name']}\n";
                echo "Email:{$order['email']}\n";
                echo "Qantity:{$order['quantity']}\n";
                echo "price:{$order['price']}\n";
                echo "Routing Key:{$routingKey}\n";
                echo "Timetamp:{$data['timestamp']}\n";
                echo "---------------------------------\n";
                sleep(1);

                if($routingKey == 'order.created') {
                    echo "Pedido recebido:{$orderId}\n";
                } elseif ($routingKey == 'order.confimed') {
                    echo "Pedido confirmado: {$orderId}\n";
                }

                Redis::setex("processed_event:$orderId", 3600, true);
                echo "Finalizado {$orderId}\n";
                
                // $msg->ack();
                $msg->delivery_info['channel']->basic_ack(
                    $msg->delivery_info['delivery_tag']
                );
            } catch (\Throwable $e) {
                echo "Erro: " .$e->getMessage() . "\n";

                $msg->delivery_info['channel']->basic_nack(
                    $msg->delivery_info['delivery_tag'],
                    false,
                    false
                );
            }
        };

        $channel->exchange_declare(
            'orders.exchange',
            'direct',
            false,
            true,
            false,
            false
        );

        $channel->queue_bind(
            'orders.queue',
            'orders.exchange',
            'order.created'
        );

        $channel->basic_consume(
            'orders.queue',
            '',
            false,
            false,
            false,
            false,
            $callback
        );

        while (true) {
            try {
                $channel->wait();
            } catch (\Throwable $e) {
                echo "Erro na conexão: " . $e->getMessage() . "\n";
                break;
            }
        }
    }
}
