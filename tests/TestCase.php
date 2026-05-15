<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Usa storage em diretório gravável (ex.: /tmp) para não depender de permissões em `storage/` do projeto
     * (comum quando o diretório pertence a outro usuário, ex.: www-data).
     */
    public function createApplication(): Application
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $storageRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'controladoria-sb-test-storage';
        if (! is_dir($storageRoot)) {
            mkdir($storageRoot, 0777, true);
        }

        foreach ([
            'framework/cache/data',
            'framework/sessions',
            'framework/views',
            'framework/testing/disks',
            'logs',
            'app/public',
            'app/private',
        ] as $sub) {
            $path = $storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $sub);
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }

        $app->useStoragePath($storageRoot);

        $this->traitsUsedByTest = class_uses_recursive(static::class);

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
