<?php

namespace App\Console\Commands;

use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Services\Captacao\CaptacaoDemandaTransferenciaRotaService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class ReverterMovimentacaoTransferenciaCaptacaoAutomaticaCommand extends Command
{
    protected $signature = 'captacao:reverter-movimentacao-transferencia-automatica
                            {demanda : ID em captacao_lote_movimentacoes}
                            {--dry-run : Apenas exibe o que seria revertido}';

    protected $description = 'Cancela transferência SB indevida em demanda automática de captação (ADR-0168)';

    public function handle(CaptacaoDemandaTransferenciaRotaService $service): int
    {
        $demanda = CaptacaoLoteMovimentacao::query()->find($this->argument('demanda'));

        if ($demanda === null) {
            $this->error('Demanda não encontrada.');

            return self::FAILURE;
        }

        if ($demanda->tipo !== CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA) {
            $this->error('Registro não é demanda de transferência.');

            return self::FAILURE;
        }

        if ($demanda->id_captacao_rota === null) {
            $this->error('Demanda não é automática da rota (id_captacao_rota vazio).');

            return self::FAILURE;
        }

        $anchor = (int) ($demanda->transferencia_origem_id ?? 0);

        if ($anchor <= 0) {
            $this->info('Demanda sem transferencia_origem_id — nada a reverter.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry-run: cancelaria transferência SB #{$anchor} e limparia transferencia_origem_id da demanda #{$demanda->id}.");

            return self::SUCCESS;
        }

        try {
            $service->reverterMovimentacaoSbIndevida($demanda);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info("Transferência SB revertida. Demanda #{$demanda->id} sem vínculo de movimentação.");

        return self::SUCCESS;
    }
}
