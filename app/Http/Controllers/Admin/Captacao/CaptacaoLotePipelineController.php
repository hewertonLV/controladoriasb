<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Actions\Captacao\ConcluirEtapaFreteLoteAction;
use App\Actions\Captacao\ConcluirSaidaEstoqueFisicoLoteAction;
use App\Actions\Captacao\DefinirHubOrigemCiganLoteAction;
use App\Actions\Captacao\ConcluirFreteVendaCaptacaoLoteAction;
use App\Actions\Captacao\ConcluirVinculoRotasCaptacaoLoteAction;
use App\Actions\Captacao\FinalizarVendasLoteAction;
use App\Enums\CaptacaoLoteStatus;
use App\Actions\Captacao\IniciarFaturamentoCiganAction;
use App\Actions\Captacao\IniciarTransferenciaCiganAction;
use App\Actions\Captacao\EnviarNfTransferenciaCiganLoteAction;
use App\Actions\Captacao\EnviarNfVendaCiganLoteAction;
use App\Actions\Captacao\ValidarTransferenciasGerenciaisLoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\DefinirHubOrigemCiganLoteRequest;
use App\Http\Requests\Admin\Captacao\UploadNfTransferenciaCiganLoteRequest;
use App\Http\Requests\Admin\Captacao\UploadNfVendaCiganLoteRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteCiganExport;
use App\Services\Captacao\GerarArquivoCiganService;
use App\Services\Captacao\GerarVendasCaptacaoLoteService;
use App\Services\Captacao\NfTransferenciaEstoqueHubInsuficienteException;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaptacaoLotePipelineController extends Controller
{
    public function iniciarTransferencia(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(IniciarTransferenciaCiganAction::class)->executar($lote, $request->user());

        return back()->with('success', 'Transferência Cigam iniciada.');
    }

    public function validarTransferencias(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(ValidarTransferenciasGerenciaisLoteAction::class)->executar($lote);

        return back()->with('success', 'Transferências gerenciais validadas.');
    }

    public function concluirSaidaEstoqueFisico(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);

        try {
            app(ConcluirSaidaEstoqueFisicoLoteAction::class)->executar($lote);
        } catch (NfTransferenciaEstoqueHubInsuficienteException $e) {
            return redirect()
                ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'saida-estoque-fisico'])
                ->with('nf_transferencia_estoque_hub_insuficiente', [
                    'hub_nome' => $e->hubNome,
                    'frutas' => $e->frutas,
                ]);
        }

        return redirect()
            ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'frete-hub'])
            ->with('success', 'Transferências gerenciais efetivadas. Vincule o frete se necessário.');
    }

    public function concluirFrete(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(ConcluirEtapaFreteLoteAction::class)->executar($lote);

        return back()->with('success', 'Etapa de frete concluída.');
    }

    public function iniciarFaturamento(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(IniciarFaturamentoCiganAction::class)->executar($lote, $request->user());

        return back()->with('success', 'Faturamento Cigam iniciado.');
    }

    public function finalizarVendas(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        return $this->concluirVinculoRotas($request, $lote);
    }

    public function concluirVinculoRotas(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(ConcluirVinculoRotasCaptacaoLoteAction::class)->executar($lote);

        return redirect()
            ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'frete-vendas'])
            ->with('success', 'Rotas e ordem de carregamento concluídos. Vincule o frete das vendas se necessário.');
    }

    public function concluirFreteVenda(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(ConcluirFreteVendaCaptacaoLoteAction::class)->executar($lote);

        return redirect()
            ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'quantidade'])
            ->with('success', 'Frete de vendas concluído. Lote em vendas finalizadas.');
    }

    public function downloadCigan(CaptacaoLoteCiganExport $export): StreamedResponse
    {
        return Storage::disk('local')->download($export->caminho_arquivo);
    }

    public function definirHubOrigemCigan(DefinirHubOrigemCiganLoteRequest $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);

        app(DefinirHubOrigemCiganLoteAction::class)->executar(
            $lote,
            (int) $request->validated('id_unidade_negocio_hub_origem'),
        );

        return redirect()
            ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan'])
            ->with('success', 'HUB de origem definido. Você já pode baixar o arquivo TXT.');
    }

    public function uploadNfTransferencia(UploadNfTransferenciaCiganLoteRequest $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);

        try {
            app(EnviarNfTransferenciaCiganLoteAction::class)->executar(
                $lote,
                $request->file('arquivo_nf_transferencia'),
                $request->user(),
            );
        } catch (NfTransferenciaEstoqueHubInsuficienteException $e) {
            return redirect()
                ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan'])
                ->with('nf_transferencia_estoque_hub_insuficiente', [
                    'hub_nome' => $e->hubNome,
                    'frutas' => $e->frutas,
                ])
                ->withInput();
        }

        return redirect()
            ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'saida-estoque-fisico'])
            ->with('success', 'NF de transferência enviada. Defina a saída física por loja e conclua a etapa.');
    }

    public function downloadNfTransferencia(Request $request, CaptacaoLote $lote): StreamedResponse
    {
        $this->assertGalpao($request, $lote);

        if (! $lote->status->exibeAbaArquivoCiganTransferencia() || ! $lote->possuiNfTransferencia()) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($lote->arquivo_nf_transferencia_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $lote->arquivo_nf_transferencia_path,
            $lote->arquivo_nf_transferencia_nome ?? basename($lote->arquivo_nf_transferencia_path),
        );
    }

    public function uploadNfVenda(UploadNfVendaCiganLoteRequest $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);

        $lote = app(EnviarNfVendaCiganLoteAction::class)->executar(
            $lote,
            $request->file('arquivo_nf_venda'),
            $request->user(),
        );

        if ($lote->status === CaptacaoLoteStatus::VendasFinalizadas) {
            return redirect()
                ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'frete-vendas'])
                ->with('success', 'NF de venda enviada. Vendas efetivadas no SB Controladoria.');
        }

        return redirect()
            ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rotas'])
            ->with('success', 'NF de venda enviada e vendas movimentadas no SB. Vincule as rotas pendentes e clique em Concluído.');
    }

    public function sincronizarVendasPendentes(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);

        if (! $lote->possuiNfVenda()) {
            return back()->withErrors([
                'vendas' => 'Envie a NF de venda antes de sincronizar as movimentações.',
            ]);
        }

        $gerador = app(GerarVendasCaptacaoLoteService::class);

        if (! $gerador->possuiVendasPendentes($lote)) {
            return redirect()
                ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan'])
                ->with('success', 'Todas as lojas com quantidade já possuem movimentação de venda no SB.');
        }

        $gerador->executar($lote, $request->user());

        return redirect()
            ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan'])
            ->with('success', 'Vendas pendentes do lote foram geradas no SB.');
    }

    public function downloadNfVenda(Request $request, CaptacaoLote $lote): StreamedResponse
    {
        $this->assertGalpao($request, $lote);

        if (! $lote->status->exibeAbaArquivoCiganVendas() || ! $lote->possuiNfVenda()) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($lote->arquivo_nf_venda_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $lote->arquivo_nf_venda_path,
            $lote->arquivo_nf_venda_nome ?? basename($lote->arquivo_nf_venda_path),
        );
    }

    public function downloadArquivoCiganVendas(Request $request, CaptacaoLote $lote): StreamedResponse|RedirectResponse
    {
        $this->assertGalpao($request, $lote);

        if (! $lote->status->exibeAbaArquivoCiganVendas()) {
            abort(404);
        }

        try {
            $conteudo = app(GerarArquivoCiganService::class)->conteudoTxtVendas($lote);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan'])
                ->withErrors($exception->errors());
        }

        $nomeArquivo = sprintf('cigan-vendas-lote-%d.txt', $lote->id);

        return response()->streamDownload(
            static function () use ($conteudo): void {
                echo $conteudo;
            },
            $nomeArquivo,
            ['Content-Type' => 'text/plain; charset=ISO-8859-1'],
        );
    }

    public function downloadArquivoCiganTransferencia(Request $request, CaptacaoLote $lote): StreamedResponse|RedirectResponse
    {
        $this->assertGalpao($request, $lote);

        if (! $lote->status->exibeAbaArquivoCiganTransferencia()) {
            abort(404);
        }

        if ($lote->id_unidade_negocio_hub_origem === null) {
            return redirect()
                ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan'])
                ->withErrors([
                    'id_unidade_negocio_hub_origem' => 'Informe a unidade HUB de origem antes de baixar o arquivo Cigam.',
                ]);
        }

        try {
            $conteudo = app(GerarArquivoCiganService::class)->conteudoTxtTransferencia($lote);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan'])
                ->withErrors($exception->errors());
        }
        $nomeArquivo = sprintf('cigan-transferencia-lote-%d.txt', $lote->id);

        return response()->streamDownload(
            static function () use ($conteudo): void {
                echo $conteudo;
            },
            $nomeArquivo,
            ['Content-Type' => 'text/plain; charset=ISO-8859-1'],
        );
    }

    private function assertGalpao(Request $request, CaptacaoLote $lote): void
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }
    }
}
