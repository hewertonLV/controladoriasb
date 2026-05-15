<?php

namespace App\Services\UnidadesNegocio;

use App\Models\Estado;
use App\Models\UnidadeNegocio;
use App\Models\UnidadeNegocioImportacao;
use App\Support\TextoCadastro;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Processa a planilha de Unidades de Negócio em background (apenas preview).
 *
 * Layout fixo (linha 1 = cabeçalho ignorado na contagem de dados):
 *  - A: id_cigam
 *  - B: razao_social
 *  - C: nome
 *  - D: cpf_cnpj
 *  - E: custo_operacional
 *  - F: possui_estoque (SIM/NÃO, S/N, 1/0, VERDADEIRO/FALSO)
 *  - G: estado (nome cadastrado, ex.: CEARA)
 *
 * @see UnidadeNegocioImportacaoController::confirmar para persistência.
 */
class UnidadeNegocioImportacaoProcessor
{
    /**
     * @var array<string, int>|null
     */
    private ?array $idsEstadoPorNome = null;

    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const CHUNK_DB = 100;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    public function processar(UnidadeNegocioImportacao $importacao): void
    {
        $importacao->forceFill([
            'status' => UnidadeNegocioImportacao::STATUS_PROCESSANDO,
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
        $reader->setReadFilter(new UnidadeNegocioImportacaoReadFilter(self::MAX_LINHAS_ESCANEADAS));

        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = $reader->load($absoluto);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = min((int) $sheet->getHighestDataRow(), self::MAX_LINHAS_ESCANEADAS);
        $totalLinhas = max(0, $highestRow - 1);

        $importacao->forceFill(['total_linhas' => $totalLinhas])->save();

        $novas = [];
        $atualizacoes = [];
        $semAlteracoes = [];
        $erros = [];

        $idsCigamVistos = [];
        $buffer = [];

        $rowId = 0;
        $linhasProcessadas = 0;
        $linhasUteis = 0;

        for ($r = 2; $r <= $highestRow; $r++) {
            $dadosBrutos = [
                $sheet->getCell('A'.$r)->getValue(),
                $sheet->getCell('B'.$r)->getValue(),
                $sheet->getCell('C'.$r)->getValue(),
                $sheet->getCell('D'.$r)->getValue(),
                $sheet->getCell('E'.$r)->getValue(),
                $sheet->getCell('F'.$r)->getValue(),
                $sheet->getCell('G'.$r)->getValue(),
            ];

            $linhasProcessadas++;

            if ($this->linhaVazia($dadosBrutos)) {
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $atualizacoes,
                    $semAlteracoes,
                    $erros,
                );

                continue;
            }

            $linhasUteis++;

            if ($linhasUteis > self::MAX_LINHAS_UTEIS) {
                $erros[] = [
                    'row_id' => ++$rowId,
                    'linha' => $r,
                    'id_cigam' => '',
                    'erros' => ['Limite de '.self::MAX_LINHAS_UTEIS.' linhas úteis excedido. As linhas seguintes foram ignoradas.'],
                    'dados' => [],
                ];
                break;
            }

            $rowId++;
            $normalized = $this->normalizeRow($dadosBrutos);
            $dados = $normalized['dados'];
            $errosLinha = $normalized['erros'];
            $idCigam = $dados['id_cigam'];

            if ($idCigam !== '' && isset($idsCigamVistos[$idCigam])) {
                $errosLinha[] = "ID CIGAM duplicado na planilha (já aparece na linha {$idsCigamVistos[$idCigam]}).";
            } elseif ($idCigam !== '') {
                $idsCigamVistos[$idCigam] = $r;
            }

            if ($errosLinha !== []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $r,
                    'id_cigam' => $idCigam,
                    'erros' => $errosLinha,
                    'dados' => $dados,
                ];
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $atualizacoes,
                    $semAlteracoes,
                    $erros,
                );

                continue;
            }

            $buffer[] = [
                'row_id' => $rowId,
                'linha' => $r,
                'dados' => $dados,
            ];

            if (count($buffer) >= self::CHUNK_DB) {
                $this->flushBuffer($buffer, $novas, $atualizacoes, $semAlteracoes, $erros);
                $buffer = [];
                $this->salvarProgresso(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $atualizacoes,
                    $semAlteracoes,
                    $erros,
                );
            } else {
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $atualizacoes,
                    $semAlteracoes,
                    $erros,
                );
            }
        }

        if ($buffer !== []) {
            $this->flushBuffer($buffer, $novas, $atualizacoes, $semAlteracoes, $erros);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $reader, $sheet);
        gc_collect_cycles();

        $resultado = [
            'novas' => $novas,
            'atualizacoes' => $atualizacoes,
            'sem_alteracoes' => $semAlteracoes,
            'erros' => $erros,
        ];

        $importacao->forceFill([
            'status' => UnidadeNegocioImportacao::STATUS_CONCLUIDO,
            'finished_at' => now(),
            'linhas_processadas' => $linhasProcessadas,
            'percentual' => 100,
            'novas_count' => count($novas),
            'atualizacoes_count' => count($atualizacoes),
            'sem_alteracoes_count' => count($semAlteracoes),
            'erros_count' => count($erros),
            'resultado' => $resultado,
        ])->save();
    }

    public function marcarFalha(UnidadeNegocioImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de Unidades de Negócio falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => UnidadeNegocioImportacao::STATUS_FALHOU,
            'finished_at' => now(),
            'erro_mensagem' => $this->mensagemAmigavel($e),
        ])->save();
    }

    /**
     * @param  list<mixed>  $dadosBrutos
     * @return array{dados: array{id_cigam: string, razao_social: string, nome: string, cpf_cnpj: string, custo_operacional: string, possui_estoque: bool, id_estado: int}, erros: list<string>}
     */
    private function normalizeRow(array $dadosBrutos): array
    {
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) ($dadosBrutos[0] ?? ''));
        $razaoSocial = TextoCadastro::normalizarMaiusculas((string) ($dadosBrutos[1] ?? ''));
        $nome = TextoCadastro::normalizarMaiusculas((string) ($dadosBrutos[2] ?? ''));
        $cpfCnpj = TextoCadastro::somenteDigitos((string) ($dadosBrutos[3] ?? ''));
        $custoOperacional = TextoCadastro::normalizarValorMonetarioBrasileiro($dadosBrutos[4] ?? '0');

        $possuiParsed = $this->parsePossuiEstoquePlanilha($dadosBrutos[5] ?? null);

        $estadoNome = TextoCadastro::normalizarMaiusculas(trim((string) ($dadosBrutos[6] ?? '')));
        $idEstado = $estadoNome === '' ? null : ($this->idsEstadoPorNome()[$estadoNome] ?? null);

        $erros = [];

        if ($idCigam === '') {
            $erros[] = 'ID CIGAM (coluna A) é obrigatório.';
        } elseif (! preg_match('/^\d{6}$/', $idCigam)) {
            $erros[] = 'ID CIGAM deve ter no máximo 6 dígitos numéricos.';
        }

        if ($razaoSocial === '') {
            $erros[] = 'Razão social (coluna B) é obrigatória.';
        } elseif (mb_strlen($razaoSocial) > 255) {
            $erros[] = 'Razão social pode ter no máximo 255 caracteres.';
        }

        if ($nome === '') {
            $erros[] = 'Nome (coluna C) é obrigatório.';
        } elseif (mb_strlen($nome) > 255) {
            $erros[] = 'Nome pode ter no máximo 255 caracteres.';
        }

        if ($cpfCnpj === '') {
            $erros[] = 'CPF/CNPJ (coluna D) é obrigatório.';
        } elseif (! in_array(strlen($cpfCnpj), [11, 14], true)) {
            $erros[] = 'CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).';
        }

        $erros = array_merge($erros, $possuiParsed['erros']);

        if ($estadoNome === '') {
            $erros[] = 'Estado (coluna G) é obrigatório. Informe o nome cadastrado (ex.: CEARA).';
        } elseif ($idEstado === null) {
            $erros[] = 'Estado inválido na coluna G. Valores aceitos: '.implode(', ', array_keys($this->idsEstadoPorNome())).'.';
        }

        return [
            'dados' => [
                'id_cigam' => $idCigam,
                'razao_social' => $razaoSocial,
                'nome' => $nome,
                'cpf_cnpj' => $cpfCnpj,
                'custo_operacional' => $custoOperacional,
                'possui_estoque' => $possuiParsed['valor'],
                'id_estado' => (int) ($idEstado ?? 0),
            ],
            'erros' => $erros,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function idsEstadoPorNome(): array
    {
        return $this->idsEstadoPorNome ??= Estado::query()->pluck('id', 'nome')->all();
    }

    /**
     * @return array{valor: bool, erros: list<string>}
     */
    private function parsePossuiEstoquePlanilha(mixed $raw): array
    {
        if ($raw === null) {
            return [
                'valor' => false,
                'erros' => ['Possui estoque (coluna F) é obrigatório: use SIM ou NÃO.'],
            ];
        }

        $t = TextoCadastro::normalizarMaiusculas(trim((string) $raw));

        if ($t === '') {
            return [
                'valor' => false,
                'erros' => ['Possui estoque (coluna F) é obrigatório: use SIM ou NÃO.'],
            ];
        }

        if (in_array($t, ['1', 'S', 'SIM', 'Y', 'YES', 'TRUE', 'VERDADEIRO'], true)) {
            return ['valor' => true, 'erros' => []];
        }

        if (in_array($t, ['0', 'N', 'NÃO', 'NAO', 'NO', 'FALSE', 'FALSO'], true)) {
            return ['valor' => false, 'erros' => []];
        }

        return [
            'valor' => false,
            'erros' => ['Possui estoque (coluna F): valor inválido. Use SIM ou NÃO.'],
        ];
    }

    private function mensagemAmigavel(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Allowed memory size')) {
            return 'A planilha excedeu o limite de memória. Remova linhas vazias/formatação extra, salve novamente como .xlsx limpo e tente outra vez.';
        }

        if (str_contains($msg, 'Maximum execution time')) {
            return 'O processamento excedeu o tempo limite. Configure o worker de fila com `--timeout=900` e verifique se a planilha tem milhares de linhas vazias.';
        }

        return 'Falha ao processar a planilha: '.$msg;
    }

    /**
     * @param  list<array{row_id:int, linha:int, dados:array<string,mixed>}>  $buffer
     * @param  array<int, mixed>  $novas
     * @param  array<int, mixed>  $atualizacoes
     * @param  array<int, mixed>  $semAlteracoes
     * @param  array<int, mixed>  $erros
     */
    private function flushBuffer(array $buffer, array &$novas, array &$atualizacoes, array &$semAlteracoes, array &$erros): void
    {
        $idsCigam = [];
        foreach ($buffer as $item) {
            $id = $item['dados']['id_cigam'];
            if ($id !== '') {
                $idsCigam[] = $id;
            }
        }

        $existentes = UnidadeNegocio::query()
            ->whereIn('id_cigam', array_values(array_unique($idsCigam)))
            ->get()
            ->keyBy('id_cigam');

        foreach ($buffer as $item) {
            $rowId = $item['row_id'];
            $linha = $item['linha'];
            $dados = $item['dados'];
            $idCigam = $dados['id_cigam'];

            $existente = $existentes->get($idCigam);

            if ($existente === null) {
                $novas[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'dados' => $dados,
                ];

                continue;
            }

            $camposAlterados = $this->diffCampos($existente, $dados);

            if ($camposAlterados === []) {
                $semAlteracoes[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'unidade_negocio_id' => $existente->id,
                    'id_cigam' => $idCigam,
                    'nome' => $existente->nome,
                ];

                continue;
            }

            $atualizacoes[] = [
                'row_id' => $rowId,
                'unidade_negocio_id' => $existente->id,
                'id_cigam' => $idCigam,
                'linha' => $linha,
                'dados_atuais' => $this->snapshot($existente),
                'dados_novos' => $dados,
                'campos_alterados' => $camposAlterados,
            ];
        }
    }

    /**
     * @param  array<int, mixed>  $novas
     * @param  array<int, mixed>  $atualizacoes
     * @param  array<int, mixed>  $semAlteracoes
     * @param  array<int, mixed>  $erros
     */
    private function atualizarProgressoSeNecessario(
        UnidadeNegocioImportacao $importacao,
        int $linhasProcessadas,
        int $totalLinhas,
        array $novas,
        array $atualizacoes,
        array $semAlteracoes,
        array $erros,
    ): void {
        if ($linhasProcessadas % self::PROGRESSO_A_CADA_N_LINHAS !== 0) {
            return;
        }

        $this->salvarProgresso($importacao, $linhasProcessadas, $totalLinhas, $novas, $atualizacoes, $semAlteracoes, $erros);
    }

    /**
     * @param  array<int, mixed>  $novas
     * @param  array<int, mixed>  $atualizacoes
     * @param  array<int, mixed>  $semAlteracoes
     * @param  array<int, mixed>  $erros
     */
    private function salvarProgresso(
        UnidadeNegocioImportacao $importacao,
        int $linhasProcessadas,
        int $totalLinhas,
        array $novas,
        array $atualizacoes,
        array $semAlteracoes,
        array $erros,
    ): void {
        $percentual = $totalLinhas > 0
            ? (int) min(99, floor($linhasProcessadas * 100 / $totalLinhas))
            : 0;

        $importacao->forceFill([
            'linhas_processadas' => $linhasProcessadas,
            'percentual' => $percentual,
            'novas_count' => count($novas),
            'atualizacoes_count' => count($atualizacoes),
            'sem_alteracoes_count' => count($semAlteracoes),
            'erros_count' => count($erros),
        ])->save();
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function linhaVazia(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return list<array{campo:string, atual:mixed, novo:mixed}>
     */
    private function diffCampos(UnidadeNegocio $unidade, array $dados): array
    {
        $diff = [];
        $campos = ['razao_social', 'nome', 'cpf_cnpj', 'custo_operacional', 'possui_estoque', 'id_estado'];

        foreach ($campos as $campo) {
            if ($campo === 'custo_operacional') {
                $atual = number_format((float) $unidade->custo_operacional, 2, '.', '');
                $novo = number_format((float) ($dados[$campo] ?? 0), 2, '.', '');
            } elseif ($campo === 'possui_estoque') {
                $atual = (bool) $unidade->possui_estoque ? '1' : '0';
                $novo = ! empty($dados[$campo]) ? '1' : '0';
            } elseif ($campo === 'id_estado') {
                $atual = (string) (int) $unidade->id_estado;
                $novo = (string) (int) ($dados[$campo] ?? 0);
            } else {
                $atual = (string) ($unidade->{$campo} ?? '');
                $novo = (string) ($dados[$campo] ?? '');
            }

            if ($atual !== $novo) {
                $atualValor = match ($campo) {
                    'custo_operacional' => $atual,
                    'possui_estoque' => (bool) $unidade->possui_estoque,
                    'id_estado' => (int) $unidade->id_estado,
                    default => $unidade->{$campo},
                };

                $diff[] = [
                    'campo' => $campo,
                    'atual' => $atualValor,
                    'novo' => $dados[$campo] ?? null,
                ];
            }
        }

        return $diff;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(UnidadeNegocio $unidade): array
    {
        return [
            'id_cigam' => $unidade->id_cigam,
            'razao_social' => $unidade->razao_social,
            'nome' => $unidade->nome,
            'cpf_cnpj' => $unidade->cpf_cnpj,
            'custo_operacional' => number_format((float) $unidade->custo_operacional, 2, '.', ''),
            'possui_estoque' => (bool) $unidade->possui_estoque,
            'id_estado' => (int) $unidade->id_estado,
        ];
    }
}
