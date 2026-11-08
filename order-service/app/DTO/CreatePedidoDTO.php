<?php

namespace App\DTO;

class CreatePedidoDTO
{
    public function __construct(
        public string $Idempotency_key,
        public string $name,
        public string $email,
        public int $quantity,
        public float $price,
    ) {}

    public static function fromArray(array $data)
    {
        return new self(
            Idempotency_key: (string) $data['Idempotency_key'],
            name:      (string) $data['name'],
            email:     (string) $data['email'],
            quantity:  (int)    $data['quantity'],
            price:     (float)  $data['price'],
        );
    }
}