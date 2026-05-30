<?php

namespace Tests\Unit\Support\Captacao;

use App\Support\Captacao\RomaneioRotaPdfBranding;
use Tests\TestCase;

class RomaneioRotaPdfBrandingTest extends TestCase
{
    public function test_logo_data_uri_eh_png_base64(): void
    {
        $dataUri = RomaneioRotaPdfBranding::logoDataUri();

        $this->assertNotNull($dataUri);
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function test_paleta_usa_cores_da_marca(): void
    {
        $this->assertSame('#1A5FB4', RomaneioRotaPdfBranding::COR_AZUL);
        $this->assertSame('#FBC02D', RomaneioRotaPdfBranding::COR_AMARELO);
        $this->assertSame('#2E7D32', RomaneioRotaPdfBranding::COR_VERDE);
    }
}
