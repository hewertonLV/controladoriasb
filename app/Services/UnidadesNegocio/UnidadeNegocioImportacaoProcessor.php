<?php

namespace App\Services\UnidadesNegocio;

use App\Models\Cliente;
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
 *  - F: possui_estoque — controla estoque de frutas (vazio = NÃO)
 *  - G: is_unidade_producao — fazenda (vazio = NÃO)
 *  - H: is_hub (vazio = NÃO)
 *  - I: is_galpao_operacional — galpão / centro resultado regional (vazio = NÃO)
 *  - J: emite_nota_fiscal — NF e faturamento na captação (vazio = NÃO)
 *  - K: estado (abreviação ou nome cadastrado, ex.: CE ou CEARA)
 *  - L: codigo_cliente — ID CIGAM do cliente principal (opcional; só em atualizações)
 *  - M: centro_armazenagem — 3 dígitos (opcional; vazio = 001 em novas; em atualização não altera)
 *
 * @see UnidadeNegocioImportacaoController::confirmar para persistência.
 */
class UnidadeNegocioImportacaoProcessor
{
    /**
     * @var array<string, int>|null
     */
    private ?array $idsEstadoPorBusca = null;

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
                $sheet->getCell('H'.$r)->getValue(),
                $sheet->getCell('I'.$r)->getValue(),
                $sheet->getCell('J'.$r)->getValue(),
                $sheet->getCell('K'.$r)->getValue(),
                $sheet->getCell('L'.$r)->getValue(),
                $sheet->getCell('M'.$r)->getValue(),
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
     * @return array{dados: array{id_cigam: string, razao_social: string, nome: string, cpf_cnpj: string|null, custo_operacional: string, possui_estoque: bool, is_unidade_producao: bool, is_hub: bool, is_galpao_operacional: bool, emite_nota_fiscal: bool, id_estado: int}, erros: list<string>}
     */
    private function normalizeRow(array $dadosBrutos): array
    {
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) ($dadosBrutos[0] ?? ''));
        $razaoSocial = TextoCadastro::normalizarMaiusculas((string) ($dadosBrutos[1] ?? ''));
        $nome = TextoCadastro::normalizarMaiusculas((string) ($dadosBrutos[2] ?? ''));
        $cpfCnpj = TextoCadastro::somenteDigitos((string) ($dadosBrutos[3] ?? ''));
        $custoOperacional = TextoCadastro::normalizarValorMonetarioBrasileiro($dadosBrutos[4] ?? '0');

        $possuiParsed = $this->parseSimNaoPlanilha($dadosBrutos[5] ?? null, 'Controle estoque de frutas', 'F');
        $producaoParsed = $this->parseSimNaoPlanilha($dadosBrutos[6] ?? null, 'Unidade de produção', 'G');
        $hubParsed = $this->parseSimNaoPlanilha($dadosBrutos[7] ?? null, 'Unidade HUB', 'H');
        $galpaoParsed = $this->parseSimNaoPlanilha($dadosBrutos[8] ?? null, 'Galpão operacional', 'I');
        $emiteNfParsed = $this->parseSimNaoPlanilha($dadosBrutos[9] ?? null, 'Emite nota fiscal', 'J');

        $estadoBusca = TextoCadastro::normalizarBuscaEstado(trim((string) ($dadosBrutos[10] ?? '')));
        $idEstado = $estadoBusca === '' ? null : ($this->idsEstadoPorBusca()[$estadoBusca] ?? null);

        $codigoClienteBruto = trim((string) ($dadosBrutos[11] ?? ''));
        $codigoCliente = $codigoClienteBruto === ''
            ? null
            : TextoCadastro::normalizarIdCigamAteSeisDigitos($codigoClienteBruto);

        $centroArmazenagemBruto = trim((string) ($dadosBrutos[12] ?? ''));
        $centroArmazenagem = $centroArmazenagemBruto === ''
            ? null
            : str_pad(
                substr(TextoCadastro::somenteDigitos($centroArmazenagemBruto), 0, 3),
                3,
                '0',
                STR_PAD_LEFT,
            );

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

        if ($cpfCnpj !== '' && ! in_array(strlen($cpfCnpj), [11, 14], true)) {
            $erros[] = 'CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).';
        }

        $erros = array_merge(
            $erros,
            $possuiParsed['erros'],
            $producaoParsed['erros'],
            $hubParsed['erros'],
            $galpaoParsed['erros'],
            $emiteNfParsed['erros'],
        );

        if ($estadoBusca === '') {
            $erros[] = 'Estado (coluna K) é obrigatório. Informe a abreviação ou o nome cadastrado (ex.: CE ou CEARA).';
        } elseif ($idEstado === null) {
            $erros[] = 'Estado inválido na coluna K. Valores aceitos: '.implode(', ', array_keys($this->idsEstadoPorBusca())).'.';
        }

        if ($codigoCliente !== null && $codigoCliente !== '' && ! preg_match('/^\d{6}$/', $codigoCliente)) {
            $erros[] = 'Código do cliente (coluna L) deve ter no máximo 6 dígitos numéricos.';
        }

        if ($centroArmazenagem !== null && ! preg_match('/^\d{3}$/', $centroArmazenagem)) {
            $erros[] = 'Centro de armazenagem (coluna M) deve ter até 3 dígitos numéricos.';
        }

        $erros = array_merge($erros, $this->errosCombinacaoFlags(
            $possuiParsed['valor'],
            $hubParsed['valor'],
            $galpaoParsed['valor'],
            $emiteNfParsed['valor'],
        ));

        return [
            'dados' => [
                'id_cigam' => $idCigam,
                'razao_social' => $razaoSocial,
                'nome' => $nome,
                'cpf_cnpj' => $cpfCnpj === '' ? null : $cpfCnpj,
                'custo_operacional' => $custoOperacional,
                'possui_estoque' => $possuiParsed['valor'],
                'is_unidade_producao' => $producaoParsed['valor'],
                'is_hub' => $hubParsed['valor'],
                'is_galpao_operacional' => $galpaoParsed['valor'],
                'emite_nota_fiscal' => $emiteNfParsed['valor'],
                'id_estado' => (int) ($idEstado ?? 0),
                'codigo_cliente' => $codigoCliente,
                'centro_armazenagem' => $centroArmazenagem,
            ],
            'erros' => $erros,
        ];
    }

    /**
     * @return list<string>
     */
    private function errosCombinacaoFlags(
        bool $possuiEstoque,
        bool $isHub,
        bool $isGalpao,
        bool $emiteNotaFiscal,
    ): array {
        $erros = [];

        if ($isGalpao && ! $possuiEstoque) {
            $erros[] = 'Galpão operacional (coluna I) exige Controle estoque de frutas = SIM (coluna F).';
        }

        if ($isHub && $emiteNotaFiscal) {
            $erros[] = 'Unidade HUB (coluna H) não pode ter Emite nota fiscal = SIM (coluna J).';
        }

        return $erros;
    }

    /**
     * @return array<string, int>
     */
    private function idsEstadoPorBusca(): array
    {
        if ($this->idsEstadoPorBusca !== null) {
            return $this->idsEstadoPorBusca;
        }

        $map = [];
        $estados = Estado::query()->get(['id', 'nome', 'abreviacao']);

        foreach ($estados as $estado) {
            $abreviacao = Estado::normalizarChaveBusca($estado->abreviacao);
            if ($abreviacao !== '') {
                $map[$abreviacao] = (int) $estado->id;
            }
        }

        foreach ($estados as $estado) {
            $nome = Estado::normalizarChaveBusca($estado->nome);
            if ($nome !== '' && ! isset($map[$nome])) {
                $map[$nome] = (int) $estado->id;
            }
        }

        return $this->idsEstadoPorBusca = $map;
    }

    /**
     * Célula vazia → NÃO (false). Valores aceitos: SIM/NÃO, S/N, 1/0, VERDADEIRO/FALSO.
     *
     * @return array{valor: bool, erros: list<string>}
     */
    private function parseSimNaoPlanilha(mixed $raw, string $rotulo, string $coluna): array
    {
        if ($raw === null) {
            return ['valor' => false, 'erros' => []];
        }

        $t = TextoCadastro::normalizarMaiusculas(trim((string) $raw));

        if ($t === '') {
            return ['valor' => false, 'erros' => []];
        }

        if (in_array($t, ['1', 'S', 'SIM', 'Y', 'YES', 'TRUE', 'VERDADEIRO'], true)) {
            return ['valor' => true, 'erros' => []];
        }

        if (in_array($t, ['0', 'N', 'NÃO', 'NAO', 'NO', 'FALSE', 'FALSO'], true)) {
            return ['valor' => false, 'erros' => []];
        }

        return [
            'valor' => false,
            'erros' => ["{$rotulo} (coluna {$coluna}): valor inválido. Use SIM ou NÃO."],
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
                unset($dados['codigo_cliente']);
                $dados['centro_armazenagem'] = $dados['centro_armazenagem'] ?? '001';
                $novas[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'dados' => $dados,
                ];

                continue;
            }

            [$dados, $errosCliente] = $this->aplicarCodigoClienteNaUnidade($dados, $existente);
            $dados = $this->aplicarCentroArmazenagemNaUnidade($dados, $existente);
            if ($errosCliente !== []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'id_cigam' => $idCigam,
                    'erros' => $errosCliente,
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
        $campos = [
            'razao_social',
            'nome',
            'cpf_cnpj',
            'custo_operacional',
            'possui_estoque',
            'is_unidade_producao',
            'is_hub',
            'is_galpao_operacional',
            'emite_nota_fiscal',
            'id_estado',
            'id_cliente',
            'centro_armazenagem',
        ];

        foreach ($campos as $campo) {
            if ($campo === 'custo_operacional') {
                $atual = number_format((float) $unidade->custo_operacional, 2, '.', '');
                $novo = number_format((float) ($dados[$campo] ?? 0), 2, '.', '');
            } elseif (in_array($campo, [
                'possui_estoque',
                'is_unidade_producao',
                'is_hub',
                'is_galpao_operacional',
                'emite_nota_fiscal',
            ], true)) {
                $atual = (bool) $unidade->{$campo} ? '1' : '0';
                $novo = ! empty($dados[$campo]) ? '1' : '0';
            } elseif ($campo === 'id_estado') {
                $atual = (string) (int) $unidade->id_estado;
                $novo = (string) (int) ($dados[$campo] ?? 0);
            } elseif ($campo === 'id_cliente') {
                $atual = (string) (int) ($unidade->id_cliente ?? 0);
                $novo = array_key_exists('id_cliente', $dados)
                    ? (string) (int) ($dados['id_cliente'] ?? 0)
                    : $atual;
            } elseif ($campo === 'centro_armazenagem') {
                $atual = (string) ($unidade->centro_armazenagem ?? '001');
                $novo = array_key_exists('centro_armazenagem', $dados)
                    ? (string) ($dados['centro_armazenagem'] ?? $atual)
                    : $atual;
            } else {
                $atual = (string) ($unidade->{$campo} ?? '');
                $novo = (string) ($dados[$campo] ?? '');
            }

            if ($atual !== $novo) {
                $atualValor = match ($campo) {
                    'custo_operacional' => $atual,
                    'possui_estoque', 'is_unidade_producao', 'is_hub', 'is_galpao_operacional', 'emite_nota_fiscal' => (bool) $unidade->{$campo},
                    'id_estado' => (int) $unidade->id_estado,
                    'id_cliente' => $unidade->id_cliente,
                    'centro_armazenagem' => $unidade->centro_armazenagem,
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
            'is_unidade_producao' => (bool) $unidade->is_unidade_producao,
            'is_hub' => (bool) $unidade->is_hub,
            'is_galpao_operacional' => (bool) $unidade->is_galpao_operacional,
            'emite_nota_fiscal' => (bool) $unidade->emite_nota_fiscal,
            'id_estado' => (int) $unidade->id_estado,
            'id_cliente' => $unidade->id_cliente,
            'centro_armazenagem' => $unidade->centro_armazenagem,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function aplicarCentroArmazenagemNaUnidade(array $dados, UnidadeNegocio $unidade): array
    {
        if (! array_key_exists('centro_armazenagem', $dados) || $dados['centro_armazenagem'] === null) {
            unset($dados['centro_armazenagem']);

            return $dados;
        }

        return $dados;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function aplicarCodigoClienteNaUnidade(array $dados, UnidadeNegocio $unidade): array
    {
        if (! array_key_exists('codigo_cliente', $dados)) {
            return [$dados, []];
        }

        $codigoCliente = $dados['codigo_cliente'];
        unset($dados['codigo_cliente']);

        if ($codigoCliente === null || $codigoCliente === '') {
            return [$dados, []];
        }

        $cliente = Cliente::query()->where('id_cigam', $codigoCliente)->first();
        if ($cliente === null) {
            return [$dados, ["Código do cliente {$codigoCliente} (coluna L) não encontrado no cadastro."]];
        }

        if ((int) $cliente->id_unidade_negocio !== (int) $unidade->id) {
            return [$dados, ["O cliente {$codigoCliente} não pertence à unidade de negócio {$unidade->id_cigam}."]];
        }

        $outraUnidade = UnidadeNegocio::query()
            ->where('id_cliente', $cliente->id)
            ->where('id', '!=', $unidade->id)
            ->exists();
        if ($outraUnidade) {
            return [$dados, ["O cliente {$codigoCliente} já é o cliente principal de outra unidade de negócio."]];
        }

        $dados['id_cliente'] = $cliente->id;

        return [$dados, []];
    }
}
