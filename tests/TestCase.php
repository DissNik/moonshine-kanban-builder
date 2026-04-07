<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Tests;

use DissNik\MoonShineKanBanBuilder\Providers\MoonShineKanBanBuilderServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application(dirname(__DIR__));
        Container::setInstance($this->app);

        $this->app->instance('config', new Repository([]));
        $this->app->instance('path.config', dirname(__DIR__) . '/config');

        (new MoonShineKanBanBuilderServiceProvider($this->app))->register();
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }
}
