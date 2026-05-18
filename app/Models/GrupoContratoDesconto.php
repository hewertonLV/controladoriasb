<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrupoContratoDesconto extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'grupo_contrato_descontos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_contrato_id',
        'competencia',
        'valor',
        'valor_teto',
        'observacao',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grupo_contrato_id' => 'integer',
            'valor' => 'decimal:2',
            'valor_teto' => 'decimal:2',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function grupoContrato(): BelongsTo
    {
        return $this->belongsTo(GrupoContrato::class, 'grupo_contrato_id');
    }

    /**
     * @return HasMany<GrupoContratoDescontoHistorico, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(GrupoContratoDescontoHistorico::class, 'grupo_contrato_desconto_id');
    }
}
