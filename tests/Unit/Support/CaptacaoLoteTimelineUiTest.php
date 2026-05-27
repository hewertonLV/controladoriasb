<?php

namespace Tests\Unit\Support;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Support\Captacao\CaptacaoLoteTimelineUi;
use PHPUnit\Framework\TestCase;

class CaptacaoLoteTimelineUiTest extends TestCase
{
    public function test_captacao_pedidos_marca_passos_anteriores_como_concluidos(): void
    {
        $lote = new CaptacaoLote([
            'tipo' => CaptacaoLoteTipo::CaptacaoPedidos,
            'status' => CaptacaoLoteStatus::TransferenciaCiganIniciada,
        ]);

        $passos = CaptacaoLoteTimelineUi::passos($lote);

        $this->assertCount(9, $passos);
        $this->assertSame('concluido', $passos[0]['estado']);
        $this->assertSame('concluido', $passos[1]['estado']);
        $this->assertSame('atual', $passos[2]['estado']);
        $this->assertSame('pendente', $passos[3]['estado']);
        $descricao = CaptacaoLoteTimelineUi::descricaoAtual($lote);
        $this->assertStringContainsString('HUB', $descricao);
        $this->assertStringContainsString('Cigam', $descricao);
        $this->assertStringNotContainsString('Lucas', $descricao);
    }

    public function test_faturamento_cigan_iniciado_descreve_aguardando_sem_nomes_e_preco_travado(): void
    {
        $lote = new CaptacaoLote([
            'tipo' => CaptacaoLoteTipo::CaptacaoPedidos,
            'status' => CaptacaoLoteStatus::FaturamentoCiganIniciado,
        ]);

        $descricao = CaptacaoLoteTimelineUi::descricaoAtual($lote);

        $this->assertStringContainsString('Arquivo Cigam Venda', $descricao);
        $this->assertStringContainsString('NF', $descricao);
        $this->assertStringContainsString('movimentações', $descricao);
        $this->assertStringContainsString('travados', $descricao);
        $this->assertStringNotContainsString('Jefferson', $descricao);
        $this->assertStringNotContainsString('Lucas', $descricao);
    }

    public function test_romaneio_manual_tem_sequencia_reduzida(): void
    {
        $lote = new CaptacaoLote([
            'tipo' => CaptacaoLoteTipo::RomaneioManual,
            'status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan,
        ]);

        $passos = CaptacaoLoteTimelineUi::passos($lote);

        $this->assertCount(4, $passos);
        $this->assertSame('atual', $passos[1]['estado']);
        $this->assertSame('pendente', $passos[2]['estado']);
    }
}
