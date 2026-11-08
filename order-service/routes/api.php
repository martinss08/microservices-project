<?php

use App\Http\Controllers\PedidosController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/pedidos', [PedidosController::class, 'index']);
Route::post('/pedidos', [PedidosController::class, 'store']);
