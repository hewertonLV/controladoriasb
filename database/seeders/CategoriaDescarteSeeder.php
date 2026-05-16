<?php

namespace Database\Seeders;

use App\Models\CategoriaDescarte;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriaDescarteSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            CategoriaDescarte::ID_AVARIA => ['nome' => 'AVARIA', 'descricao' => 'Produto avariado antes da utilização.'],
            CategoriaDescarte::ID_VENCIMENTO => ['nome' => 'VENCIMENTO', 'descricao' => 'Produto descartado por vencimento.'],
            CategoriaDescarte::ID_FUNGOS => ['nome' => 'FUNGOS', 'descricao' => 'Produto com incidência de fungos.'],
            CategoriaDescarte::ID_QUALIDADE => ['nome' => 'QUALIDADE', 'descricao' => 'Produto fora do padrão de qualidade.'],
            CategoriaDescarte::ID_TRANSPORTE => ['nome' => 'TRANSPORTE', 'descricao' => 'Perda associada ao transporte.'],
            CategoriaDescarte::ID_QUEBRA => ['nome' => 'QUEBRA', 'descricao' => 'Quebra ou dano físico operacional.'],
            CategoriaDescarte::ID_CONTAMINACAO => ['nome' => 'CONTAMINACAO', 'descricao' => 'Produto contaminado.'],
            CategoriaDescarte::ID_MADURACAO_EXCESSIVA => ['nome' => 'MADURACAO_EXCESSIVA', 'descricao' => 'Produto com maturação excessiva.'],
            CategoriaDescarte::ID_PERDA_OPERACIONAL => ['nome' => 'PERDA_OPERACIONAL', 'descricao' => 'Perda operacional diversa.'],
            CategoriaDescarte::ID_OUTROS => ['nome' => 'OUTROS', 'descricao' => 'Outros motivos de descarte.'],
        ];

        DB::transaction(function () use ($categorias): void {
            foreach ($categorias as $id => $dados) {
                CategoriaDescarte::query()->updateOrCreate(
                    ['id' => $id],
                    [
                        'nome' => $dados['nome'],
                        'descricao' => $dados['descricao'],
                        'impacta_kpi_perda' => true,
                    ],
                );
            }
        });
    }
}
