<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;

class SalvarPedidoPorLojaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'itens' => ['nullable', 'array'],
            'itens.*.id_fruta' => ['required', 'integer', 'exists:frutas,id'],
            'itens.*.quantidade' => ['nullable', 'numeric', 'min:0'],
            'itens.*.preco_venda' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array{
     *     id_cliente: int,
     *     itens: list<array{id_fruta: int, quantidade?: string, preco_venda?: string|null, remover?: bool}>,
     * }
     */
    public function pedidoPayload(int $idCliente): array
    {
        $itens = [];
        foreach ((array) $this->input('itens', []) as $item) {
            $idFruta = (int) $item['id_fruta'];
            $qtyRaw = $item['quantidade'] ?? '';
            if (is_string($qtyRaw)) {
                $qtyRaw = trim($qtyRaw);
            }

            if ($qtyRaw === '' || $qtyRaw === null || (float) $qtyRaw <= 0) {
                $itens[] = [
                    'id_fruta' => $idFruta,
                    'remover' => true,
                ];

                continue;
            }

            $linha = [
                'id_fruta' => $idFruta,
                'quantidade' => number_format((float) $qtyRaw, 3, '.', ''),
            ];

            if (isset($item['preco_venda']) && $item['preco_venda'] !== '') {
                $linha['preco_venda'] = number_format((float) $item['preco_venda'], 4, '.', '');
            }

            $itens[] = $linha;
        }

        return [
            'id_cliente' => $idCliente,
            'itens' => $itens,
        ];
    }
}
