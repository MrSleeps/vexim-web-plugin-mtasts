<?php

namespace VEximweb\Plugin\MTASTS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Pdp\Rules;
use Pdp\Domain;
use VEximweb\Plugin\MTASTS\Services\PublicSuffixListService;
use VEximweb\Plugin\MTASTS\Models\MtaSts;
use VEximweb\Core\Data\Repositories\DomainRepository;
use Illuminate\Support\Facades\Log;

class MTASTSController extends Controller
{
    protected PublicSuffixListService $suffixService;
    protected DomainRepository $domainRepository;
    
    public function __construct(
        PublicSuffixListService $suffixService,
        DomainRepository $domainRepository
    ) {
        $this->suffixService = $suffixService;
        $this->domainRepository = $domainRepository;
    }  
    
    public function showTxtFile(Request $request) {
        $filePath = $this->suffixService->getFilePath();
        
        // Check if file exists
        if (!file_exists($filePath)) {
            Log::error('Public suffix list file not found', ['path' => $filePath]);
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
        
        // ===== LOOKUP DOMAIN FROM DATABASE =====
        // Find the domain in our database
        $domainRecord = $this->domainRepository->findByDomainName($registrableDomain);
        
        if (!$domainRecord) {
            Log::warning('Domain not found in database', ['domain' => $registrableDomain]);
            return response("Domain '$registrableDomain' not found in system\n", 404)
                ->header('Content-Type', 'text/plain');
        }
        
        // ===== FIND MTA-STS RECORD FOR THIS DOMAIN =====
        $mtaStsRecord = MtaSts::where('domain_id', $domainRecord->domain_id)->first();

        if (!$mtaStsRecord) {
            Log::warning('No MTA-STS record found for domain', [
                'domain' => $registrableDomain,
                'domain_id' => $domainRecord->domain_id
            ]);
            
            // Return a default "none" policy if no record exists
            $output = "version: STSv1\n";
            $output .= "mode: none\n";
            $output .= "max_age: 86400\n";
            
            return response($output, 200)
                ->header('Content-Type', 'text/plain; charset=utf-8')
                ->header('X-Content-Type-Options', 'nosniff')
                ->header('Cache-Control', 'public, max-age=86400');
        }
        
        // ===== GET MX RECORDS =====
        $mxRecords = [];
        $mxFound = false;
        
        if (getmxrr($registrableDomain, $mxRecords)) {
            $mxFound = true;
            // Sort MX records by priority
            sort($mxRecords);
        } else {
            Log::warning('No MX records found for domain', ['domain' => $registrableDomain]);
            
            // Try to get MX records from DNS using alternative method
            try {
                $dnsRecords = dns_get_record($registrableDomain, DNS_MX);
                if (!empty($dnsRecords)) {
                    $mxFound = true;
                    foreach ($dnsRecords as $record) {
                        if (isset($record['target']) && isset($record['pri'])) {
                            $mxRecords[] = $record['target'];
                        }
                    }
                    sort($mxRecords);
                }
            } catch (\Exception $e) {
                Log::error('DNS lookup failed', [
                    'domain' => $registrableDomain,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // ===== BUILD MTA-STS RESPONSE =====
        $output = "version: STSv1\n";
        $output .= "mode: " . ($mtaStsRecord->policy_type ?? 'none') . "\n";
        
        // Add MX records if found
        if ($mxFound && !empty($mxRecords)) {
            $output .= "mx: " . implode("\nmx: ", $mxRecords) . "\n";
        } else {
            // If no MX records, the policy should be "none" or we return an error
            Log::warning('No MX records to include in MTA-STS policy', ['domain' => $registrableDomain]);
            
            // If policy is "enforce" or "testing" but no MX records, override to "none"
            if (in_array($mtaStsRecord->policy_type, ['enforce', 'testing'])) {
                Log::warning('Overriding policy to "none" due to missing MX records', [
                    'domain' => $registrableDomain,
                    'requested_policy' => $mtaStsRecord->policy_type
                ]);
                $output = "version: STSv1\n";
                $output .= "mode: none\n";
                $output .= "max_age: 86400\n";
                
                return response($output, 200)
                    ->header('Content-Type', 'text/plain; charset=utf-8')
                    ->header('X-Content-Type-Options', 'nosniff')
                    ->header('Cache-Control', 'public, max-age=86400');
            }
        }
        
        // Add max_age from database record
        $maxAge = $mtaStsRecord->max_age ?? 86400;
        $output .= "max_age: " . $maxAge . "\n";
        
        // Log successful MTA-STS response
        Log::info('MTA-STS policy served', [
            'domain' => $registrableDomain,
            'mode' => $mtaStsRecord->policy_type,
            'max_age' => $maxAge,
            'mx_count' => count($mxRecords)
        ]);
        
        return response($output, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'public, max-age=' . min($maxAge, 86400));
    }
}