<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\SyncClienteFrutaVinculoRequest;
use App\Models\Captacao\ClienteFrutaVinculo;
use App\Models\Cliente;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\ClienteFrutaVinculoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClienteFrutaVinculoController extends Controller
{
    public function __construct(
        private readonly ClienteFrutaVinculoService $vinculos,
    ) {}

    public function listagem(Request $request): View
    {
        $faturamentoId = $request->integer('faturamento') ?: null;

        $faturamentos = UnidadeNegocio::query()
            ->where('emite_nota_fiscal', true)
            ->where('is_hub', false)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        if ($faturamentoId === null && $faturamentos->isNotEmpty()) {
            $faturamentoId = $faturamentos->first()->id;
        }

        $clientes = collect();
        if ($faturamentoId !== null) {
            $clientes = Cliente::query()
                ->with([
                    'frutaVinculos' => fn ($q) => $q->where('ativo', true)->with('fruta:id,nome'),
                ])
                ->withCount([
                    'frutaVinculos as qtd_frutas' => fn ($q) => $q->where('ativo', true),
                ])
                ->where('id_unidade_negocio', $faturamentoId)
                ->orderBy('razao_social')
                ->get(['id', 'razao_social', 'fantasia', 'id_unidade_negocio']);
        }

        return view('admin.captacao.cliente-frutas.listagem', [
            'faturamentos' => $faturamentos,
            'faturamentoId' => $faturamentoId,
            'clientes' => $clientes,
            'podeSalvarVinculos' => $request->user()?->canAny([
                'captacao.cliente_fruta.vincular',
                'captacao.pedido.editar',
            ]) ?? false,
        ]);
    }

    public function index(Cliente $cliente): View
    {
        $cliente->load('unidadeNegocio:id,nome');

        $vinculadas = ClienteFrutaVinculo::query()
            ->where('id_cliente', $cliente->id)
            ->where('ativo', true)
            ->pluck('id_fruta')
            ->map(fn ($id) => (int) $id)
            ->all();

        $user = request()->user();

        return view('admin.captacao.cliente-frutas.index', [
            'cliente' => $cliente,
            'frutas' => Fruta::query()->orderBy('nome')->get(['id', 'nome']),
            'vinculadas' => $vinculadas,
            'podeSalvarVinculos' => $user?->canAny([
                'captacao.cliente_fruta.vincular',
                'captacao.pedido.editar',
            ]) ?? false,
        ]);
    }

    public function sync(SyncClienteFrutaVinculoRequest $request, Cliente $cliente): RedirectResponse
    {
        $ids = $request->validated('id_frutas') ?? [];
        $this->vinculos->sincronizarFrutas($cliente, $ids);

        return redirect()
            ->route('admin.captacao.clientes.frutas.index', $cliente)
            ->with('success', count($ids).' fruta(s) vinculada(s) a esta loja.');
    }

    public function store(Request $request, Cliente $cliente): RedirectResponse
    {
        $dados = $request->validate([
            'id_fruta' => ['required', 'integer', 'exists:frutas,id'],
        ]);

        $this->vinculos->sincronizarFrutas(
            $cliente,
            array_merge(
                ClienteFrutaVinculo::query()
                    ->where('id_cliente', $cliente->id)
                    ->where('ativo', true)
                    ->pluck('id_fruta')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
                [(int) $dados['id_fruta']],
            ),
        );

        return back()->with('success', 'Fruta vinculada.');
    }

    public function destroy(Cliente $cliente, ClienteFrutaVinculo $vinculo): RedirectResponse
    {
        abort_unless($vinculo->id_cliente === $cliente->id, 404);
        $vinculo->delete();

        return back()->with('success', 'Vínculo removido.');
    }
}
