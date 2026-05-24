<?php

namespace App\Models\Captacao;

use App\Models\UnidadeNegocio;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaptacaoRota extends Model
{
    use SoftDeletes;

    protected $table = 'captacao_rotas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_unidade_negocio_galpao',
        'nome',
        'id_veiculo',
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

    public function unidadeGalpao(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_galpao');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'id_veiculo');
    }
}
