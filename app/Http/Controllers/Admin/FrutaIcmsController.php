<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Frutas\StoreFrutaIcmsRequest;
use App\Http\Requests\Admin\Frutas\UpdateFrutaIcmsRequest;
use App\Models\Estado;
use App\Models\Fruta;
use App\Models\FrutaIcmsHistorico;
use App\Queries\FrutaIcmsQuery;
use App\Services\Frutas\FrutaIcmsAliquotaResolver;
use App\Services\Frutas\FrutaIcmsSyncService;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FrutaIcmsController extends Controller
{
    public function __construct(
        private readonly FrutaIcmsQuery $icmsQuery,
        private readonly FrutaIcmsSyncService $icmsSync,
        private readonly FrutaIcmsAliquotaResolver $resolver,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->icmsQuery->filtrosFromRequest($request);
        $registros = $this->icmsQuery->listagemAgrupada($filtros);

        $linhas = [];
        foreach ($registros as $item) {
            $fruta = $item->fruta;
            $estado = $item->estado;
            if ($fruta === null || $estado === null) {
                continue;
            }
            $linhas[] = [
                'fruta' => $fruta,
                'estado' => $estado,
                'valores' => $this->resolver->mapaParaFormulario($fruta, $estado->id),
            ];
        }

        return view('admin.frutas.icms.index', [
            'linhas' => $linhas,
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('admin.frutas.icms.create', [
            'frutas' => Fruta::query()->orderBy('nome')->get(['id', 'id_cigam', 'nome']),
            'estados' => Estado::query()->orderBy('nome')->get(),
            'icmsLinha' => FrutaIcmsLinhaFormulario::vazia(),
        ]);
    }

    public function store(StoreFrutaIcmsRequest $request): RedirectResponse
    {
        $frutaId = (int) $request->validated('fruta_id');
        $idEstado = (int) $request->validated('id_estado');

        DB::transaction(function () use ($request, $frutaId, $idEstado): void {
            $fruta = Fruta::query()->findOrFail($frutaId);
            $this->icmsSync->syncEstado(
                $fruta,
                $idEstado,
                $request->dadosIcms(),
                $request->user(),
                FrutaIcmsHistorico::ORIGEM_MANUAL,
            );
        });

        $fruta = Fruta::query()->findOrFail($frutaId);
        $estado = Estado::query()->findOrFail($idEstado);

        return redirect()
            ->route('admin.frutas.icms.index')
            ->with('success', "ICMS de {$fruta->nome} ({$estado->abreviacao}) cadastrado com sucesso.");
    }

    public function edit(Fruta $fruta, Estado $estado): View
    {
        $fruta->load('icmsAliquotas.estado');
        $linha = $this->resolver->mapaParaFormulario($fruta, $estado->id);

        $historicos = FrutaIcmsHistorico::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $estado->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.frutas.icms.edit', [
            'fruta' => $fruta,
            'estado' => $estado,
            'icmsLinha' => $linha,
            'historicos' => $historicos,
        ]);
    }

    public function update(UpdateFrutaIcmsRequest $request, Fruta $fruta, Estado $estado): RedirectResponse
    {
        DB::transaction(function () use ($request, $fruta, $estado): void {
            $this->icmsSync->syncEstado(
                $fruta,
                $estado->id,
                $request->dadosIcms(),
                $request->user(),
                FrutaIcmsHistorico::ORIGEM_MANUAL,
            );
        });

        return redirect()
            ->route('admin.frutas.icms.index')
            ->with('success', "ICMS de {$fruta->nome} ({$estado->abreviacao}) atualizado com sucesso.");
    }
}
