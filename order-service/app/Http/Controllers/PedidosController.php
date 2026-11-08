<?php

namespace App\Http\Controllers;

use App\Http\Requests\PedidosRequest;
use App\Models\Pedidos;
use App\Service\PedidoService;

class PedidosController extends Controller
{
    protected $model;
    protected $pedidoService;

    public function __construct(Pedidos $model, PedidoService $pedidoService) 
    {
        $this->model = $model;
        $this->pedidoService  = $pedidoService;
    }

    public function index()
    {
        $data = $this->model->all();

        $result = $this->pedidoService->getPedidos($data);

        return response()->json([
            'status' => true,
            'massage'   => $result,
        ]);
    }

    public function store(PedidosRequest $request) 
    {
        $data = $request->validated();

        $result = $this->pedidoService->savePedidos($data);
        
        return response()->json([
            'status' => true,
            'massage'   => $result,
        ]);
    }
}
