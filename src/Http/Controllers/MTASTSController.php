<?php

namespace VEximweb\Plugin\MTASTS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Pdp\Rules;
use Pdp\Domain;
use VEximweb\Plugin\MTASTS\Services\PublicSuffixListService;

class MTASTSController extends Controller
{
    protected PublicSuffixListService $suffixService;
    
    public function __construct(PublicSuffixListService $suffixService)
    {
        $this->suffixService = $suffixService;
    }  
    
    public function showTxtFile(Request $request) {
        $filePath = $this->suffixService->getFilePath();
        
        // Check if file exists
        if (!file_exists($filePath)) {
            return response('Missing TLD lists', 404)
                ->header('Content-Type', 'text/plain');
        }
        
        $originalHost = $request->header('X-Original-Host');
        
        // Fallback to the actual host if header doesn't exist
        if (!$originalHost) {
            $originalHost = $request->getHost();
        }
        
        // Read the file directly
        $content = file_get_contents($filePath);        
        $publicSuffixList = Rules::fromPath($filePath);
        $domain = Domain::fromIDNA2008($originalHost);

        $result = $publicSuffixList->resolve($domain);
        $registrableDomain = $result->registrableDomain()->toString();
        
        // Get MX records
        $mxRecords = [];
        if (getmxrr($registrableDomain, $mxRecords)) {
            // Build MTA-STS response
            $output = "version: STSv1\n";
            $output .= "mode: enforce\n"; // or "testing" or "none"
            $output .= "mx: " . implode("\nmx: ", $mxRecords) . "\n";
            $output .= "max_age: 86400\n"; // 1 day in seconds
            
            return response($output, 200)
                ->header('Content-Type', 'text/plain; charset=utf-8')
                ->header('X-Content-Type-Options', 'nosniff')
                ->header('Cache-Control', 'public, max-age=86400');
        } else {
            // No MX records found - return error or minimal policy
            return response("No MX records found for $registrableDomain\n", 404)
                ->header('Content-Type', 'text/plain');
        }
    }
}