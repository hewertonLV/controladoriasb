<?php

namespace App\Models;

use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property string|null $descricao
 * @property bool $ativo
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GrupoContrato extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'grupos_contrato';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'descricao',
        'ativo',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    protected function setNomeAttribute(mixed $value): void
    {
        $this->attributes['nome'] = TextoCadastro::normalizarMaiusculas(
            $value === null ? '' : (string) $value,
        );
    }

    protected function setDescricaoAttribute(mixed $value): void
    {
        $descricao = trim((string) ($value ?? ''));

        $this->attributes['descricao'] = $descricao === '' ? null : $descricao;
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function atualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<GrupoContratoCliente, $this>
     */
    public function membros(): HasMany
    {
        return $this->hasMany(GrupoContratoCliente::class, 'grupo_contrato_id');
    }

    /**
     * @return HasMany<GrupoContratoDesconto, $this>
     */
    public function descontos(): HasMany
    {
        return $this->hasMany(GrupoContratoDesconto::class, 'grupo_contrato_id');
    }

    /**
     * @return HasMany<GrupoContratoHistorico, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(GrupoContratoHistorico::class, 'grupo_contrato_id');
    }

    /**
     * @return HasManyThrough<Cliente, GrupoContratoCliente, $this>
     */
    public function clientes(): HasManyThrough
    {
        return $this->hasManyThrough(
            Cliente::class,
            GrupoContratoCliente::class,
            'grupo_contrato_id',
            'id',
            'id',
            'cliente_id',
        );
    }

    /**
     * @return Builder<Cliente>
     */
    public function clientesNaCompetencia(string $competencia): Builder
    {
        return Cliente::query()
            ->whereHas('gruposContrato', function (Builder $query) use ($competencia) {
                $query->where('grupos_contrato.id', $this->id)
                    ->where('grupo_contrato_clientes.competencia_inicio', '<=', $competencia)
                    ->where(function (Builder $q) use ($competencia) {
                        $q->whereNull('grupo_contrato_clientes.competencia_fim')
                            ->orWhere('grupo_contrato_clientes.competencia_fim', '>=', $competencia);
                    });
            });
    }
}
