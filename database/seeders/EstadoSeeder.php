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
                        'id_cigam' => $estado['id_cigam'],
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
     * @return list<array{id:int,id_cigam:string,nome:string,abreviacao:string,descricao:string|null,created_at:mixed,updated_at:mixed}>
     */
    private function estados(mixed $now): array
    {
        $estados = [
            ['id' => 1, 'id_cigam' => '000001', 'nome' => 'CEARA', 'abreviacao' => 'CE', 'descricao' => 'PAGA ICMS NA ENTRADA DO ESTADO'],
            ['id' => 2, 'id_cigam' => '000002', 'nome' => 'PERNAMBUCO', 'abreviacao' => 'PE', 'descricao' => 'PAGA ICMS NA VENDA'],
            ['id' => 3, 'id_cigam' => '000003', 'nome' => 'ALAGOAS', 'abreviacao' => 'AL', 'descricao' => 'NAO PAGA ICMS'],
            ['id' => 4, 'id_cigam' => '000004', 'nome' => 'RIO GRANDE DO SUL', 'abreviacao' => 'RS', 'descricao' => null],
            ['id' => 5, 'id_cigam' => '000005', 'nome' => 'BAHIA', 'abreviacao' => 'BA', 'descricao' => null],
            ['id' => 6, 'id_cigam' => '000006', 'nome' => 'RIO GRANDE DO NORTE', 'abreviacao' => 'RN', 'descricao' => null],
            ['id' => 7, 'id_cigam' => '000007', 'nome' => 'SERGIPE', 'abreviacao' => 'SE', 'descricao' => null],
            ['id' => 8, 'id_cigam' => '000008', 'nome' => 'PARAIBA', 'abreviacao' => 'PB', 'descricao' => null],
            ['id' => 9, 'id_cigam' => '000009', 'nome' => 'MINAS GERAIS', 'abreviacao' => 'MG', 'descricao' => null],
            ['id' => 10, 'id_cigam' => '000010', 'nome' => 'PARANA', 'abreviacao' => 'PR', 'descricao' => null],
            ['id' => 11, 'id_cigam' => '000011', 'nome' => 'SAO PAULO', 'abreviacao' => 'SP', 'descricao' => null],
            ['id' => 12, 'id_cigam' => '000012', 'nome' => 'SANTA CATARINA', 'abreviacao' => 'SC', 'descricao' => null],
            ['id' => 13, 'id_cigam' => '000013', 'nome' => 'PIAUI', 'abreviacao' => 'PI', 'descricao' => null],
        ];

        $vistos = [];

        return array_values(array_filter(
            array_map(function (array $estado) use ($now): array {
                $estado['id_cigam'] ??= str_pad((string) $estado['id'], 6, '0', STR_PAD_LEFT);
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
