<?php

namespace App\Models\Captacao;

use App\Models\Cliente;
use App\Models\Fruta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteFrutaVinculo extends Model
{
    protected $table = 'cliente_fruta_vinculos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_cliente',
        'id_fruta',
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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function fruta(): BelongsTo
    {
        return $this->belongsTo(Fruta::class, 'id_fruta');
    }
}
