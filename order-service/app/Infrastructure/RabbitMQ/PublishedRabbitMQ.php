<?php

namespace App\Infrastructure\RabbitMQ;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class PublishedRabbitMQ
{
    public function publish($data, $routin_key)
    {
        Log::info('Mensagem publicada', ['data' => $data, 'routing_key' => $routin_key]);
        $connetion = new AMQPStreamConnection(
          env('RABBITMQ_HOST'),
          env('RABBITMQ_PORT'),
          env('RABBITMQ_USER'),
          env('RABBITMQ_PASSWORD'),  
        );

        $channel = $connetion->channel();

        $channel->exchange_declare(
            'orders.exchange',
            'direct',
            false,
            true,
            false,
            false,
        );

        $channel->queue_declare(
            'orders.queue',
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

        $message = new AMQPMessage( 
            json_encode($data), [
                'content_type' => 'application/json',
                'delivery_mode' => 2
            ]
        );

        $channel->basic_publish(
            $message,
            'orders.exchange',
            'order.created'
        );

        Log::info('Mensagem enviada com sucesso');

        $channel->close();
        $connetion->close();
    }
}