<?php

use App\Http\Controllers\Api\V1\Captacao\CaptacaoApiDocumentacaoController;
use App\Http\Controllers\Api\V1\Captacao\PedidoApiController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/captacao/contrato', [CaptacaoApiDocumentacaoController::class, 'contrato'])
    ->name('api.v1.captacao.contrato');

Route::middleware(['auth', 'verified', 'user.active', 'password.changed'])
    ->prefix('v1/captacao')
    ->name('api.v1.captacao.')
    ->group(function () {
        Route::post('/lotes/{lote}/pedidos', [PedidoApiController::class, 'store'])
            ->middleware('permission:captacao.pedido.editar')
            ->name('lotes.pedidos.store');

        Route::get('/lotes/{lote}/pedidos', [PedidoApiController::class, 'meusPedidos'])
            ->middleware('permission:captacao.pedido.editar')
            ->name('lotes.pedidos.index');
    });
