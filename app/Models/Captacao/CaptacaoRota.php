<?php

namespace App\Models\Captacao;

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
        'id_captacao_carteira',
        'nome',
        'nome_motorista',
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

    public function carteira(): BelongsTo
    {
        return $this->belongsTo(CaptacaoCarteira::class, 'id_captacao_carteira');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'id_veiculo');
    }
}
