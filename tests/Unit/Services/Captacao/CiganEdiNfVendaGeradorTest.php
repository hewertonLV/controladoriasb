<?php

namespace Tests\Unit\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use App\Models\Captacao\PedidoItem;
use App\Models\Cliente;
use App\Models\Estado;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\CiganEdiNfTransferenciaGerador;
use App\Services\Captacao\CiganEdiNfVendaGerador;
use App\Services\Captacao\GerarArquivoCiganService;
use App\Support\Captacao\Cigan\CiganEdiEncoding;
use Illuminate\Validation\ValidationException;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CiganEdiNfVendaGeradorTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_n_e_i_por_loja_com_origem_faturamento_e_destino_loja(): void
    {
        $this->seed(EstadoSeeder::class);

        $estado = Estado::query()->firstOrFail();

        $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create();

        $faturamento = UnidadeNegocio::factory()->create([
            'id_cigam' => '881001',
            'centro_armazenagem' => '001',
            'emite_nota_fiscal' => true,
        ]);

        $loja = Cliente::factory()->create([
            'id_cigam' => '770055',
            'numero_divisao' => '08',
            'cnpj_cpf' => '12345678000199',
            'fantasia' => null,
            'razao_social' => 'LOJA VENDA TESTE',
        ]);

        $unidadeLoja = UnidadeNegocio::factory()->create([
            'id_estado' => $estado->id,
        ]);
        $loja->update(['id_unidade_negocio' => $unidadeLoja->id]);

        $fruta = Fruta::factory()->create(['id_cigam' => '990011', 'nome' => 'BANANA']);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $faturamento->id,
            'id_unidade_negocio_galpao' => $galpao->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => 'FATURAMENTO_CIGAN_INICIADO',
        ]);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $loja->id,
            'captacao_concluida' => true,
            'origem' => 'WEB',
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $fruta->id,
            'quantidade' => '5.00',
            'preco_venda' => '5.50',
            'version' => 1,
        ]);

        $campos = app(CiganEdiNfTransferenciaGerador::class);
        $txt = app(CiganEdiNfVendaGerador::class)->gerar($lote->fresh(['pedidos.cliente', 'pedidos.itens.fruta']));

        $linhas = array_values(array_filter(explode("\n", trim($txt))));

        $this->assertCount(2, $linhas);
        $this->assertSame('N', substr($linhas[0], 0, 1));
        $this->assertSame('I', substr($linhas[1], 0, 1));
        $this->assertSame('NF881', substr($linhas[0], 2, 5));
        $this->assertSame('001    ', substr($linhas[0], 8, 7));
        $this->assertStringContainsString('770055', $linhas[0]);
        $this->assertStringContainsString(
            mb_strtoupper(mb_substr($loja->fresh()->razao_social, 0, 20)),
            $linhas[0],
        );
        $this->assertSame(str_repeat(' ', 5), substr($linhas[0], 19, 5));
        $this->assertSame(str_repeat(' ', 5), substr($linhas[1], 371, 5));
        $this->assertSame('001', substr($linhas[0], 604, 3));
        $this->assertSame(
            $campos->formatarPrecoUnitarioCigam(5.5),
            substr($linhas[1], 55, 15),
            'Preço unitário (pos. 56–70) = preco_venda da matriz (N10.5)',
        );
    }

    public function test_para_iso88591_ignora_caracteres_ilegais_sem_lancar_erro(): void
    {
        $entrada = "MAÇÃ — ESPECIAL \xEF\xBF\xBD emoji 🍌";
        $saida = CiganEdiEncoding::paraIso88591($entrada);

        $this->assertNotSame('', $saida);
        $this->assertTrue(mb_check_encoding($saida, 'ISO-8859-1') || $saida !== '');
    }

    public function test_gera_vendas_com_nome_fruta_com_caracteres_especiais(): void
    {
        $this->seed(EstadoSeeder::class);

        $estado = Estado::query()->firstOrFail();
        $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create();
        $faturamento = UnidadeNegocio::factory()->create([
            'id_cigam' => '881001',
            'centro_armazenagem' => '001',
        ]);
        $loja = Cliente::factory()->create([
            'id_cigam' => '770055',
            'cnpj_cpf' => '12345678000199',
            'razao_social' => 'LOJA — TESTE',
        ]);
        $unidadeLoja = UnidadeNegocio::factory()->create(['id_estado' => $estado->id]);
        $loja->update(['id_unidade_negocio' => $unidadeLoja->id]);
        $fruta = Fruta::factory()->create(['id_cigam' => '990011', 'nome' => 'BANANA — PRATA 🍌']);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $faturamento->id,
            'id_unidade_negocio_galpao' => $galpao->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => 'FATURAMENTO_CIGAN_INICIADO',
        ]);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $loja->id,
            'captacao_concluida' => true,
            'origem' => 'WEB',
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $fruta->id,
            'quantidade' => '2.00',
            'preco_venda' => '10.00',
            'version' => 1,
        ]);

        $conteudo = app(GerarArquivoCiganService::class)->conteudoTxtVendas($lote->fresh(['pedidos.cliente', 'pedidos.itens.fruta']));
        $linhas = array_values(array_filter(explode("\n", trim($conteudo))));

        $this->assertCount(2, $linhas);
        $this->assertSame('N', substr($linhas[0], 0, 1));
        $this->assertSame('I', substr($linhas[1], 0, 1));
        $this->assertSame('001', substr($linhas[0], 604, 3));
    }

    public function test_geracao_vendas_exige_preco_venda_no_item(): void
    {
        $this->seed(EstadoSeeder::class);

        $estado = Estado::query()->firstOrFail();
        $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create();
        $faturamento = UnidadeNegocio::factory()->create([
            'id_cigam' => '881001',
            'centro_armazenagem' => '001',
        ]);
        $loja = Cliente::factory()->create(['id_cigam' => '770055', 'cnpj_cpf' => '12345678000199']);
        $unidadeLoja = UnidadeNegocio::factory()->create(['id_estado' => $estado->id]);
        $loja->update(['id_unidade_negocio' => $unidadeLoja->id]);
        $fruta = Fruta::factory()->create(['id_cigam' => '990011', 'nome' => 'BANANA']);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $faturamento->id,
            'id_unidade_negocio_galpao' => $galpao->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => 'FATURAMENTO_CIGAN_INICIADO',
        ]);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $loja->id,
            'captacao_concluida' => true,
            'origem' => 'WEB',
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $fruta->id,
            'quantidade' => '3.00',
            'version' => 1,
        ]);

        $this->expectException(ValidationException::class);

        app(CiganEdiNfVendaGerador::class)->gerar($lote->fresh(['pedidos.itens.fruta', 'pedidos.cliente']));
    }
}
