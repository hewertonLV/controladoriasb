<?php

namespace App\Models\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaptacaoLote extends Model
{
    use SoftDeletes;

    protected $table = 'captacao_lotes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'data_referencia',
        'id_captacao_carteira',
        'id_unidade_negocio_faturamento',
        'id_unidade_negocio_galpao',
        'id_unidade_negocio_hub_origem',
        'arquivo_nf_transferencia_path',
        'arquivo_nf_transferencia_nome',
        'nf_transferencia_enviada_em',
        'nf_transferencia_user_id',
        'arquivo_nf_venda_path',
        'arquivo_nf_venda_nome',
        'nf_venda_enviada_em',
        'nf_venda_user_id',
        'tipo',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_referencia' => 'date',
            'tipo' => CaptacaoLoteTipo::class,
            'status' => CaptacaoLoteStatus::class,
            'nf_transferencia_enviada_em' => 'datetime',
            'nf_venda_enviada_em' => 'datetime',
        ];
    }

    public function carteira(): BelongsTo
    {
        return $this->belongsTo(CaptacaoCarteira::class, 'id_captacao_carteira');
    }

    public function unidadeFaturamento(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_faturamento');
    }

    public function unidadeGalpao(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_galpao');
    }

    public function unidadeHubOrigem(): BelongsTo
    {
        return $this->belongsTo(UnidadeNegocio::class, 'id_unidade_negocio_hub_origem');
    }

    public function usuarioNfTransferencia(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'nf_transferencia_user_id');
    }

    public function possuiNfTransferencia(): bool
    {
        return $this->arquivo_nf_transferencia_path !== null
            && $this->arquivo_nf_transferencia_path !== '';
    }

    public function usuarioNfVenda(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'nf_venda_user_id');
    }

    public function possuiNfVenda(): bool
    {
        return $this->arquivo_nf_venda_path !== null
            && $this->arquivo_nf_venda_path !== '';
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'id_captacao_lote')->orderBy('id');
    }

    public function freteLinhas(): HasMany
    {
        return $this->hasMany(CaptacaoLoteFreteLinha::class, 'id_captacao_lote');
    }

    public function ciganExports(): HasMany
    {
        return $this->hasMany(CaptacaoLoteCiganExport::class, 'id_captacao_lote');
    }

    public function manualLinhas(): HasMany
    {
        return $this->hasMany(CaptacaoRomaneioManualLinha::class, 'id_captacao_lote');
    }

    public function movimentacoesVinculo(): HasMany
    {
        return $this->hasMany(CaptacaoLoteMovimentacao::class, 'id_captacao_lote');
    }
}
