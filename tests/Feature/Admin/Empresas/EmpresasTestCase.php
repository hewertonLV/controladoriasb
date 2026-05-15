<?php

namespace Tests\Feature\Admin\Empresas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class EmpresasTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;
}
