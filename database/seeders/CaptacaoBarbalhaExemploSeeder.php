<?php

namespace Database\Seeders;

use App\Support\Captacao\Seed\CaptacaoBarbalhaExemploSeedService;
use Illuminate\Database\Seeder;

/**
 * Cria ou atualiza a captação do dia (data atual) da carteira Barbalha
 * a partir de planilhas/captação exemplo.xlsx.
 *
 * Uso: php artisan db:seed --class=CaptacaoBarbalhaExemploSeeder
 */
class CaptacaoBarbalhaExemploSeeder extends Seeder
{
    public function run(): void
    {
        app(CaptacaoBarbalhaExemploSeedService::class)->executar(
            output: $this->command,
        );
    }
}
