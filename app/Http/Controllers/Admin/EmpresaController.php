<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Queries\EmpresaQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class EmpresaController extends Controller
{
    public function __construct(
        private readonly EmpresaQuery $empresaQuery,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->empresaQuery->filtrosFromRequest($request);
        $query = $this->empresaQuery->aplicarFiltros(
            Empresa::query()->withEntidadeParaListagem(),
            $filtros,
        );

        if ($filtros['per_page'] === 'all') {
            $total = (clone $query)->toBase()->count();
            $resultados = $query->get();
            $empresas = $resultados;
            $exibindo = $resultados->count();
        } else {
            $paginator = $query->paginate((int) $filtros['per_page'])->appends($filtros);
            $empresas = $paginator;
            $total = $paginator->total();
            $exibindo = count((array) $paginator->items());
        }

        $payload = [
            'empresas' => $empresas,
            'filtros' => $filtros,
            'perPageOptions' => EmpresaQuery::PER_PAGE_OPTIONS,
            'total' => $total,
            'exibindo' => $exibindo,
        ];

        if ($request->ajax()) {
            return view('admin.empresas._table', $payload);
        }

        return view('admin.empresas.index', $payload);
    }

    public function exportarPdf(Request $request): Response
    {
        $filtros = $this->empresaQuery->filtrosFromRequest($request);

        /** @var Collection<int, Empresa> $empresas */
        $empresas = $this->empresaQuery->aplicarFiltros(
            Empresa::query()->withEntidadeParaListagem(),
            $filtros,
        )->get();

        $pdf = Pdf::loadView('admin.empresas.pdf', [
            'empresas' => $empresas,
            'filtros' => $filtros,
            'geradoEm' => now(),
            'geradoPor' => $request->user()?->name ?? '—',
        ])->setPaper('a4', 'landscape');

        $arquivo = 'empresas_'.now()->format('Ymd_His').'.pdf';

        return $pdf->stream($arquivo);
    }

    public function historico(Empresa $empresa): View
    {
        $historicos = $empresa->historicos()
            ->with('user')
            ->paginate(50);

        return view('admin.empresas.historico', [
            'empresa' => $empresa->loadMissing(['entidade']),
            'historicos' => $historicos,
        ]);
    }
}
