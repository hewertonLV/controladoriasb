<?php

namespace App\Support\Captacao;

final class RomaneioRotaPdfBranding
{
    /** Azul principal — texto da marca Sítio Barreiras */
    public const COR_AZUL = '#1A5FB4';

    /** Amarelo — sol da marca */
    public const COR_AMARELO = '#FBC02D';

    /** Verde — folhas da marca */
    public const COR_VERDE = '#2E7D32';

    public const LOGO_RELATIVO = 'assets/images/logo cor.png';

    public static function logoDataUri(): ?string
    {
        $path = public_path(self::LOGO_RELATIVO);

        if (! is_file($path)) {
            return null;
        }

        $conteudo = file_get_contents($path);

        if ($conteudo === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($conteudo);
    }
}
