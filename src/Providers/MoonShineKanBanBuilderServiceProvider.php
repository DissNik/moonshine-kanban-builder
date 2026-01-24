<?php

namespace DissNik\MoonShineKanBanBuilder\Providers;

use Illuminate\Support\ServiceProvider;

class MoonShineKanBanBuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
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
        //
    }
}
