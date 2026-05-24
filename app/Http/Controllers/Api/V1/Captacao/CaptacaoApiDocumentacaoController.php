<?php

namespace App\Http\Controllers\Api\V1\Captacao;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Contrato resumido para integração — ver ADR-0081 e docs/api/captacao-v1.md.
 */
class CaptacaoApiDocumentacaoController extends Controller
{
    public function contrato(): JsonResponse
    {
        return response()->json([
            'adr' => 'ADR-0081',
            'base_path' => '/api/v1/captacao',
            'auth_futuro' => 'Bearer Sanctum',
            'endpoints_implementados' => [
                'POST /lotes/{lote}/pedidos' => [
                    'body' => [
                        'id_cliente' => 'int',
                        'id_captacao_rota' => 'int|null',
                        'data_entrega' => 'date|null',
                        'itens' => [['id_fruta', 'quantidade', 'preco_venda?', 'id_unidade_origem_fisica?']],
                    ],
                ],
                'GET /lotes/{lote}/pedidos' => [],
            ],
            'endpoints_planejados' => [
                'POST /auth/login',
                'POST /lotes/abrir',
                'GET /lotes/{id}',
                'PATCH /lotes/{lote}/celula',
                'GET /clientes',
                'GET /clientes/{id}/frutas',
                'GET /rotas',
            ],
        ]);
    }
}
