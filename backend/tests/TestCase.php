<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Segunda barrera (la primera es tests/bootstrap.php): se verifica antes de que
     * RefreshDatabase ejecute migrate:fresh, que corre dentro de setUpTraits().
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $database = DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Suite bloqueada: la conexión apunta a [{$database}], no a una base *_test.");
        }

        return parent::setUpTraits();
    }
}
