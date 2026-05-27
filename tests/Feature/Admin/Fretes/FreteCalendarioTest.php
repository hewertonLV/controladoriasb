<?php

namespace Tests\Feature\Admin\Fretes;

use App\Enums\Permissions;
use App\Models\Frete;
use Illuminate\Support\Carbon;

class FreteCalendarioTest extends FreteTestCase
{
    public function test_convidado_e_redirecionado_no_calendario(): void
    {
        $this->get(route('admin.fretes.calendario'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_no_calendario(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.fretes.calendario'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_calendario(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRETES_VISUALIZAR]))
            ->get(route('admin.fretes.calendario'))
            ->assertOk()
            ->assertSee('Calendário de Fretes', false)
            ->assertSee('id="fretes-calendario"', false);
    }

    public function test_eventos_retorna_fretes_do_mes_por_data_de_cadastro(): void
    {
        $mes = '2024-06';

        Frete::factory()->create([
            'nome' => 'FRETE JUNHO',
            'created_at' => Carbon::parse('2024-06-10 10:00:00'),
            'updated_at' => Carbon::parse('2024-06-10 10:00:00'),
        ]);

        Frete::factory()->create([
            'nome' => 'FRETE MAIO',
            'created_at' => Carbon::parse('2024-05-28 10:00:00'),
            'updated_at' => Carbon::parse('2024-05-28 10:00:00'),
        ]);

        $response = $this->actingAs($this->userWithPermissions([Permissions::FRETES_VISUALIZAR]))
            ->getJson(route('admin.fretes.calendario.eventos', ['mes' => $mes]));

        $response
            ->assertOk()
            ->assertJsonPath('mes', $mes)
            ->assertJsonPath('inicio', '2024-06-01')
            ->assertJsonPath('fim', '2024-06-30');

        $titulos = collect($response->json('eventos'))->pluck('title')->all();

        $this->assertContains('FRETE JUNHO', $titulos);
        $this->assertNotContains('FRETE MAIO', $titulos);
    }
}
