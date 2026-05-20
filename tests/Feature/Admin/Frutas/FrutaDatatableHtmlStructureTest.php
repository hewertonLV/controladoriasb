<?php

namespace Tests\Feature\Admin\Frutas;

use App\Models\Fruta;

class FrutaDatatableHtmlStructureTest extends FrutaTestCase
{
    public function test_listagem_html_tem_uma_unica_tabela_datatable_e_scripts(): void
    {
        Fruta::factory()->count(30)->create();
        Fruta::factory()->create(['nome' => 'ZZZHTMLUNICO']);

        $html = $this->actingAs($this->frutasManager())
            ->get(route('admin.frutas.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match_all('/<table[^>]*\sid="frutas-datatable"/', $html));
        $this->assertSame(1, substr_count($html, 'data-admin-datatable'));
        $this->assertSame(1, substr_count($html, 'assets/js/admin-datatable.js'));
        $this->assertSame(1, substr_count($html, 'jquery.dataTables.min.js'));
        $this->assertGreaterThanOrEqual(31, substr_count($html, '</tr>'));
        $this->assertStringContainsString('ZZZHTMLUNICO', $html);
    }

    public function test_listagem_html_pode_ser_diagnosticada_em_arquivo_estatico(): void
    {
        Fruta::factory()->count(40)->create();
        Fruta::factory()->create(['nome' => 'ZZZE2EPLAYWRIGHT']);

        $html = $this->actingAs($this->frutasManager())
            ->get(route('admin.frutas.index'))
            ->assertOk()
            ->getContent();

        $path = public_path('diagnostics/frutas-rendered.html');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $html = str_replace($baseUrl, '', $html);
        $html = str_replace(
            '</body>',
            "<script>window.DEBUG_ADMIN_DATATABLE=true;</script>\n</body>",
            $html,
        );

        file_put_contents($path, $html);

        $this->assertFileExists($path);
        $this->assertStringContainsString('ZZZE2EPLAYWRIGHT', file_get_contents($path));
        $this->assertStringContainsString('data-admin-datatable', file_get_contents($path));
    }
}
