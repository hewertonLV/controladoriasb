<?php

namespace Tests\Unit\Config;

use Carbon\Carbon;
use Tests\TestCase;

class AppTimezoneTest extends TestCase
{
    public function test_application_uses_brasilia_timezone(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
    }

    public function test_now_matches_brasilia_offset(): void
    {
        $now = Carbon::now();
        $brasilia = Carbon::now('America/Sao_Paulo');

        $this->assertSame($brasilia->format('Y-m-d H:i'), $now->format('Y-m-d H:i'));
    }
}
