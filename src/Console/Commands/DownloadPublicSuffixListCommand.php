<?php

namespace VEximweb\Plugin\MTASTS\Console\Commands;

use Illuminate\Console\Command;
use VEximweb\Plugin\MTASTS\Services\PublicSuffixListService;

class DownloadPublicSuffixListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mtasts:download-suffix-list';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download the public suffix list from publicsuffix.org';
    
    /**
     * Execute the console command.
     */
    public function handle(PublicSuffixListService $service)
    {
        $this->info('Downloading public suffix list...');
        
        if ($service->download()) {
            $this->info('Public suffix list downloaded successfully!');
            return Command::SUCCESS;
        }
        
        $this->error('Failed to download public suffix list.');
        return Command::FAILURE;
    }
}