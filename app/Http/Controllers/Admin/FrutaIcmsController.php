<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FrutaIcmsOperacao;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Frutas\StoreFrutaIcmsRequest;
use App\Http\Requests\Admin\Frutas\UpdateFrutaIcmsRequest;
use App\Models\Estado;
use App\Models\Fruta;
use App\Models\FrutaIcms;
use App\Models\FrutaIcmsHistorico;
use App\Queries\FrutaIcmsQuery;
use App\Services\Frutas\FrutaIcmsSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FrutaIcmsController extends Controller
{
    public function __construct(
        private readonly FrutaIcmsQuery $icmsQuery,
        private readonly FrutaIcmsSyncService $icmsSync,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->icmsQuery->filtrosFromRequest($request);
        $registros = $this->icmsQuery
            ->aplicarFiltros($this->icmsQuery->listagemBase(), $filtros)
            ->get();

        $saidas = FrutaIcms::query()
            ->where('operacao', FrutaIcmsOperacao::SAIDA)
            ->whereIn('fruta_id', $registros->pluck('fruta_id')->unique()->all() ?: [0])
            ->get()
            ->keyBy(fn (FrutaIcms $s) => $s->fruta_id.'-'.$s->id_estado);

        return view('admin.frutas.icms.index', [
            'registros' => $registros,
            'saidas' => $saidas,
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('admin.frutas.icms.create', [
            'frutas' => Fruta::query()->orderBy('nome')->get(['id', 'id_cigam', 'nome']),
            'estados' => Estado::query()->orderBy('nome')->get(),
            'icmsLinha' => $this->icmsLinhaVazia(),
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
        $fruta->load('icms.estado');
        $linha = $this->icmsSync->mapaParaFormulario($fruta)[$estado->id] ?? $this->icmsLinhaVazia();

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

    /**
     * @return array<string, string>
     */
    private function icmsLinhaVazia(): array
    {
        return [
            'entrada_nacional' => '0.00',
            'entrada_um_nacional' => 'KG',
            'entrada_externo' => '0.00',
            'entrada_um_externo' => 'KG',
            'saida_importada' => '0.00',
            'saida_um_importada' => 'KG',
            'saida_nacional' => '0.00',
            'saida_um_nacional' => 'KG',
        ];
    }
}
