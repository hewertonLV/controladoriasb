<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFornecedorRequest;
use App\Http\Requests\Admin\UpdateFornecedorRequest;
use App\Models\Estado;
use App\Models\Fornecedor;
use App\Models\FornecedorHistorico;
use App\Queries\FornecedorQuery;
use App\Services\Fornecedores\FornecedorAuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FornecedorController extends Controller
{
    public function __construct(
        private readonly FornecedorAuditoriaService $auditoria,
        private readonly FornecedorQuery $fornecedorQuery,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->fornecedorQuery->filtrosFromRequest($request);
        $query = $this->fornecedorQuery->aplicarFiltros(Fornecedor::query(), $filtros);

        if ($filtros['per_page'] === 'all') {
            $total = (clone $query)->toBase()->count();
            $resultados = $query->get();
            $fornecedores = $resultados;
            $exibindo = $resultados->count();
        } else {
            $paginator = $query->paginate((int) $filtros['per_page'])->appends($filtros);
            $fornecedores = $paginator;
            $total = $paginator->total();
            $exibindo = count((array) $paginator->items());
        }

        $payload = [
            'fornecedores' => $fornecedores,
            'filtros' => $filtros,
            'perPageOptions' => FornecedorQuery::PER_PAGE_OPTIONS,
            'total' => $total,
            'exibindo' => $exibindo,
            'estados' => Estado::query()->orderBy('nome')->get(['id', 'nome', 'abreviacao']),
        ];

        if ($request->ajax()) {
            return view('admin.fornecedores._table', $payload);
        }

        return view('admin.fornecedores.index', $payload);
    }

    public function create(): View
    {
        return view('admin.fornecedores.create', [
            'estados' => Estado::query()->orderBy('nome')->get(['id', 'nome', 'abreviacao']),
            'fornecedor' => new Fornecedor([
                'id_estado' => Estado::ID_CEARA,
            ]),
        ]);
    }

    public function store(StoreFornecedorRequest $request): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        $fornecedor = DB::transaction(function () use ($dados, $user) {
            $fornecedor = Fornecedor::create($dados);

            $this->auditoria->registrarCriacao(
                $fornecedor,
                $user,
                FornecedorHistorico::ORIGEM_MANUAL,
            );

            return $fornecedor;
        });

        return redirect()
            ->route('admin.fornecedores.index')
            ->with('success', "Fornecedor \"{$fornecedor->razao_social}\" cadastrado com sucesso.");
    }

    public function show(Fornecedor $fornecedor): View
    {
        $fornecedor->load('estado');

        return view('admin.fornecedores.show', [
            'fornecedor' => $fornecedor,
        ]);
    }

    public function edit(Fornecedor $fornecedor): View
    {
        return view('admin.fornecedores.edit', [
            'estados' => Estado::query()->orderBy('nome')->get(['id', 'nome', 'abreviacao']),
            'fornecedor' => $fornecedor,
        ]);
    }

    public function update(UpdateFornecedorRequest $request, Fornecedor $fornecedor): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        DB::transaction(function () use ($fornecedor, $dados, $user) {
            $antes = $this->auditoria->snapshot($fornecedor);

            $fornecedor->update($dados);

            $depois = $this->auditoria->snapshot($fornecedor->fresh());

            $this->auditoria->registrarAtualizacao(
                $fornecedor,
                $antes,
                $depois,
                $user,
                FornecedorHistorico::ORIGEM_MANUAL,
            );
        });

        return redirect()
            ->route('admin.fornecedores.index')
            ->with('success', "Fornecedor \"{$fornecedor->razao_social}\" atualizado com sucesso.");
    }

    public function historico(Fornecedor $fornecedor): View
    {
        $fornecedor->load('estado');

        $historicos = $fornecedor->historicos()
            ->with('user')
            ->paginate(50);

        return view('admin.fornecedores.historico', [
            'fornecedor' => $fornecedor,
            'historicos' => $historicos,
            'estadosPorId' => Estado::query()->pluck('nome', 'id')->all(),
        ]);
    }
}
