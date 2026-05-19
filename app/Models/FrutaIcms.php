<?php

namespace App\Models;

use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaUmIcms;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fruta_id
 * @property int $id_estado
 * @property FrutaIcmsOperacao|string $operacao
 * @property string $icms_externo
 * @property string $icms_nacional
 * @property string $um_icms_nacional
 * @property string $um_icms_externo
 * @property string $icms_venda_importada
 * @property string $um_icms_venda_importada
 * @property string $icms_venda_nacional
 * @property string $um_icms_venda_nacional
 */
class FrutaIcms extends Model
{
    use HasFactory;

    protected $table = 'fruta_icms';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fruta_id',
        'id_estado',
        'operacao',
        'icms_externo',
        'icms_nacional',
        'um_icms_nacional',
        'um_icms_externo',
        'icms_venda_importada',
        'um_icms_venda_importada',
        'icms_venda_nacional',
        'um_icms_venda_nacional',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operacao' => FrutaIcmsOperacao::class,
            'icms_externo' => 'decimal:2',
            'icms_nacional' => 'decimal:2',
            'icms_venda_importada' => 'decimal:2',
            'icms_venda_nacional' => 'decimal:2',
        ];
    }

    protected function setIcmsExternoAttribute(mixed $value): void
    {
        $this->attributes['icms_externo'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setIcmsNacionalAttribute(mixed $value): void
    {
        $this->attributes['icms_nacional'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setUmIcmsNacionalAttribute(mixed $value): void
    {
        $this->attributes['um_icms_nacional'] = $this->normalizarUm($value);
    }

    protected function setUmIcmsExternoAttribute(mixed $value): void
    {
        $this->attributes['um_icms_externo'] = $this->normalizarUm($value);
    }

    protected function setIcmsVendaImportadaAttribute(mixed $value): void
    {
        $this->attributes['icms_venda_importada'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setUmIcmsVendaImportadaAttribute(mixed $value): void
    {
        $this->attributes['um_icms_venda_importada'] = $this->normalizarUm($value);
    }

    protected function setIcmsVendaNacionalAttribute(mixed $value): void
    {
        $this->attributes['icms_venda_nacional'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setUmIcmsVendaNacionalAttribute(mixed $value): void
    {
        $this->attributes['um_icms_venda_nacional'] = $this->normalizarUm($value);
    }

    /**
     * @return BelongsTo<Fruta, $this>
     */
    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'fruta_id');
    }

    /**
     * @return BelongsTo<Estado, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'id_estado');
    }

    public function converterParaIcmsPorKg(string|float $kgPorUnidadeMedicao): string
    {
        if ($this->operacao !== FrutaIcmsOperacao::ENTRADA) {
            return '0.00';
        }

        $kgPorUm = max(0, (float) $kgPorUnidadeMedicao);
        $nacionalKg = $this->converterValorParaKg((float) $this->icms_nacional, (string) $this->um_icms_nacional, $kgPorUm);
        $externoKg = $this->converterValorParaKg((float) $this->icms_externo, (string) $this->um_icms_externo, $kgPorUm);

        return number_format(round(max(0, $nacionalKg + $externoKg), 2), 2, '.', '');
    }

    private function converterValorParaKg(float $valor, string $um, float $kgPorUm): float
    {
        $umNormalizada = mb_strtoupper(trim($um), 'UTF-8');

        return $umNormalizada === FrutaUmIcms::KG->value
            ? $valor
            : ($kgPorUm > 0 ? $valor / $kgPorUm : 0.0);
    }

    private function normalizarUm(mixed $value): string
    {
        return TextoCadastro::normalizarMaiusculas(
            $value === null ? FrutaUmIcms::KG->value : (string) $value,
        );
    }
}
