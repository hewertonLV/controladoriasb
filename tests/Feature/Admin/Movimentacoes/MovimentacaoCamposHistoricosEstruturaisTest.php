<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\FrutaUmIcms;
use App\Enums\Permissions;
use App\Http\Requests\Admin\Movimentacoes\StoreDescarteMovimentacaoRequest;
use App\Models\CategoriaDescarte;
use App\Models\Estado;
use App\Models\Fornecedor;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use App\Services\Frutas\FrutaIcmsSyncService;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use App\Services\Movimentacoes\MovimentacaoAuditoriaService;
use Database\Seeders\CategoriaDescarteSeeder;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class MovimentacaoCamposHistoricosEstruturaisTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_compra_preserva_icms_historico_apos_mudanca_de_icms_e_recalculo_de_frete(): void
    {
        $this->seedBase();
        $cenario = $this->cenarioCompraComIcms();

        $compraAntiga = $this->registrarCompra($cenario, '10', '500,00');
        $this->assertSame('20.00', (string) $compraAntiga->icms_convertido_kg);
        $this->assertSame('20.00', (string) $compraAntiga->valor_icms_kg);
        $this->assertSame('2000.00', (string) $compraAntiga->valor_icms_total);

        app(FrutaIcmsSyncService::class)->sync($cenario['fruta'], [
            Estado::ID_CEARA => [
                FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '100.00',
                FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '12.00',
            ],
        ]);

        $this->registrarCompra($cenario, '5', '250,00');

        $compraAntiga->refresh();
        $this->assertSame('20.00', (string) $compraAntiga->icms_convertido_kg);
        $this->assertSame('20.00', (string) $compraAntiga->valor_icms_kg);
        $this->assertSame('2000.00', (string) $compraAntiga->valor_icms_total);
        $this->assertSame('25.00', (string) $compraAntiga->preco_medio_fruta_kg);
    }

    public function test_replay_usa_icms_historico_e_incrementa_versao_replay(): void
    {
        $this->seedBase();
        $cenario = $this->cenarioCompraComIcms();

        $compraAntiga = $this->registrarCompra($cenario, '10', '500,00');
        $versaoReplayAntes = (int) $compraAntiga->versao_replay;

        app(FrutaIcmsSyncService::class)->sync($cenario['fruta'], [
            Estado::ID_CEARA => [
                FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '100.00',
                FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '12.00',
            ],
        ]);

        $compraNova = $this->registrarCompra($cenario, '5', '250,00');
        $this->cancelarCompraAdmin($compraNova);

        $compraAntiga->refresh();
        $this->assertSame('20.00', (string) $compraAntiga->icms_convertido_kg);
        $this->assertSame('20.00', (string) $compraAntiga->valor_icms_kg);
        $this->assertGreaterThan($versaoReplayAntes, (int) $compraAntiga->versao_replay);
        $this->assertSame('25.00', (string) $compraAntiga->preco_medio_fruta_kg);
    }

    public function test_categorias_descarte_sao_seedadas_com_ids_fixos(): void
    {
        $this->seed(CategoriaDescarteSeeder::class);

        $esperadas = [
            1 => 'AVARIA',
            2 => 'VENCIMENTO',
            3 => 'FUNGOS',
            4 => 'QUALIDADE',
            5 => 'TRANSPORTE',
            6 => 'QUEBRA',
            7 => 'CONTAMINACAO',
            8 => 'MADURACAO_EXCESSIVA',
            9 => 'PERDA_OPERACIONAL',
            10 => 'OUTROS',
        ];

        foreach ($esperadas as $id => $nome) {
            $categoria = CategoriaDescarte::query()->findOrFail($id);
            $this->assertSame($nome, $categoria->nome);
            $this->assertTrue($categoria->impacta_kpi_perda);
        }
    }

    public function test_descarte_exige_categoria_e_grava_relacionamento(): void
    {
        $this->seedBase();
        $cenario = $this->cenarioCompraComIcms();

        $validator = Validator::make([
            'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::Descarte->value,
            'id_fruta' => $cenario['fruta']->id,
            'qtd_fruta_kg' => '10.00',
        ], (new StoreDescarteMovimentacaoRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('categoria_descarte_id', $validator->errors()->toArray());

        $descarte = Movimentacao::factory()->create([
            'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::Descarte->value,
            'categoria_descarte_id' => CategoriaDescarte::ID_AVARIA,
            'motivo_descarte' => 'Avaria identificada no recebimento.',
        ]);

        $this->assertSame(CategoriaDescarte::ID_AVARIA, (int) $descarte->categoriaDescarte?->id);
        $this->assertSame('AVARIA', $descarte->categoriaDescarte?->nome);
    }

    public function test_auditoria_preserva_campos_historicos_novos(): void
    {
        $this->seedBase();

        $movimentacao = Movimentacao::factory()->create([
            'valor_icms_total' => '123.45',
            'valor_icms_kg' => '6.78',
            'valor_icms_um' => '67.80',
            'icms_convertido_kg' => '6.78',
            'versao_replay' => 4,
            'categoria_descarte_id' => CategoriaDescarte::ID_OUTROS,
            'motivo_descarte' => 'Perda operacional documentada.',
        ]);

        $snapshot = app(MovimentacaoAuditoriaService::class)->snapshotVersao($movimentacao);

        $this->assertSame('123.45', $snapshot['valor_icms_total']);
        $this->assertSame('6.78', $snapshot['valor_icms_kg']);
        $this->assertSame('67.80', $snapshot['valor_icms_um']);
        $this->assertSame('6.78', $snapshot['icms_convertido_kg']);
        $this->assertSame(4, $snapshot['versao_replay']);
        $this->assertSame(CategoriaDescarte::ID_OUTROS, $snapshot['categoria_descarte_id']);
        $this->assertSame('Perda operacional documentada.', $snapshot['motivo_descarte']);
    }

    private function seedBase(): void
    {
        $this->seed([
            EstadoSeeder::class,
            StatusMovimentacaoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
            CategoriaDescarteSeeder::class,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cenarioCompraComIcms(): array
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_CEARA,
            'custo_operacional' => 0,
        ]);

        HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->update(['status_position' => false]);

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => 0,
            'status_position' => true,
        ]);

        return [
            'empresa_fornecedor' => $fornecedor->registroCorporativo()->firstOrFail(),
            'empresa_unidade' => $unidade->registroCorporativo()->firstOrFail(),
            'fruta' => Fruta::factory()->comIcmsCeara([
                FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '20.00',
            ])->create([
                'kg_por_unidade_medicao' => 10,
            ]),
            'frete' => Frete::factory()->create([
                'valor' => '0.00',
                'status_situacao' => FreteStatusSituacao::ABERTA->value,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarCompra(array $cenario, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesComprasUsuario())->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $cenario['empresa_fornecedor']->id,
            'id_empresa_destino' => $cenario['empresa_unidade']->id,
            'id_fruta' => $cenario['fruta']->id,
            'qtd_fruta_um' => $qtdUm,
            'valor_nf_total' => $valorNfTotal,
            'id_frete' => $cenario['frete']->id,
        ])->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->where('id_frete', $cenario['frete']->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function cancelarCompraAdmin(Movimentacao $compra): void
    {
        $admin = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN]);

        $this->actingAs($admin)->postJson(route('admin.movimentacoes.compras.cancelar-admin', $compra), [
            'motivo' => 'Cancelamento administrativo para validar replay.',
        ])->assertOk();
    }
}
