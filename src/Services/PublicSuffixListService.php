<?php

namespace VEximweb\Plugin\MTASTS\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PublicSuffixListService
{
    /**
     * The URL for the public suffix list
     */
    protected string $sourceUrl = 'https://publicsuffix.org/list/public_suffix_list.dat';
    
    /**
     * Storage disk to use (can be configured)
     */
    protected string $disk = 'local';
    
    /**
     * Path where the file will be stored
     */
    protected string $storagePath = 'public-suffix-list/';
    
    /**
     * The filename
     */
    protected string $filename = 'public_suffix_list.dat';
    
    /**
     * Download the public suffix list
     */
    public function download(): bool
    {
        try {
            Log::info('Downloading public suffix list...');
            
            $response = Http::timeout(60)->get($this->sourceUrl);
            
            if (!$response->successful()) {
                Log::error('Failed to download public suffix list', [
                    'status' => $response->status()
                ]);
                return false;
            }
            
            // Store the file
            $this->store($response->body());
            
            Log::info('Public suffix list downloaded successfully');
            return true;
            
        } catch (\Exception $e) {
            Log::error('Error downloading public suffix list', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Store the content
     */
    protected function store(string $content): bool
    {
        // Ensure directory exists
        Storage::disk($this->disk)->makeDirectory($this->storagePath);
        
        // Store the file
        return Storage::disk($this->disk)->put(
            $this->getFullPath(),
            $content
        );
    }
    
    /**
     * Get the full storage path
     */
    public function getFullPath(): string
    {
        return $this->storagePath . $this->filename;
    }
    
    /**
     * Get the full path to the file (for direct access)
     */
    public function getFilePath(): string
    {
        return Storage::disk($this->disk)->path($this->getFullPath());
    }
    
    /**
     * Get the content of the file
     */
    public function getContent(): ?string
    {
        if (!$this->exists()) {
            return null;
        }
        
        return Storage::disk($this->disk)->get($this->getFullPath());
    }
    
    /**
     * Check if the file exists
     */
    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->getFullPath());
    }
    
    /**
     * Get the last modified time
     */
    public function getLastModified(): ?int
    {
        if (!$this->exists()) {
            return null;
        }
        
        return Storage::disk($this->disk)->lastModified($this->getFullPath());
    }
    
    /**
     * Check if the file is older than X days
     */
    public function isOlderThan(int $days): bool
    {
        $lastModified = $this->getLastModified();
        
        if (!$lastModified) {
            return true;
        }
        
        return ($lastModified + ($days * 24 * 60 * 60)) < time();
    }
    
    /**
     * Parse the public suffix list into an array
     */
    public function parse(): array
    {
        $content = $this->getContent();
        
        if (!$content) {
            return [];
        }
        
        $lines = explode("\n", $content);
        $suffixes = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '//')) {
                continue;
            }
            
            $suffixes[] = $line;
        }
        
        return $suffixes;
    }
    
    /**
     * Check if a domain matches the public suffix list
     */
    public function isPublicSuffix(string $domain): bool
    {
        $suffixes = $this->parse();
        
        foreach ($suffixes as $suffix) {
            // Handle wildcard rules (*.example.com)
            if (str_starts_with($suffix, '*.')) {
                $suffixWithoutWildcard = substr($suffix, 2);
                if (str_ends_with($domain, '.' . $suffixWithoutWildcard)) {
                    return true;
                }
                continue;
            }
            
            // Handle exception rules (!example.com)
            if (str_starts_with($suffix, '!')) {
                $suffixWithoutException = substr($suffix, 1);
                if ($domain === $suffixWithoutException) {
                    return false; // Exception overrides wildcard rule
                }
                continue;
            }
            
            // Exact match
            if ($domain === $suffix || str_ends_with($domain, '.' . $suffix)) {
                return true;
            }
        }
        
        return false;
    }
}