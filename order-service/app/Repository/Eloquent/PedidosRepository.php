<?php

namespace App\Repository\Contracts;

use App\Http\Requests\PedidosRequest;
use App\Models\Pedidos;
use App\Repository\Interface\PedidosInterface;

class PedidosRepository implements PedidosInterface
{
    // protected $model;

    // public function __construct(Pedidos $model)
    // {
    //     $this->model = $model;
    // }

    public function all() 
    {
        return Pedidos::all();
    }

    // public function store(PedidosRequest $requeste)
    // {   
    //     $data = $requeste->validate();

    //     if( $data->isEmpty()) {

    //     }
    // }
}
