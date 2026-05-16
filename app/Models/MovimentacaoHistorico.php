<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $movimentacao_cadeia_raiz_id
 * @property int $movimentacao_antes_id
 * @property int $movimentacao_depois_id
 * @property int|null $user_id
 * @property string $origem
 * @property string $acao
 * @property string|null $motivo
 * @property array<string, mixed>|null $dados_antes
 * @property array<string, mixed>|null $dados_depois
 * @property Carbon $created_at
 */
class MovimentacaoHistorico extends Model
{
    public const ACAO_SUBSTITUICAO_VERSAO = 'SUBSTITUICAO_VERSAO';

    public const ACAO_CANCELAMENTO_ADMIN = 'CANCELAMENTO_ADMIN';

    public const ACAO_REGISTRO_DOACAO = 'REGISTRO_DOACAO';

    public const ACAO_REGISTRO_DESCARTE = 'REGISTRO_DESCARTE';

    public const ORIGEM_VERSIONAMENTO = 'versionamento';

    public const ORIGEM_CANCELAMENTO_ADMIN = 'cancelamento_admin';

    public const ORIGEM_DOACAO = 'doacao';

    public const ORIGEM_DESCARTE = 'descarte';

    public $timestamps = false;

    protected $table = 'movimentacao_historicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'movimentacao_cadeia_raiz_id',
        'movimentacao_antes_id',
        'movimentacao_depois_id',
        'user_id',
        'origem',
        'acao',
        'motivo',
        'dados_antes',
        'dados_depois',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dados_antes' => 'array',
            'dados_depois' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
