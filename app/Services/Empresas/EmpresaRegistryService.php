<?php

namespace App\Services\Empresas;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaHistorico;
use App\Models\Fornecedor;
use App\Models\UnidadeNegocio;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EmpresaRegistryService
{
    /**
     * @var list<class-string<Model>>
     */
    private const TIPOS_PERMITIDOS = [
        Cliente::class,
        Fornecedor::class,
        UnidadeNegocio::class,
    ];

    public function __construct(
        private readonly EmpresaAuditoriaService $auditoria,
    ) {}

    public function garantirRegistro(Model $entidade, ?User $user = null): Empresa
    {
        $class = $entidade::class;
        if (! in_array($class, self::TIPOS_PERMITIDOS, true)) {
            throw new InvalidArgumentException('Tipo não suportado para registro corporativo: '.$class);
        }

        return DB::transaction(function () use ($entidade, $class, $user): Empresa {
            $existente = Empresa::query()
                ->where('entidade_type', $class)
                ->where('entidade_id', $entidade->getKey())
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                return $existente;
            }

            $empresa = Empresa::query()->create([
                'entidade_type' => $class,
                'entidade_id' => $entidade->getKey(),
            ]);

            $this->auditoria->registrarCriacao(
                $empresa,
                $user,
                EmpresaHistorico::ORIGEM_SISTEMA,
            );

            return $empresa;
        });
    }

    public function removerRegistroSeExistir(Model $entidade): void
    {
        $class = $entidade::class;
        if (! in_array($class, self::TIPOS_PERMITIDOS, true)) {
            return;
        }

        Empresa::query()
            ->where('entidade_type', $class)
            ->where('entidade_id', $entidade->getKey())
            ->delete();
    }
}
