<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\StoreCaptacaoCarteiraRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoCarteiraRequest;
use App\Models\Captacao\CaptacaoCarteira;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\CaptacaoCarteiraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CaptacaoCarteiraController extends Controller
{
    public function __construct(
        private readonly CaptacaoCarteiraService $carteiras,
    ) {}

    public function index(Request $request): View
    {
        $aba = $request->string('aba', 'ativas')->toString();
        if (! in_array($aba, ['ativas', 'inativas'], true)) {
            $aba = 'ativas';
        }

        $somenteAtivas = $aba === 'ativas';

        $carteiras = CaptacaoCarteira::query()
            ->with(['unidadeFaturamento:id,nome', 'unidadeGalpao:id,nome'])
            ->withCount('clientes')
            ->where('ativo', $somenteAtivas)
            ->orderBy('nome')
            ->get();

        return view('admin.captacao.carteiras.index', [
            'carteiras' => $carteiras,
            'aba' => $aba,
            'somenteAtivas' => $somenteAtivas,
        ]);
    }

    public function create(): View
    {
        return view('admin.captacao.carteiras.create', $this->dadosFormulario());
    }

    public function store(StoreCaptacaoCarteiraRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $dados['ativo'] = true;
        try {
            $this->carteiras->validarUnidades(
                (int) $dados['id_unidade_negocio_faturamento'],
                (int) $dados['id_unidade_negocio_galpao'],
            );
            CaptacaoCarteira::query()->create($dados);
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.captacao.carteiras.create')
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.captacao.carteiras.index', ['aba' => 'ativas'])
            ->with('success', 'Carteira cadastrada.');
    }

    public function edit(CaptacaoCarteira $carteira): View
    {
        $carteira->load(['unidadeFaturamento:id,nome']);

        return view('admin.captacao.carteiras.edit', array_merge(
            [
                'carteira' => $carteira,
                'lojasVinculadas' => $this->carteiras->lojasVinculadas($carteira),
                'lojasSemCarteira' => $this->carteiras->lojasSemCarteiraNoFaturamento($carteira),
            ],
            $this->dadosFormulario(),
        ));
    }

    public function update(UpdateCaptacaoCarteiraRequest $request, CaptacaoCarteira $carteira): RedirectResponse
    {
        $dados = $request->validated();
        $idClientes = $request->idClientesSelecionados();
        unset($dados['ativo'], $dados['id_clientes']);

        try {
            $this->carteiras->validarUnidades(
                (int) $dados['id_unidade_negocio_faturamento'],
                (int) $dados['id_unidade_negocio_galpao'],
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.captacao.carteiras.edit', $carteira)
                ->withInput()
                ->withErrors($e->errors());
        }

        try {
            DB::transaction(function () use ($carteira, $dados, $idClientes): void {
                $carteira->update($dados);
                $this->carteiras->sincronizarLojas($carteira, $idClientes);
            });
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.captacao.carteiras.edit', $carteira)
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.captacao.carteiras.index', ['aba' => $carteira->fresh()->ativo ? 'ativas' : 'inativas'])
            ->with('success', 'Carteira e lojas vinculadas atualizadas.');
    }

    public function inativar(CaptacaoCarteira $carteira): RedirectResponse
    {
        try {
            $this->carteiras->inativar($carteira);
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.captacao.carteiras.index', ['aba' => 'ativas'])
                ->withErrors($e->errors())
                ->with('error', $e->errors()['carteira'][0] ?? 'Não foi possível inativar a carteira.');
        }

        return redirect()
            ->route('admin.captacao.carteiras.index', ['aba' => 'inativas'])
            ->with('success', "Carteira «{$carteira->nome}» inativada.");
    }

    public function reativar(CaptacaoCarteira $carteira): RedirectResponse
    {
        $this->carteiras->reativar($carteira);

        return redirect()
            ->route('admin.captacao.carteiras.index', ['aba' => 'ativas'])
            ->with('success', "Carteira «{$carteira->nome}» reativada.");
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosFormulario(): array
    {
        return [
            'faturamentos' => UnidadeNegocio::query()
                ->where('emite_nota_fiscal', true)
                ->where('is_hub', false)
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'galpoes' => UnidadeNegocio::query()
                ->where('is_galpao_operacional', true)
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ];
    }
}
