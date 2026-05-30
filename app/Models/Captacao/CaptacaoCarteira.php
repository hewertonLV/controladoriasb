<?php

namespace App\Models\Captacao;

use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaptacaoCarteira extends Model
{
    use SoftDeletes;

    protected $table = 'captacao_carteiras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'id_unidade_negocio_faturamento',
        'id_unidade_negocio_galpao',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function unidadeFaturamento(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_faturamento');
    }

    public function unidadeGalpao(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_galpao');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'id_captacao_carteira');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(CaptacaoLote::class, 'id_captacao_carteira');
    }

    public function rotas(): HasMany
    {
        return $this->hasMany(CaptacaoRota::class, 'id_captacao_carteira');
    }

    public function importacoes(): HasMany
    {
        return $this->hasMany(CaptacaoCarteiraImportacao::class, 'id_captacao_carteira');
    }
}
