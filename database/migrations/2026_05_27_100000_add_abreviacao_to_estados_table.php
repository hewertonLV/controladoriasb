<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estados', function (Blueprint $table): void {
            $table->string('abreviacao', 2)->nullable()->after('nome');
            $table->unique('abreviacao');
        });

        foreach ($this->estados() as $estado) {
            DB::table('estados')
                ->where('id', $estado['id'])
                ->orWhere('nome', $estado['nome'])
                ->update(['abreviacao' => $estado['abreviacao']]);
        }

        if (DB::table('estados')->whereNull('abreviacao')->exists()) {
            throw new RuntimeException('Existem estados sem abreviação. Preencha abreviacao antes de concluir a migration.');
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE estados MODIFY abreviacao VARCHAR(2) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE estados ALTER COLUMN abreviacao SET NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('estados', function (Blueprint $table): void {
            $table->dropUnique(['abreviacao']);
            $table->dropColumn('abreviacao');
        });
    }

    /**
     * @return list<array{id:int,nome:string,abreviacao:string}>
     */
    private function estados(): array
    {
        return [
            ['id' => 1, 'nome' => 'CEARA', 'abreviacao' => 'CE'],
            ['id' => 2, 'nome' => 'PERNAMBUCO', 'abreviacao' => 'PE'],
            ['id' => 3, 'nome' => 'ALAGOAS', 'abreviacao' => 'AL'],
            ['id' => 4, 'nome' => 'RIO GRANDE DO SUL', 'abreviacao' => 'RS'],
            ['id' => 5, 'nome' => 'BAHIA', 'abreviacao' => 'BA'],
            ['id' => 6, 'nome' => 'RIO GRANDE DO NORTE', 'abreviacao' => 'RN'],
            ['id' => 7, 'nome' => 'SERGIPE', 'abreviacao' => 'SE'],
            ['id' => 8, 'nome' => 'PARAIBA', 'abreviacao' => 'PB'],
            ['id' => 9, 'nome' => 'MINAS GERAIS', 'abreviacao' => 'MG'],
            ['id' => 10, 'nome' => 'PARANA', 'abreviacao' => 'PR'],
            ['id' => 11, 'nome' => 'SAO PAULO', 'abreviacao' => 'SP'],
            ['id' => 12, 'nome' => 'SANTA CATARINA', 'abreviacao' => 'SC'],
            ['id' => 13, 'nome' => 'PIAUI', 'abreviacao' => 'PI'],
        ];
    }
};
