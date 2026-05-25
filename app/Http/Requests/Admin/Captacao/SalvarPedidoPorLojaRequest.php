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
     *     itens: list<array{id_fruta: int, quantidade: string, preco_venda?: string|null}>,
     * }
     */
    public function pedidoPayload(int $idCliente): array
    {
        $itens = [];
        foreach ((array) $this->input('itens', []) as $item) {
            $qty = $item['quantidade'] ?? '';
            if ($qty === '' || $qty === null) {
                continue;
            }
            if ((float) $qty <= 0 && empty($item['preco_venda'])) {
                continue;
            }

            $linha = [
                'id_fruta' => (int) $item['id_fruta'],
                'quantidade' => number_format((float) $qty, 3, '.', ''),
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
