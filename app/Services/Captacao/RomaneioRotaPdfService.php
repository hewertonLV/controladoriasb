<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoRota;
use App\Support\Captacao\RomaneioRotaPdfBranding;
use App\Support\Captacao\RomaneioRotaPdfNomeArquivo;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RomaneioRotaPdfService
{
    public function __construct(
        private readonly RomaneioCarregamentoService $romaneioCarregamento,
        private readonly CaptacaoMatrizRotasService $matrizRotas,
    ) {}

    /**
     * @return array{
     *     romaneio: array<string, mixed>,
     *     lote: CaptacaoLote,
     *     nome_arquivo: string,
     *     veiculo_nome: string|null,
     * }
     */
    public function dadosParaPdf(CaptacaoLote $lote, CaptacaoRota $rota): array
    {
        if (! $this->matrizRotas->rotaEstaConcluidaNoLote($lote, $rota->id)) {
            throw new NotFoundHttpException('Romaneio disponível apenas para rotas concluídas.');
        }

        if ($rota->id_captacao_carteira !== $lote->id_captacao_carteira) {
            throw new NotFoundHttpException('A rota não pertence à carteira deste lote.');
        }

        $romaneio = $this->romaneioCarregamento
            ->previewPorRotas($lote)
            ->firstWhere('id_captacao_rota', $rota->id);

        if ($romaneio === null) {
            throw new NotFoundHttpException('Nenhuma loja com quantidade vinculada a esta rota.');
        }

        $veiculoNome = null;
        if (! empty($romaneio['veiculo_rotulo'])) {
            $veiculoNome = preg_replace('/\s*\(SBS\s+.+\)\s*$/u', '', (string) $romaneio['veiculo_rotulo']);
            $veiculoNome = trim((string) $veiculoNome) !== '' ? trim((string) $veiculoNome) : null;
        }

        return [
            'romaneio' => $this->enriquecerLojasParaPdf($romaneio),
            'lote' => $lote->loadMissing(['unidadeGalpao:id,nome']),
            'nome_arquivo' => RomaneioRotaPdfNomeArquivo::gerar(
                (string) $romaneio['rota_nome'],
                $romaneio['motorista_nome'] ?? null,
            ),
            'veiculo_nome' => $veiculoNome,
        ];
    }

    public function gerarPdf(CaptacaoLote $lote, CaptacaoRota $rota): DomPdfDocument
    {
        $dados = $this->dadosParaPdf($lote, $rota);

        return Pdf::loadView('admin.captacao.pdf.romaneio-rota', [
            'romaneio' => $dados['romaneio'],
            'lote' => $dados['lote'],
            'veiculoNome' => $dados['veiculo_nome'],
            'geradoEm' => now(),
            'logoDataUri' => RomaneioRotaPdfBranding::logoDataUri(),
            'cores' => [
                'azul' => RomaneioRotaPdfBranding::COR_AZUL,
                'amarelo' => RomaneioRotaPdfBranding::COR_AMARELO,
                'verde' => RomaneioRotaPdfBranding::COR_VERDE,
            ],
        ])->setPaper('a4', 'portrait');
    }

    /**
     * @param  array<string, mixed>  $romaneio
     * @return array<string, mixed>
     */
    private function enriquecerLojasParaPdf(array $romaneio): array
    {
        /** @var Collection<int, array<string, mixed>> $lojas */
        $lojas = collect($romaneio['lojas'])->map(function (array $loja): array {
            $itens = collect($loja['itens'])->map(function (array $item): array {
                $unidade = mb_strtoupper(trim((string) ($item['unidade_medicao'] ?? '')), 'UTF-8');
                $exibeCaixas = in_array($unidade, ['CX', 'CXS', 'CAIXA', 'CAIXAS'], true);

                return [
                    ...$item,
                    'caixas_formatado' => $exibeCaixas ? ($item['quantidade_um_formatado'] ?? '—') : '—',
                ];
            })->all();

            $totalCaixas = collect($itens)
                ->filter(fn (array $item): bool => ($item['caixas_formatado'] ?? '—') !== '—')
                ->sum(fn (array $item): float => (float) ($item['quantidade_um'] ?? 0));

            return [
                ...$loja,
                'itens' => $itens,
                'total_caixas' => $totalCaixas,
                'total_caixas_formatado' => $totalCaixas > 0
                    ? number_format($totalCaixas, 2, ',', '.')
                    : '—',
            ];
        });

        $totalCaixasGeral = $lojas->sum(fn (array $loja): float => (float) ($loja['total_caixas'] ?? 0));

        $romaneio['lojas'] = $lojas->values()->all();
        $romaneio['totais_gerais']['total_caixas_formatado'] = $totalCaixasGeral > 0
            ? number_format($totalCaixasGeral, 2, ',', '.')
            : '—';

        return $romaneio;
    }
}
