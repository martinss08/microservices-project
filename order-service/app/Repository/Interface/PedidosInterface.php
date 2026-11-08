<?php

namespace App\Repository\Interface;

interface PedidosInterface
{
    public function all();
    public function store($response);
}
