<?php

namespace Modules\Cleanup\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Cleanup\Console\CleanupConversations;

class CleanupServiceProvider extends ServiceProvider {
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot(): void {
        $this->registerCommands();
    }

    /**
     * Register the Artisan commands.
     *
     * @return void
     */
    private function registerCommands(): void {
        $this->commands( [
            CleanupConversations::class,
        ] );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
    }

}
