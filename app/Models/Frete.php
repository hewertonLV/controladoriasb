<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property string $valor
 * @property int $id_veiculo
 * @property string|null $descricao
 * @property string $status_situacao
 * @property string $valor_fruta_kg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Frete extends Model
{
    use HasFactory;

    protected $table = 'fretes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'valor',
        'id_veiculo',
        'descricao',
        'status_situacao',
        'valor_fruta_kg',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_veiculo' => 'integer',
            'valor' => 'decimal:2',
            'valor_fruta_kg' => 'decimal:2',
        ];
    }

    protected function setNomeAttribute(mixed $value): void
    {
        $this->attributes['nome'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setValorAttribute(mixed $value): void
    {
        $this->attributes['valor'] = TextoCadastro::normalizarDecimalNaoNegativo($value);
    }

    protected function setValorFrutaKgAttribute(mixed $value): void
    {
        $this->attributes['valor_fruta_kg'] = TextoCadastro::normalizarDecimalNaoNegativo($value);
    }

    protected function setDescricaoAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['descricao'] = null;

            return;
        }

        $trimmed = trim((string) $value);
        $this->attributes['descricao'] = $trimmed === '' ? null : $trimmed;
    }

    protected function setStatusSituacaoAttribute(mixed $value): void
    {
        $this->attributes['status_situacao'] = mb_strtoupper(trim((string) ($value ?: 'ABERTA')), 'UTF-8');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'id_veiculo');
    }

    /**
     * @return HasMany<FreteHistorico, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(FreteHistorico::class, 'frete_id')->latest('created_at');
    }

    /**
     * @return HasMany<Movimentacao, $this>
     */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'id_frete');
    }
}
