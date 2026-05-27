<?php

namespace App\Models\Captacao;

use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptacaoRomaneioManualLinha extends Model
{
    protected $table = 'captacao_romaneio_manual_linhas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_captacao_lote',
        'id_fruta',
        'quantidade',
        'id_unidade_origem_fisica',
        'motivo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(CaptacaoLote::class, 'id_captacao_lote');
    }

    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'id_fruta');
    }

    public function unidadeOrigemFisica(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_origem_fisica');
    }
}
