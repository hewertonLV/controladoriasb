<?php

namespace Database\Seeders;

use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\ClienteFrutaVinculo;
use App\Models\Cliente;
use App\Services\Captacao\ClienteFrutaVinculoService;
use App\Support\TextoCadastro;
use Illuminate\Database\Seeder;

/**
 * Propaga os vínculos fruta×loja do cliente origem (Cigam 005134 — ALIMENTOS SOFRIOS)
 * para as demais lojas da carteira Cariri 01, sem remover vínculos já existentes.
 *
 * Uso: php artisan db:seed --class=ClienteFrutaVinculoCariri01Seeder
 */
class ClienteFrutaVinculoCariri01Seeder extends Seeder
{
    private const CARTEIRA_NOME = 'Cariri 01';

    private const CLIENTE_ORIGEM_ID_CIGAM = '005134';

    public function run(): void
    {
        $carteira = CaptacaoCarteira::query()
            ->where('nome', self::CARTEIRA_NOME)
            ->where('ativo', true)
            ->first();

        if ($carteira === null) {
            $this->command?->warn('Carteira «'.self::CARTEIRA_NOME.'» não encontrada ou inativa; seeder ignorado.');

            return;
        }

        $idCigamOrigem = TextoCadastro::normalizarIdCigamAteSeisDigitos(self::CLIENTE_ORIGEM_ID_CIGAM);

        $clienteOrigem = Cliente::query()
            ->where('id_cigam', $idCigamOrigem)
            ->first();

        if ($clienteOrigem === null) {
            $this->command?->warn('Cliente origem Cigam «'.$idCigamOrigem.'» não encontrado; seeder ignorado.');

            return;
        }

        if ((int) $clienteOrigem->id_captacao_carteira !== (int) $carteira->id) {
            $this->command?->warn(sprintf(
                'Cliente origem %s (%s) não pertence à carteira «%s»; vínculos serão copiados mesmo assim.',
                $idCigamOrigem,
                $clienteOrigem->fantasia ?: $clienteOrigem->razao_social,
                self::CARTEIRA_NOME,
            ));
        }

        $idFrutasOrigem = ClienteFrutaVinculo::query()
            ->where('id_cliente', $clienteOrigem->id)
            ->where('ativo', true)
            ->pluck('id_fruta')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($idFrutasOrigem === []) {
            $this->command?->warn('Cliente origem sem frutas vinculadas; seeder ignorado.');

            return;
        }

        $destinos = Cliente::query()
            ->where('id_captacao_carteira', $carteira->id)
            ->where('id', '!=', $clienteOrigem->id)
            ->orderBy('razao_social')
            ->get(['id', 'razao_social', 'fantasia', 'id_cigam']);

        if ($destinos->isEmpty()) {
            $this->command?->warn('Nenhuma outra loja na carteira «'.self::CARTEIRA_NOME.'»; seeder ignorado.');

            return;
        }

        $vinculos = app(ClienteFrutaVinculoService::class);
        $totalNovos = 0;

        foreach ($destinos as $destino) {
            $antes = ClienteFrutaVinculo::query()
                ->where('id_cliente', $destino->id)
                ->where('ativo', true)
                ->count();

            $vinculos->adicionarFrutas($destino, $idFrutasOrigem);

            $depois = ClienteFrutaVinculo::query()
                ->where('id_cliente', $destino->id)
                ->where('ativo', true)
                ->count();

            $novos = max(0, $depois - $antes);
            $totalNovos += $novos;

            $this->command?->line(sprintf(
                '  %s (%s): %d fruta(s) no total (%d nova(s)).',
                $destino->id_cigam,
                $destino->fantasia ?: $destino->razao_social,
                $depois,
                $novos,
            ));
        }

        $this->command?->info(sprintf(
            'Concluído: %d fruta(s) de referência (Cigam %s) aplicada(s) a %d loja(s) da carteira «%s» (%d vínculo(s) novo(s)).',
            count($idFrutasOrigem),
            $idCigamOrigem,
            $destinos->count(),
            self::CARTEIRA_NOME,
            $totalNovos,
        ));
    }
}
