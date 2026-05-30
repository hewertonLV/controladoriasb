<?php

namespace Tests\Unit\Support\Captacao\Seed;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\CaptacaoLote;
use App\Models\UnidadeNegocio;
use App\Support\Captacao\Seed\CaptacaoBarbalhaExemploSeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class CaptacaoBarbalhaExemploSeedServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reutiliza_lote_quando_captacao_em_andamento(): void
    {
        [$carteira, $data] = $this->carteiraBarbalhaComData();

        $loteEmAndamento = CaptacaoLote::query()->create([
            'data_referencia' => $data,
            'id_captacao_carteira' => $carteira->id,
            'id_unidade_negocio_faturamento' => $carteira->id_unidade_negocio_faturamento,
            'id_unidade_negocio_galpao' => $carteira->id_unidade_negocio_galpao,
            'tipo' => CaptacaoLoteTipo::CaptacaoPedidos->value,
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento->value,
        ]);

        $loteResolvido = $this->invocarGarantirLoteDoDia($data, $carteira);

        $this->assertSame($loteEmAndamento->id, $loteResolvido->id);
        $this->assertSame(
            1,
            CaptacaoLote::query()
                ->whereDate('data_referencia', $data)
                ->where('id_captacao_carteira', $carteira->id)
                ->count(),
        );
    }

    public function test_cria_novo_lote_quando_existe_lote_fora_de_captacao_em_andamento(): void
    {
        [$carteira, $data] = $this->carteiraBarbalhaComData();

        $loteAnterior = CaptacaoLote::query()->create([
            'data_referencia' => $data,
            'id_captacao_carteira' => $carteira->id,
            'id_unidade_negocio_faturamento' => $carteira->id_unidade_negocio_faturamento,
            'id_unidade_negocio_galpao' => $carteira->id_unidade_negocio_galpao,
            'tipo' => CaptacaoLoteTipo::CaptacaoPedidos->value,
            'status' => CaptacaoLoteStatus::CaptacaoConcluida->value,
        ]);

        $loteResolvido = $this->invocarGarantirLoteDoDia($data, $carteira);

        $this->assertNotSame($loteAnterior->id, $loteResolvido->id);
        $this->assertSame(CaptacaoLoteStatus::CaptacaoEmAndamento, $loteResolvido->status);

        $lotes = CaptacaoLote::query()
            ->whereDate('data_referencia', $data)
            ->where('id_captacao_carteira', $carteira->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $lotes);
        $this->assertSame(CaptacaoLoteStatus::CaptacaoConcluida, $lotes[0]->status);
        $this->assertSame(CaptacaoLoteStatus::CaptacaoEmAndamento, $lotes[1]->status);
    }

    /**
     * @return array{0: CaptacaoCarteira, 1: string}
     */
    private function carteiraBarbalhaComData(): array
    {
        $faturamento = UnidadeNegocio::factory()->create([
            'nome' => 'CD BARBALHA',
            'emite_nota_fiscal' => true,
        ]);

        $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create([
            'nome' => 'CD BARBALHA',
        ]);

        $carteira = CaptacaoCarteira::query()->create([
            'nome' => 'Barbalha',
            'id_unidade_negocio_faturamento' => $faturamento->id,
            'id_unidade_negocio_galpao' => $galpao->id,
            'ativo' => true,
        ]);

        return [$carteira, '2026-05-29'];
    }

    private function invocarGarantirLoteDoDia(string $dataReferencia, CaptacaoCarteira $carteira): CaptacaoLote
    {
        $service = app(CaptacaoBarbalhaExemploSeedService::class);
        $method = new ReflectionMethod($service, 'garantirLoteDoDia');
        $method->setAccessible(true);

        /** @var CaptacaoLote $lote */
        $lote = $method->invoke($service, $dataReferencia, $carteira, null);

        return $lote;
    }
}
