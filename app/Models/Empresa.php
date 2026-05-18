<?php

namespace App\Models;

use App\Enums\TipoEmpresaRegistro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Registro corporativo (hub): aponta para Cliente, Fornecedor ou Unidade de Negócio
 * sem duplicar dados cadastrais.
 *
 * @property int $id
 * @property string $entidade_type
 * @property int $entidade_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $entidade
 */
class Empresa extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Constantes reutilizadas por validações legadas (requests/importador descontinuado no hub).
     */
    public const TIPO_PESSOA_FISICA = 'FISICA';

    public const TIPO_PESSOA_JURIDICA = 'JURIDICA';

    /**
     * @return list<string>
     */
    public static function tiposPessoa(): array
    {
        return [
            self::TIPO_PESSOA_FISICA,
            self::TIPO_PESSOA_JURIDICA,
        ];
    }

    protected $table = 'empresas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entidade_type',
        'entidade_id',
    ];

    public function entidade(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entidade_type', 'entidade_id');
    }

    /**
     * @return HasMany<EmpresaHistorico, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(EmpresaHistorico::class, 'empresa_id')->latest('created_at');
    }

    /**
     * @return HasMany<Movimentacao, $this>
     */
    public function movimentacoesComoOrigem(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'id_empresa_origem');
    }

    /**
     * @return HasMany<Movimentacao, $this>
     */
    public function movimentacoesComoDestino(): HasMany
    {
        return $this->hasMany(Movimentacao::class, 'id_empresa_destino');
    }

    public function tipoRegistro(): TipoEmpresaRegistro
    {
        return TipoEmpresaRegistro::fromClass((string) $this->entidade_type);
    }

    /**
     * @param  Builder<Empresa>  $query
     * @return Builder<Empresa>
     */
    public function scopeWithEntidadeParaListagem(Builder $query): Builder
    {
        return $query->with([
            'entidade' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([
                    Cliente::class => ['unidadeNegocio'],
                ]);
            },
        ]);
    }

    /**
     * Snapshot enxuto para auditoria e exportações.
     *
     * @return array<string, mixed>
     */
    public function dadosConsolidadosParaAuditoria(): array
    {
        $this->loadMissing(['entidade']);
        $entidade = $this->entidade;

        if ($entidade === null) {
            return [
                'tipo_registro' => '',
                'id_cigam' => '',
                'nome_exibicao' => '',
                'fantasia' => null,
                'documento' => '',
                'unidade_referencia' => '',
                'tipo_pessoa' => '',
                'status' => false,
            ];
        }

        if ($entidade instanceof Cliente) {
            $doc = (string) $entidade->cnpj_cpf;
            $un = $entidade->relationLoaded('unidadeNegocio')
                ? $entidade->unidadeNegocio
                : $entidade->unidadeNegocio()->first();

            return [
                'tipo_registro' => TipoEmpresaRegistro::CLIENTE->value,
                'id_cigam' => (string) $entidade->id_cigam,
                'nome_exibicao' => (string) $entidade->razao_social,
                'fantasia' => null,
                'documento' => $doc,
                'unidade_referencia' => $un !== null ? (string) $un->id_cigam : (string) $entidade->id_unidade_negocio,
                'tipo_pessoa' => $this->inferirTipoPessoaPorDocumento($doc),
                'status' => true,
            ];
        }

        if ($entidade instanceof Fornecedor) {
            $doc = (string) $entidade->cnpj_cpf;

            return [
                'tipo_registro' => TipoEmpresaRegistro::FORNECEDOR->value,
                'id_cigam' => (string) $entidade->id_cigam,
                'nome_exibicao' => (string) $entidade->razao_social,
                'fantasia' => $entidade->fantasia,
                'documento' => $doc,
                'unidade_referencia' => '',
                'tipo_pessoa' => $this->inferirTipoPessoaPorDocumento($doc),
                'status' => true,
            ];
        }

        if ($entidade instanceof UnidadeNegocio) {
            $doc = (string) $entidade->cpf_cnpj;

            return [
                'tipo_registro' => TipoEmpresaRegistro::UNIDADE_NEGOCIO->value,
                'id_cigam' => (string) $entidade->id_cigam,
                'nome_exibicao' => (string) $entidade->nome,
                'fantasia' => null,
                'documento' => $doc,
                'unidade_referencia' => (string) $entidade->id_cigam,
                'tipo_pessoa' => $this->inferirTipoPessoaPorDocumento($doc),
                'status' => (bool) $entidade->status,
            ];
        }

        return [
            'tipo_registro' => '',
            'id_cigam' => '',
            'nome_exibicao' => '',
            'fantasia' => null,
            'documento' => '',
            'unidade_referencia' => '',
            'tipo_pessoa' => '',
            'status' => false,
        ];
    }

    public function idCigamExibicao(): string
    {
        return (string) ($this->dadosConsolidadosParaAuditoria()['id_cigam'] ?? '');
    }

    public function nomeExibicao(): string
    {
        return (string) ($this->dadosConsolidadosParaAuditoria()['nome_exibicao'] ?? '');
    }

    public function fantasiaExibicao(): ?string
    {
        $f = $this->dadosConsolidadosParaAuditoria()['fantasia'] ?? null;

        return $f !== null && $f !== '' ? (string) $f : null;
    }

    public function documentoFormatado(): string
    {
        $this->loadMissing('entidade');
        $e = $this->entidade;
        if ($e instanceof Cliente) {
            return $e->cnpj_cpf_formatado;
        }
        if ($e instanceof Fornecedor) {
            return $e->cnpj_cpf_formatado;
        }
        if ($e instanceof UnidadeNegocio) {
            return $e->cpf_cnpj_formatado;
        }

        return '';
    }

    public function unidadeNegocioExibicao(): string
    {
        $v = (string) ($this->dadosConsolidadosParaAuditoria()['unidade_referencia'] ?? '');

        return $v !== '' ? $v : '—';
    }

    public function tipoPessoaExibicao(): string
    {
        return (string) ($this->dadosConsolidadosParaAuditoria()['tipo_pessoa'] ?? '');
    }

    public function statusExibicao(): bool
    {
        return (bool) ($this->dadosConsolidadosParaAuditoria()['status'] ?? false);
    }

    public function rotuloTipoRegistro(): string
    {
        try {
            return $this->tipoRegistro()->rotulo();
        } catch (\Throwable) {
            return '—';
        }
    }

    public function urlModuloEdicao(): ?string
    {
        $this->loadMissing('entidade');
        $e = $this->entidade;
        if ($e instanceof Cliente) {
            return route('admin.clientes.edit', $e);
        }
        if ($e instanceof Fornecedor) {
            return route('admin.fornecedores.edit', $e);
        }
        if ($e instanceof UnidadeNegocio) {
            return route('admin.unidades-negocio.edit', $e);
        }

        return null;
    }

    private function inferirTipoPessoaPorDocumento(string $digits): string
    {
        $len = strlen(preg_replace('/\D/', '', $digits) ?? '');

        return match ($len) {
            11 => 'FISICA',
            14 => 'JURIDICA',
            default => '',
        };
    }
}
