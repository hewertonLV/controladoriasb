<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrupoContratoCliente extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'grupo_contrato_clientes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_contrato_id',
        'cliente_id',
        'competencia_inicio',
        'competencia_fim',
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
            'cliente_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function grupoContrato(): BelongsTo
    {
        return $this->belongsTo(GrupoContrato::class, 'grupo_contrato_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
