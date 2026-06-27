<?php

namespace VEximweb\Plugin\MTASTS\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Scheduling\Schedule;
use VEximweb\Plugin\MTASTS\Services\PublicSuffixListService;


class MTASTSServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        // Bind plugin repositories
        $this->bindRepositories();
        
        // Bind services
        $this->bindServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

        // Load migrations
        /*
        if (is_dir(__DIR__ . '/../Database/Migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        }
        */
        
		// Published via
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mta-sts.php',
            'mtasts'
        );        
        
        $this->publishes([
            __DIR__ . '/../config/mta-sts.php' => config_path('mta-sts.php'),
        ], 'mtasts-config');        
        
        // Register console commands (only in console)
        if ($this->app->runningInConsole()) {
            $this->loadCommands();
        }
        
        // Schedule the download
        $this->scheduleDownload();        
    }

    /**
     * Bind all repositories to their interfaces.
     */
    protected function bindRepositories(): void
    {
        /*
        $this->app->bind(
            EmailScoreSampleRepositoryInterface::class,
            EmailScoreSampleRepository::class
        );
        */

  
        
    }

    /**
     * Bind all services to the container.
     */
    protected function bindServices(): void
    {
        $this->app->singleton(PublicSuffixListService::class, function ($app) {
            return new PublicSuffixListService();
        });
    }

    /**
     * Auto-discover and register all console commands from the Commands directory.
     */
    protected function loadCommands(): void
    {
        $commandPath = __DIR__ . '/../Console/Commands';

        // Check if the Commands directory exists
        if (!is_dir($commandPath)) {
            return;
        }

        $commands = [];
        $files = File::files($commandPath);

        foreach ($files as $file) {
            // Only process PHP files
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = 'VEximweb\\Plugin\\MTASTS\\Console\\Commands\\' . $file->getFilenameWithoutExtension();

            // Check if the class exists and is a valid console command
            if (class_exists($className) && is_subclass_of($className, \Illuminate\Console\Command::class)) {
                $commands[] = $className;
            }
        }

        // Register all discovered commands
        if (!empty($commands)) {
            $this->commands($commands);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
        //EmailScoreSampleRepositoryInterface::class,

        ];
    }
    
    /**
     * Schedule the weekly download
     */
    protected function scheduleDownload()
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            
            // Run weekly on Sunday at 2 AM
            $schedule->command('mtasts:download-suffix-list')
                ->weekly()
                ->sundays()
                ->at('02:00')
                ->emailOutputOnFailure(config('vexim.communications.email_reports_to'))
                ->description('Download public suffix list');
        });
    }    
}