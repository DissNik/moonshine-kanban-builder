<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Providers;

use DissNik\MoonShineKanBanBuilder\Contracts\KanbanTransportContract;
use DissNik\MoonShineKanBanBuilder\Support\KanbanConfig;
use DissNik\MoonShineKanBanBuilder\Support\KanbanTransport;
use DissNik\MoonShineKanBanBuilder\Support\NullKanbanTransport;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

class MoonShineKanBanBuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes(
            [__DIR__ . '/../../config/kanban-builder.php' => config_path('moonshine-kanban-builder.php')],
            ['moonshine-kanban-builder', 'moonshine-kanban-builder-config', 'laravel-config']
        );

        $this->loadViewsFrom(
            __DIR__ . '/../../resources/views',
            'moonshine-kanban-builder'
        );

        $this->publishes(
            [__DIR__ . '/../../public' => public_path('vendor/moonshine-kanban-builder')],
            ['moonshine-kanban-builder', 'moonshine-kanban-builder-assets', 'laravel-assets']
        );
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/kanban-builder.php',
            'moonshine-kanban-builder'
        );

        $this->app->singleton(KanbanTransportContract::class, function ($app): KanbanTransportContract {
            $adapter = KanbanConfig::transportAdapter();

            if ($adapter === null) {
                return new NullKanbanTransport;
            }

            if (! class_exists($adapter)) {
                throw new RuntimeException(sprintf(
                    'Configured kanban transport adapter [%s] could not be found.',
                    $adapter,
                ));
            }

            $transport = $app->make($adapter);

            if (! $transport instanceof KanbanTransportContract) {
                throw new InvalidArgumentException(sprintf(
                    'Configured kanban transport adapter [%s] must implement [%s].',
                    $adapter,
                    KanbanTransportContract::class,
                ));
            }

            return $transport;
        });

        $this->app->singleton(KanbanTransport::class);
    }
}
