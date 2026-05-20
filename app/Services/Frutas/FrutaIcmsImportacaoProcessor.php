<?php

namespace App\Services\Frutas;

use App\Models\Fruta;
use App\Models\FrutaIcmsImportacao;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class FrutaIcmsImportacaoProcessor
{
    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const COMPARABLE_FIELDS = [
        FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG,
        FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG,
        FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT,
        FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT,
        FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT,
        FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT,
    ];

    public function __construct(
        private readonly FrutaIcmsPlanilhaNormalizer $normalizer,
        private readonly FrutaIcmsSyncService $icmsSync,
    ) {}

    public function processar(FrutaIcmsImportacao $importacao): void
    {
        $importacao->forceFill([
            'status' => FrutaIcmsImportacao::STATUS_PROCESSANDO,
            'started_at' => $importacao->started_at ?? now(),
            'erro_mensagem' => null,
        ])->save();

        $absoluto = Storage::disk('local')->path($importacao->arquivo_path);
        if (! is_file($absoluto)) {
            throw new \RuntimeException("Arquivo de importação não encontrado: {$importacao->arquivo_path}");
        }

        $reader = IOFactory::createReaderForFile($absoluto);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setReadFilter(new ImportacaoReadFilter(self::MAX_LINHAS_ESCANEADAS));

        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = $reader->load($absoluto);
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = min((int) $sheet->getHighestDataRow(), self::MAX_LINHAS_ESCANEADAS);
        $totalLinhas = max(0, $highestRow - 1);

        $importacao->forceFill(['total_linhas' => $totalLinhas])->save();

        $novas = [];
        $atualizacoes = [];
        $semAlteracoes = [];
        $erros = [];
        $chavesVistas = [];
        $rowId = 0;
        $linhasProcessadas = 0;
        $linhasUteis = 0;

        for ($r = 2; $r <= $highestRow; $r++) {
            $dadosBrutos = [];
            for ($col = 1; $col <= 11; $col++) {
                $dadosBrutos[] = $sheet->getCell(Coordinate::stringFromColumnIndex($col).$r)->getCalculatedValue();
            }

            $linhasProcessadas++;

            if ($this->linhaVazia($dadosBrutos)) {
                continue;
            }

            $linhasUteis++;
            if ($linhasUteis > self::MAX_LINHAS_UTEIS) {
                $erros[] = $this->erroLinha(++$rowId, $r, '', ['Limite de linhas úteis excedido.'], []);
                break;
            }

            $rowId++;
            $normalized = $this->normalizer->normalize($dadosBrutos);
            $dados = $normalized['dados'];

            if ($normalized['erros'] !== []) {
                $erros[] = $this->erroLinha($rowId, $r, (string) ($dados['fruta_ref'] ?? ''), $normalized['erros'], $dados);

                continue;
            }

            $chave = $dados['fruta_id'].'-'.$dados['id_estado'];
            if (isset($chavesVistas[$chave])) {
                $erros[] = $this->erroLinha(
                    $rowId,
                    $r,
                    (string) $dados['fruta_ref'],
                    ["Combinação fruta/estado duplicada na planilha (linha {$chavesVistas[$chave]})."],
                    $dados,
                );

                continue;
            }
            $chavesVistas[$chave] = $r;

            /** @var Fruta $fruta */
            $fruta = Fruta::query()->findOrFail($dados['fruta_id']);
            $idEstado = (int) $dados['id_estado'];
            $snapshot = $this->icmsSync->snapshotImportacao($fruta, $idEstado);
            $diff = $this->diffCampos($snapshot, $dados);

            $item = [
                'row_id' => $rowId,
                'linha' => $r,
                'fruta_id' => $fruta->id,
                'id_estado' => $idEstado,
                'fruta_ref' => $dados['fruta_ref'],
                'fruta_nome' => $fruta->nome,
                'fruta_id_cigam' => $fruta->id_cigam,
                'estado_ref' => $dados['estado_ref'],
                'dados_novos' => $this->dadosComparaveis($dados),
                'dados_atuais' => $snapshot,
                'campos_alterados' => $diff,
            ];

            if ($diff === []) {
                if ($this->icmsSync->possuiConfiguracao($fruta, $idEstado)) {
                    $semAlteracoes[] = $item;
                } else {
                    $novas[] = $item;
                }
            } elseif (! $this->icmsSync->possuiConfiguracao($fruta, $idEstado)) {
                $novas[] = $item;
            } else {
                $atualizacoes[] = $item;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $reader);
        gc_collect_cycles();

        $importacao->forceFill([
            'status' => FrutaIcmsImportacao::STATUS_CONCLUIDO,
            'finished_at' => now(),
            'linhas_processadas' => $linhasProcessadas,
            'percentual' => 100,
            'novas_count' => count($novas),
            'atualizacoes_count' => count($atualizacoes),
            'sem_alteracoes_count' => count($semAlteracoes),
            'erros_count' => count($erros),
            'resultado' => compact('novas', 'atualizacoes', 'semAlteracoes', 'erros'),
        ])->save();
    }

    public function marcarFalha(FrutaIcmsImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de ICMS de Frutas falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => FrutaIcmsImportacao::STATUS_FALHOU,
            'finished_at' => now(),
            'erro_mensagem' => 'Falha ao processar a planilha: '.$e->getMessage(),
        ])->save();
    }

    /**
     * @param  list<mixed>  $row
     */
    private function linhaVazia(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) ($cell ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $mensagens
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function erroLinha(int $rowId, int $linha, string $frutaRef, array $mensagens, array $dados): array
    {
        return [
            'row_id' => $rowId,
            'linha' => $linha,
            'fruta_ref' => $frutaRef,
            'erros' => $mensagens,
            'dados' => $dados,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, string>
     */
    private function dadosComparaveis(array $dados): array
    {
        $dados = FrutaIcmsLinhaFormulario::normalizarChavesLegadas($dados);
        $comparaveis = [];

        foreach (self::COMPARABLE_FIELDS as $campo) {
            $comparaveis[$campo] = number_format(max(0, (float) ($dados[$campo] ?? 0)), 2, '.', '');
        }

        return $comparaveis;
    }

    /**
     * @param  array<string, string>  $atual
     * @param  array<string, mixed>  $novos
     * @return list<array{campo: string, atual: string, novo: string}>
     */
    private function diffCampos(array $atual, array $novos): array
    {
        $comparaveis = $this->dadosComparaveis($novos);
        $diff = [];

        foreach (self::COMPARABLE_FIELDS as $campo) {
            if (($atual[$campo] ?? '') !== ($comparaveis[$campo] ?? '')) {
                $diff[] = [
                    'campo' => $campo,
                    'atual' => $atual[$campo] ?? '',
                    'novo' => $comparaveis[$campo] ?? '',
                ];
            }
        }

        return $diff;
    }
}
