<?php

namespace App\Service;

use App\DTO\CreatePedidoDTO;
use App\Infrastructure\RabbitMQ\PublishedRabbitMQ;
use App\Infrastructure\Redis\RedisService;
use App\Models\Pedidos;
use Illuminate\Support\Facades\Log;

class PedidoService
{
    protected $model;
    protected $redisService;
    protected $publishedRabbit;

    public function __construct(Pedidos $model, RedisService $redisService, PublishedRabbitMQ $publishedRabbit)
    {
        $this->model = $model;
        $this->redisService = $redisService;
        $this->publishedRabbit = $publishedRabbit;
    }

    public function getPedidos()
    {
        $data = $this->model->all();

        if ($data->isEmpty()) {
            return response()->json([
                'status'    => false,
                'message'   => 'Nenhum pedido cadastrado.',
                'data'      => null
            ]);
        }

        return response()->json([
            'status'    => true,
            'message'   =>  $data
        ], 200);
    }

    public function savePedidos(array $data)
    {
        $pedidoDTO = CreatePedidoDTO::fromArray($data);

        if(!$this->redisService->iniciarProcessamento($pedidoDTO->Idempotency_key)) {
            Log::info('Log do registro duplicado',[
                'key'   => $pedidoDTO->Idempotency_key
            ]);

            throw new \Exception('Requisição duplicada');
        }

        $pedido = $this->model->create([
            'name'      => $pedidoDTO->name,
            'email'     => $pedidoDTO->email,
            'quantity'  => $pedidoDTO->quantity,
            'price'     => $pedidoDTO->price
        ]);

        $response = [
            'status'    => true,
            'message'   => 'Pedido feito com sucesso ',
            'data'      => $pedido,
        ];

        $this->redisService->armazenarResultado("order:{$pedido->id}", $response);
        
        $this->publishedRabbit->publish([
            'event' => 'order.created',
            'data' => [
                'order_id' => $pedido->id,
                'name' => $pedido->name,
                'email' => $pedido->email,
                'quantity' => $pedido->quantity,
                'price' => $pedido->price,
            ],
            'idempotency_key' => $pedidoDTO->Idempotency_key,
            'timestamp' => now()->toDateTimeString()
        ], 'order.created');

        $this->publishedRabbit->publish([
            'event' => 'order.confirmed',
            'data'  => [
                'order_id' => $pedido->id,
                'name'     => $pedido->name,
                'email'    => $pedido->email,
                'quantity' => $pedido->quantity,
                'price'    => $pedido->price,
            ],
            'idempotency_key' => $pedidoDTO->Idempotency_key,
            'timestamp'       => now()->toDateTimeString()
        ], 'order.confirmed');


        return response()->json($response);
    }
}
