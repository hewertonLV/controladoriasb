<?php

use App\Enums\FrutaIcmsEscopoVenda;
use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaProcedencia;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fruta_icms_historicos')) {
            if (! Schema::hasColumn('fruta_icms_historicos', 'aliquotas')) {
                $this->migrarColunasLegadasParaJson();
            }

            return;
        }

        Schema::create('fruta_icms_historicos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fruta_id')->constrained('frutas')->cascadeOnDelete();
            $table->foreignId('id_estado')->constrained('estados')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('origem', 30)->default('MANUAL');
            $table->json('aliquotas');

            $table->boolean('status_position')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['fruta_id', 'id_estado', 'created_at'], 'fruta_icms_hist_lookup');
            $table->index(['fruta_id', 'id_estado', 'status_position'], 'fruta_icms_hist_vigente');
        });

        $this->backfillFromAliquotasAtuais();
    }

    public function down(): void
    {
        Schema::dropIfExists('fruta_icms_historicos');
    }

    private function migrarColunasLegadasParaJson(): void
    {
        Schema::table('fruta_icms_historicos', function (Blueprint $table): void {
            $table->json('aliquotas')->nullable()->after('origem');
        });

        DB::table('fruta_icms_historicos')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $aliquotas = [
                    FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => number_format((float) ($row->entrada_nacional ?? 0), 2, '.', ''),
                    FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => number_format((float) ($row->entrada_externo ?? 0), 2, '.', ''),
                    FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => number_format((float) ($row->saida_nacional ?? 0), 2, '.', ''),
                    FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => number_format((float) ($row->saida_importada ?? 0), 2, '.', ''),
                    FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => number_format((float) ($row->saida_nacional ?? 0), 2, '.', ''),
                    FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => number_format((float) ($row->saida_importada ?? 0), 2, '.', ''),
                ];

                DB::table('fruta_icms_historicos')->where('id', $row->id)->update([
                    'aliquotas' => json_encode($aliquotas, JSON_THROW_ON_ERROR),
                ]);
            }
        });

        Schema::table('fruta_icms_historicos', function (Blueprint $table): void {
            $table->dropColumn([
                'entrada_nacional',
                'um_icms_nacional',
                'entrada_externo',
                'um_icms_externo',
                'saida_importada',
                'um_icms_venda_importada',
                'saida_nacional',
                'um_icms_venda_nacional',
            ]);
        });
    }

    private function backfillFromAliquotasAtuais(): void
    {
        if (! Schema::hasTable('fruta_icms_aliquotas')) {
            return;
        }

        $porEstado = DB::table('fruta_icms_aliquotas')
            ->select('fruta_id', 'id_estado')
            ->distinct()
            ->get();

        foreach ($porEstado as $row) {
            $linha = FrutaIcmsLinhaFormulario::vazia();

            $aliquotas = DB::table('fruta_icms_aliquotas')
                ->where('fruta_id', $row->fruta_id)
                ->where('id_estado', $row->id_estado)
                ->get();

            foreach ($aliquotas as $aliq) {
                $chave = $this->chaveFormulario(
                    (string) $aliq->operacao,
                    (string) $aliq->procedencia,
                    $aliq->escopo_venda,
                );

                if ($chave !== null) {
                    $linha[$chave] = number_format((float) $aliq->valor, 2, '.', '');
                }
            }

            DB::table('fruta_icms_historicos')->insert([
                'fruta_id' => $row->fruta_id,
                'id_estado' => $row->id_estado,
                'user_id' => null,
                'origem' => 'BACKFILL',
                'aliquotas' => json_encode($linha, JSON_THROW_ON_ERROR),
                'status_position' => true,
                'created_at' => now(),
            ]);
        }
    }

    private function chaveFormulario(string $operacao, string $procedencia, ?string $escopo): ?string
    {
        if ($operacao === FrutaIcmsOperacao::ENTRADA->value && $procedencia === FrutaProcedencia::NACIONAL->value) {
            return FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG;
        }

        if ($operacao === FrutaIcmsOperacao::ENTRADA->value && $procedencia === FrutaProcedencia::INTERNACIONAL->value) {
            return FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG;
        }

        if ($operacao !== FrutaIcmsOperacao::SAIDA->value) {
            return null;
        }

        return match ([$procedencia, $escopo]) {
            [FrutaProcedencia::NACIONAL->value, FrutaIcmsEscopoVenda::DENTRO_ESTADO->value] => FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT,
            [FrutaProcedencia::NACIONAL->value, FrutaIcmsEscopoVenda::FORA_ESTADO->value] => FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT,
            [FrutaProcedencia::INTERNACIONAL->value, FrutaIcmsEscopoVenda::DENTRO_ESTADO->value] => FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT,
            [FrutaProcedencia::INTERNACIONAL->value, FrutaIcmsEscopoVenda::FORA_ESTADO->value] => FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT,
            default => null,
        };
    }
};
