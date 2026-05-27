<?php

namespace App\Models\Captacao;

use App\Enums\ClienteCaptacaoAgendaTipo;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteCaptacaoAgenda extends Model
{
    protected $table = 'cliente_captacao_agenda';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_cliente',
        'dia_semana',
        'tipo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'tipo' => ClienteCaptacaoAgendaTipo::class,
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}
