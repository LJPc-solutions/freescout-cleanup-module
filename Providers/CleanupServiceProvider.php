<?php

namespace Modules\Cleanup\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Cleanup\Console\CleanupConversations;
use Modules\Cleanup\Console\CleanupAttachments;

class CleanupServiceProvider extends ServiceProvider {
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot(): void {
        $this->registerCommands();
        $this->registerMigrations();
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
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
    }

}
