<?php

use App\Enums\EstadoIcmsCobraEm;
use App\Enums\FrutaIcmsEscopoVenda;
use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaIcmsTipoValor;
use App\Enums\FrutaProcedencia;
use App\Enums\FrutaUmIcms;
use App\Models\Estado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frutas', function (Blueprint $table): void {
            $table->string('procedencia', 20)->default(FrutaProcedencia::NACIONAL->value)->after('kg_por_unidade_medicao');
        });

        Schema::table('estados', function (Blueprint $table): void {
            $table->string('icms_cobra_em', 10)->default(EstadoIcmsCobraEm::NENHUM->value)->after('descricao');
        });

        $this->preencherIcmsCobraEmEstados();

        Schema::create('fruta_icms_aliquotas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fruta_id')->constrained('frutas')->cascadeOnDelete();
            $table->foreignId('id_estado')->constrained('estados')->cascadeOnDelete();
            $table->string('operacao', 10);
            $table->string('procedencia', 20);
            $table->string('escopo_venda', 20)->nullable();
            $table->string('tipo_valor', 20);
            $table->decimal('valor', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(
                ['fruta_id', 'id_estado', 'operacao', 'procedencia', 'escopo_venda'],
                'fruta_icms_aliq_uk',
            );
            $table->index(['id_estado', 'operacao']);
        });

        if (Schema::hasTable('fruta_icms')) {
            $this->migrarFrutaIcmsLegado();
            Schema::drop('fruta_icms');
        }

        if (Schema::hasTable('fruta_icms_historicos')) {
            $this->migrarHistoricoParaJson();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fruta_icms_historicos') && Schema::hasColumn('fruta_icms_historicos', 'aliquotas')) {
            Schema::table('fruta_icms_historicos', function (Blueprint $table): void {
                $table->dropColumn('aliquotas');
            });
        }

        Schema::dropIfExists('fruta_icms_aliquotas');

        Schema::table('estados', function (Blueprint $table): void {
            $table->dropColumn('icms_cobra_em');
        });

        Schema::table('frutas', function (Blueprint $table): void {
            $table->dropColumn('procedencia');
        });

        if (! Schema::hasTable('fruta_icms')) {
            Schema::create('fruta_icms', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('fruta_id')->constrained('frutas')->cascadeOnDelete();
                $table->foreignId('id_estado')->constrained('estados')->cascadeOnDelete();
                $table->string('operacao', 10);
                $table->decimal('icms_externo', 15, 2)->default(0);
                $table->decimal('icms_nacional', 15, 2)->default(0);
                $table->string('um_icms_nacional', 10)->default('KG');
                $table->string('um_icms_externo', 10)->default('KG');
                $table->decimal('icms_venda_importada', 15, 2)->default(0);
                $table->string('um_icms_venda_importada', 10)->default('KG');
                $table->decimal('icms_venda_nacional', 15, 2)->default(0);
                $table->string('um_icms_venda_nacional', 10)->default('KG');
                $table->timestamps();
                $table->unique(['fruta_id', 'id_estado', 'operacao'], 'fruta_icms_uk');
            });
        }
    }

    private function preencherIcmsCobraEmEstados(): void
    {
        $mapa = [
            Estado::ID_CEARA => EstadoIcmsCobraEm::ENTRADA->value,
            Estado::ID_PERNAMBUCO => EstadoIcmsCobraEm::SAIDA->value,
            Estado::ID_ALAGOAS => EstadoIcmsCobraEm::NENHUM->value,
        ];

        foreach ($mapa as $id => $cobraEm) {
            DB::table('estados')->where('id', $id)->update(['icms_cobra_em' => $cobraEm]);
        }
    }

    private function migrarFrutaIcmsLegado(): void
    {
        $frutasKg = DB::table('frutas')->pluck('kg_por_unidade_medicao', 'id');

        DB::table('fruta_icms')->orderBy('id')->chunkById(200, function ($rows) use ($frutasKg): void {
            foreach ($rows as $row) {
                $kgPorUm = max(0.0001, (float) ($frutasKg[$row->fruta_id] ?? 1));

                if ($row->operacao === FrutaIcmsOperacao::ENTRADA->value) {
                    $this->inserirAliquota(
                        (int) $row->fruta_id,
                        (int) $row->id_estado,
                        FrutaIcmsOperacao::ENTRADA->value,
                        FrutaProcedencia::NACIONAL->value,
                        null,
                        FrutaIcmsTipoValor::VALOR_POR_KG->value,
                        $this->valorEntradaParaKg((float) $row->icms_nacional, (string) $row->um_icms_nacional, $kgPorUm),
                    );
                    $this->inserirAliquota(
                        (int) $row->fruta_id,
                        (int) $row->id_estado,
                        FrutaIcmsOperacao::ENTRADA->value,
                        FrutaProcedencia::INTERNACIONAL->value,
                        null,
                        FrutaIcmsTipoValor::VALOR_POR_KG->value,
                        $this->valorEntradaParaKg((float) $row->icms_externo, (string) $row->um_icms_externo, $kgPorUm),
                    );

                    continue;
                }

                if ($row->operacao !== FrutaIcmsOperacao::SAIDA->value) {
                    continue;
                }

                $tipoNac = $this->tipoValorSaida((string) $row->um_icms_venda_nacional);
                $tipoInt = $this->tipoValorSaida((string) $row->um_icms_venda_importada);

                $this->inserirAliquota(
                    (int) $row->fruta_id,
                    (int) $row->id_estado,
                    FrutaIcmsOperacao::SAIDA->value,
                    FrutaProcedencia::NACIONAL->value,
                    FrutaIcmsEscopoVenda::DENTRO_ESTADO->value,
                    $tipoNac,
                    (float) $row->icms_venda_nacional,
                );
                $this->inserirAliquota(
                    (int) $row->fruta_id,
                    (int) $row->id_estado,
                    FrutaIcmsOperacao::SAIDA->value,
                    FrutaProcedencia::NACIONAL->value,
                    FrutaIcmsEscopoVenda::FORA_ESTADO->value,
                    $tipoInt,
                    (float) $row->icms_venda_importada,
                );
                $this->inserirAliquota(
                    (int) $row->fruta_id,
                    (int) $row->id_estado,
                    FrutaIcmsOperacao::SAIDA->value,
                    FrutaProcedencia::INTERNACIONAL->value,
                    FrutaIcmsEscopoVenda::DENTRO_ESTADO->value,
                    $tipoNac,
                    (float) $row->icms_venda_nacional,
                );
                $this->inserirAliquota(
                    (int) $row->fruta_id,
                    (int) $row->id_estado,
                    FrutaIcmsOperacao::SAIDA->value,
                    FrutaProcedencia::INTERNACIONAL->value,
                    FrutaIcmsEscopoVenda::FORA_ESTADO->value,
                    $tipoInt,
                    (float) $row->icms_venda_importada,
                );
            }
        });
    }

    private function migrarHistoricoParaJson(): void
    {
        Schema::table('fruta_icms_historicos', function (Blueprint $table): void {
            $table->json('aliquotas')->nullable()->after('origem');
        });

        DB::table('fruta_icms_historicos')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $aliquotas = [
                    'entrada_nacional_kg' => number_format((float) ($row->entrada_nacional ?? 0), 2, '.', ''),
                    'entrada_internacional_kg' => number_format((float) ($row->entrada_externo ?? 0), 2, '.', ''),
                    'saida_nacional_dentro_pct' => number_format((float) ($row->saida_nacional ?? 0), 2, '.', ''),
                    'saida_nacional_fora_pct' => number_format((float) ($row->saida_importada ?? 0), 2, '.', ''),
                    'saida_internacional_dentro_pct' => number_format((float) ($row->saida_nacional ?? 0), 2, '.', ''),
                    'saida_internacional_fora_pct' => number_format((float) ($row->saida_importada ?? 0), 2, '.', ''),
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

    private function inserirAliquota(
        int $frutaId,
        int $idEstado,
        string $operacao,
        string $procedencia,
        ?string $escopoVenda,
        string $tipoValor,
        float $valor,
    ): void {
        if ($valor <= 0 && $operacao === FrutaIcmsOperacao::SAIDA->value) {
            return;
        }

        if ($valor <= 0 && $operacao === FrutaIcmsOperacao::ENTRADA->value) {
            DB::table('fruta_icms_aliquotas')
                ->where('fruta_id', $frutaId)
                ->where('id_estado', $idEstado)
                ->where('operacao', $operacao)
                ->where('procedencia', $procedencia)
                ->whereNull('escopo_venda')
                ->delete();

            return;
        }

        DB::table('fruta_icms_aliquotas')->updateOrInsert(
            [
                'fruta_id' => $frutaId,
                'id_estado' => $idEstado,
                'operacao' => $operacao,
                'procedencia' => $procedencia,
                'escopo_venda' => $escopoVenda,
            ],
            [
                'tipo_valor' => $tipoValor,
                'valor' => round($valor, 4),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function valorEntradaParaKg(float $valor, string $um, float $kgPorUm): float
    {
        $umNorm = mb_strtoupper(trim($um), 'UTF-8');

        if ($umNorm === FrutaUmIcms::KG->value) {
            return $valor;
        }

        if ($umNorm === FrutaUmIcms::UM->value && $kgPorUm > 0) {
            return round($valor / $kgPorUm, 4);
        }

        return $valor;
    }

    private function tipoValorSaida(string $um): string
    {
        return mb_strtoupper(trim($um), 'UTF-8') === FrutaUmIcms::PCT->value
            ? FrutaIcmsTipoValor::PERCENTUAL->value
            : FrutaIcmsTipoValor::VALOR_POR_KG->value;
    }
};
