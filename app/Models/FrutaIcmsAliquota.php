<?php

namespace App\Models;

use App\Enums\FrutaIcmsEscopoVenda;
use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaIcmsTipoValor;
use App\Enums\FrutaProcedencia;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fruta_id
 * @property int $id_estado
 * @property FrutaIcmsOperacao|string $operacao
 * @property FrutaProcedencia|string $procedencia
 * @property FrutaIcmsEscopoVenda|string|null $escopo_venda
 * @property FrutaIcmsTipoValor|string $tipo_valor
 * @property string $valor
 */
class FrutaIcmsAliquota extends Model
{
    use HasFactory;

    protected $table = 'fruta_icms_aliquotas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fruta_id',
        'id_estado',
        'operacao',
        'procedencia',
        'escopo_venda',
        'tipo_valor',
        'valor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operacao' => FrutaIcmsOperacao::class,
            'procedencia' => FrutaProcedencia::class,
            'escopo_venda' => FrutaIcmsEscopoVenda::class,
            'tipo_valor' => FrutaIcmsTipoValor::class,
            'valor' => 'decimal:4',
        ];
    }

    protected function setValorAttribute(mixed $value): void
    {
        $this->attributes['valor'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'fruta_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }

    public function valorPorKgEntrada(): float
    {
        if ($this->operacao !== FrutaIcmsOperacao::ENTRADA
            || $this->tipo_valor !== FrutaIcmsTipoValor::VALOR_POR_KG) {
            return 0.0;
        }

        return max(0, (float) $this->valor);
    }

    public function percentualSaida(): float
    {
        if ($this->operacao !== FrutaIcmsOperacao::SAIDA
            || $this->tipo_valor !== FrutaIcmsTipoValor::PERCENTUAL) {
            return 0.0;
        }

        return max(0, (float) $this->valor);
    }

    public function calcularIcmsSaidaSobreValor(float $valorVenda): string
    {
        if ($valorVenda <= 0) {
            return '0.00';
        }

        $percentual = $this->percentualSaida();

        return number_format(round($valorVenda * ($percentual / 100), 2), 2, '.', '');
    }
}
