<?php

namespace Tests\Feature;

use App\Models\Estado;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EstadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_estado_seeder_nao_duplica_estados(): void
    {
        $this->seed(EstadoSeeder::class);
        $this->seed(EstadoSeeder::class);

        $this->assertSame(13, Estado::query()->count());
        $this->assertSame(13, Estado::query()->distinct('abreviacao')->count('abreviacao'));
    }

    public function test_estado_seeder_preserva_ids_fixos(): void
    {
        $this->seed(EstadoSeeder::class);

        $this->assertDatabaseHas('estados', [
            'id' => Estado::ID_CEARA,
            'nome' => 'CEARA',
            'abreviacao' => 'CE',
        ]);
        $this->assertDatabaseHas('estados', [
            'id' => Estado::ID_PERNAMBUCO,
            'nome' => 'PERNAMBUCO',
            'abreviacao' => 'PE',
        ]);
        $this->assertDatabaseHas('estados', [
            'id' => Estado::ID_ALAGOAS,
            'nome' => 'ALAGOAS',
            'abreviacao' => 'AL',
        ]);
    }

    public function test_busca_estado_por_abreviacao(): void
    {
        $this->assertSame(Estado::ID_CEARA, Estado::buscarPorAbreviacaoOuNome('CE')?->id);
    }

    public function test_busca_estado_por_nome(): void
    {
        $this->assertSame(Estado::ID_CEARA, Estado::buscarPorAbreviacaoOuNome('CEARA')?->id);
    }

    public function test_busca_estado_funciona_com_lowercase(): void
    {
        $this->assertSame(Estado::ID_CEARA, Estado::buscarPorAbreviacaoOuNome('ce')?->id);
    }

    public function test_busca_estado_funciona_com_espacos_e_acentos(): void
    {
        $this->assertSame(Estado::ID_CEARA, Estado::buscarPorAbreviacaoOuNome('  C e  ')?->id);
        $this->assertSame(Estado::ID_CEARA, Estado::buscarPorAbreviacaoOuNome(' Ceará ')?->id);
    }

    public function test_abreviacao_duplicada_nao_e_permitida(): void
    {
        $this->expectException(ValidationException::class);

        Estado::factory()->create([
            'nome' => 'CEARA DUPLICADO',
            'abreviacao' => ' ce ',
        ]);
    }
}
