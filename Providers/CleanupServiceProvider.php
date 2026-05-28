<?php

namespace Modules\Cleanup\Providers;

use Eventy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Modules\Cleanup\Console\CleanupAttachments;
use Modules\Cleanup\Console\CleanupConversations;
use View;

class CleanupServiceProvider extends ServiceProvider {
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot(): void {
        $this->registerCommands();
        $this->registerMigrations();
        $this->registerViews();
        $this->hooks();
    }

    /**
     * Register the Artisan commands.
     *
     * @return void
     */
    private function registerCommands(): void {
        $this->commands( [
            CleanupConversations::class,
            CleanupAttachments::class,
        ] );
    }
    
    /**
     * Register migrations.
     */
    private function registerMigrations(): void {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    /**
     * Register module views.
     */
    private function registerViews(): void {
        $viewPath = resource_path( 'views/modules/cleanup' );

        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes( [
            $sourcePath => $viewPath,
        ], 'views' );

        $this->loadViewsFrom( array_merge( array_map( function ( $path ) {
            return $path . '/modules/cleanup';
        }, Config::get( 'view.paths' ) ), [ $sourcePath ] ), 'cleanup' );
    }

    /**
     * Register FreeScout hooks.
     */
    private function hooks(): void {
        Eventy::addAction( 'menu.append', function () {
            if ( auth()->user() && auth()->user()->isAdmin() ) {
                echo View::make( 'cleanup::partials/menu' )->render();
            }
        } );

        Eventy::addFilter( 'menu.selected', function ( $menu ) {
            if ( auth()->user() && auth()->user()->isAdmin() ) {
                $menu['cleanup'] = [
                    'cleanup.index',
                ];
            }

            return $menu;
        } );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
    }

}
