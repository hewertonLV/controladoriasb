<?php

use App\Enums\FrutaIcmsOperacao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fruta_icms_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fruta_id')->constrained('frutas')->cascadeOnDelete();
            $table->foreignId('id_estado')->constrained('estados')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('origem', 30)->default('MANUAL');

            $table->decimal('entrada_nacional', 15, 2)->default(0);
            $table->string('um_icms_nacional', 10)->default('KG');
            $table->decimal('entrada_externo', 15, 2)->default(0);
            $table->string('um_icms_externo', 10)->default('KG');
            $table->decimal('saida_importada', 15, 2)->default(0);
            $table->string('um_icms_venda_importada', 10)->default('KG');
            $table->decimal('saida_nacional', 15, 2)->default(0);
            $table->string('um_icms_venda_nacional', 10)->default('KG');

            $table->boolean('status_position')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['fruta_id', 'id_estado', 'created_at'], 'fruta_icms_hist_lookup');
            $table->index(['fruta_id', 'id_estado', 'status_position'], 'fruta_icms_hist_vigente');
        });

        $this->backfillFromFrutaIcmsAtual();
    }

    public function down(): void
    {
        Schema::dropIfExists('fruta_icms_historicos');
    }

    private function backfillFromFrutaIcmsAtual(): void
    {
        if (! Schema::hasTable('fruta_icms')) {
            return;
        }

        $porEstado = DB::table('fruta_icms')
            ->select('fruta_id', 'id_estado')
            ->distinct()
            ->get();

        foreach ($porEstado as $row) {
            $entrada = DB::table('fruta_icms')
                ->where('fruta_id', $row->fruta_id)
                ->where('id_estado', $row->id_estado)
                ->where('operacao', FrutaIcmsOperacao::ENTRADA->value)
                ->first();

            $saida = DB::table('fruta_icms')
                ->where('fruta_id', $row->fruta_id)
                ->where('id_estado', $row->id_estado)
                ->where('operacao', FrutaIcmsOperacao::SAIDA->value)
                ->first();

            if ($entrada === null && $saida === null) {
                continue;
            }

            DB::table('fruta_icms_historicos')->insert([
                'fruta_id' => $row->fruta_id,
                'id_estado' => $row->id_estado,
                'user_id' => null,
                'origem' => 'BACKFILL',
                'entrada_nacional' => $entrada->icms_nacional ?? 0,
                'um_icms_nacional' => $entrada->um_icms_nacional ?? 'KG',
                'entrada_externo' => $entrada->icms_externo ?? 0,
                'um_icms_externo' => $entrada->um_icms_externo ?? 'KG',
                'saida_importada' => $saida->icms_venda_importada ?? 0,
                'um_icms_venda_importada' => $saida->um_icms_venda_importada ?? 'KG',
                'saida_nacional' => $saida->icms_venda_nacional ?? 0,
                'um_icms_venda_nacional' => $saida->um_icms_venda_nacional ?? 'KG',
                'status_position' => true,
                'created_at' => now(),
            ]);
        }
    }
};
