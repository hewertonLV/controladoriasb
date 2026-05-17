<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Registros fixos idempotentes de estados (ICMS).
 */
class EstadoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            foreach ($this->estados($now) as $estado) {
                DB::table('estados')->updateOrInsert(
                    ['id' => $estado['id']],
                    [
                        'nome' => $estado['nome'],
                        'abreviacao' => $estado['abreviacao'],
                        'descricao' => $estado['descricao'],
                        'created_at' => $estado['created_at'],
                        'updated_at' => $estado['updated_at'],
                    ],
                );
            }
        });
    }

    /**
     * @return list<array{id:int,nome:string,abreviacao:string,descricao:string|null,created_at:mixed,updated_at:mixed}>
     */
    private function estados(mixed $now): array
    {
        $estados = [
            ['id' => 1, 'nome' => 'CEARA', 'abreviacao' => 'CE', 'descricao' => 'PAGA ICMS NA ENTRADA DO ESTADO'],
            ['id' => 2, 'nome' => 'PERNAMBUCO', 'abreviacao' => 'PE', 'descricao' => 'PAGA ICMS NA VENDA'],
            ['id' => 3, 'nome' => 'ALAGOAS', 'abreviacao' => 'AL', 'descricao' => 'NAO PAGA ICMS'],
            ['id' => 4, 'nome' => 'RIO GRANDE DO SUL', 'abreviacao' => 'RS', 'descricao' => null],
            ['id' => 5, 'nome' => 'BAHIA', 'abreviacao' => 'BA', 'descricao' => null],
            ['id' => 6, 'nome' => 'RIO GRANDE DO NORTE', 'abreviacao' => 'RN', 'descricao' => null],
            ['id' => 7, 'nome' => 'SERGIPE', 'abreviacao' => 'SE', 'descricao' => null],
            ['id' => 8, 'nome' => 'PARAIBA', 'abreviacao' => 'PB', 'descricao' => null],
            ['id' => 9, 'nome' => 'MINAS GERAIS', 'abreviacao' => 'MG', 'descricao' => null],
            ['id' => 10, 'nome' => 'PARANA', 'abreviacao' => 'PR', 'descricao' => null],
            ['id' => 11, 'nome' => 'SAO PAULO', 'abreviacao' => 'SP', 'descricao' => null],
            ['id' => 12, 'nome' => 'SANTA CATARINA', 'abreviacao' => 'SC', 'descricao' => null],
            ['id' => 13, 'nome' => 'PIAUI', 'abreviacao' => 'PI', 'descricao' => null],
        ];

        $vistos = [];

        return array_values(array_filter(
            array_map(function (array $estado) use ($now): array {
                $estado['created_at'] = $now;
                $estado['updated_at'] = $now;

                return $estado;
            }, $estados),
            function (array $estado) use (&$vistos): bool {
                if (isset($vistos[$estado['abreviacao']])) {
                    return false;
                }

                $vistos[$estado['abreviacao']] = true;

                return true;
            },
        ));
    }
}
