<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->assertSafeTestProcessEnvironment();

        $app = parent::createApplication();
        $connection = (string) $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if (! $app->environment('testing') || $connection !== 'sqlite' || $database !== ':memory:') {
            throw new LogicException(
                'Test interrotti: Laravel non sta usando esclusivamente SQLite in memoria. Nessuna migrazione di test è stata eseguita.',
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function assertSafeTestProcessEnvironment(): void
    {
        if (getenv('APP_ENV') !== 'testing'
            || getenv('DB_CONNECTION') !== 'sqlite'
            || getenv('DB_DATABASE') !== ':memory:') {
            throw new LogicException(
                'Test interrotti: APP_ENV=testing, DB_CONNECTION=sqlite e DB_DATABASE=:memory: sono obbligatori.',
            );
        }
    }
}
