<?php

use App\Http\Controllers\PedidosController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pedidos', [PedidosController::class, 'index']);
Route::post('/pedidos/create', [PedidosController::class, 'store']);


use Illuminate\Support\Facades\Redis;

Route::get('/test-redis', function () {
    Redis::set('teste', 'ok');
    return Redis::get('teste');
});
