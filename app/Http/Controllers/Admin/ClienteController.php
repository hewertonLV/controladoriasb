<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClienteRequest;
use App\Http\Requests\Admin\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\ClienteHistorico;
use App\Models\Grupo;
use App\Models\Praca;
use App\Models\UnidadeNegocio;
use App\Queries\ClienteQuery;
use App\Models\Captacao\CaptacaoCarteira;
use App\Services\Captacao\ClienteCaptacaoAgendaService;
use App\Services\Clientes\ClienteAuditoriaService;
use App\Support\Captacao\DiasSemanaCaptacao;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function __construct(
        private readonly ClienteAuditoriaService $auditoria,
        private readonly ClienteQuery $clienteQuery,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->clienteQuery->filtrosFromRequest($request);
        $clientes = $this->clienteQuery->aplicarFiltros(
            Cliente::query()->with(['praca', 'grupo']),
            $filtros,
        )->get();

        return view('admin.clientes.index', [
            'clientes' => $clientes,
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('admin.clientes.create', [
            'cliente' => new Cliente([
                'desconto_nf' => '0.00',
            ]),
            'unidadesNegocio' => $this->unidadesParaFormulario(),
            'pracas' => $this->pracasParaFormulario(),
            'grupos' => $this->gruposParaFormulario(),
            'carteirasCaptacao' => $this->carteirasParaFormulario(),
            'diasSemanaCaptacao' => DiasSemanaCaptacao::labels(),
            'diasCriacaoPedido' => [],
            'diasEnvioPedido' => [],
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->dadosClientePersistencia();

        $cliente = DB::transaction(function () use ($dados, $user, $request) {
            $cliente = Cliente::create($dados);

            app(ClienteCaptacaoAgendaService::class)->sincronizar(
                $cliente,
                (array) $request->input('dias_criacao_pedido', []),
                (array) $request->input('dias_envio_pedido', []),
            );

            $this->auditoria->registrarCriacao(
                $cliente,
                $user,
                ClienteHistorico::ORIGEM_MANUAL,
            );

            return $cliente;
        });

        return redirect()
            ->route('admin.clientes.index')
            ->with('success', "Cliente \"{$cliente->razao_social}\" cadastrado com sucesso.");
    }

    public function edit(Cliente $cliente): View
    {
        $agenda = app(ClienteCaptacaoAgendaService::class)->diasPorCliente($cliente);

        return view('admin.clientes.edit', [
            'cliente' => $cliente->load(['praca', 'grupo']),
            'unidadesNegocio' => $this->unidadesParaFormulario(),
            'pracas' => $this->pracasParaFormulario(),
            'grupos' => $this->gruposParaFormulario(),
            'carteirasCaptacao' => $this->carteirasParaFormulario(),
            'diasSemanaCaptacao' => DiasSemanaCaptacao::labels(),
            'diasCriacaoPedido' => $agenda['criacao'],
            'diasEnvioPedido' => $agenda['envio'],
        ]);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->dadosClientePersistencia();

        DB::transaction(function () use ($cliente, $dados, $user, $request) {
            $antes = $this->auditoria->snapshot($cliente);

            $cliente->update($dados);

            app(ClienteCaptacaoAgendaService::class)->sincronizar(
                $cliente,
                (array) $request->input('dias_criacao_pedido', []),
                (array) $request->input('dias_envio_pedido', []),
            );

            $depois = $this->auditoria->snapshot($cliente->fresh());

            $this->auditoria->registrarAtualizacao(
                $cliente,
                $antes,
                $depois,
                $user,
                ClienteHistorico::ORIGEM_MANUAL,
            );
        });

        return redirect()
            ->route('admin.clientes.index')
            ->with('success', "Cliente \"{$cliente->razao_social}\" atualizado com sucesso.");
    }

    public function historico(Cliente $cliente): View
    {
        $historicos = $cliente->historicos()
            ->with('user')
            ->paginate(50);

        return view('admin.clientes.historico', [
            'cliente' => $cliente->load(['praca', 'grupo']),
            'historicos' => $historicos,
        ]);
    }

    /**
     * @return Collection<int, UnidadeNegocio>
     */
    private function unidadesParaFormulario()
    {
        return UnidadeNegocio::query()
            ->where('is_hub', false)
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_cigam']);
    }

    /**
     * @return Collection<int, Praca>
     */
    private function pracasParaFormulario()
    {
        return Praca::query()
            ->with('unidadeNegocio:id,nome')
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_unidade_negocio']);
    }

    /**
     * @return Collection<int, Grupo>
     */
    private function gruposParaFormulario()
    {
        return Grupo::query()
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    /**
     * @return Collection<int, CaptacaoCarteira>
     */
    private function carteirasParaFormulario()
    {
        return CaptacaoCarteira::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }
}
