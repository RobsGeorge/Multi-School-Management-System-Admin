<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SubdomainService
{
    /**
     * Create and validate a subdomain for a school
     */
    public function createSubdomain(School $school, string $domain = null): array
    {
        try {
            // Generate domain if not provided
            if (!$domain) {
                $domain = $this->generateDomain($school->name);
            }

            // Validate domain
            $validation = $this->validateDomain($domain);
            if (!$validation['valid']) {
                return $validation;
            }

            // Check if domain is available
            if (!$this->isDomainAvailable($domain)) {
                return [
                    'valid' => false,
                    'message' => 'Domain is already taken',
                    'suggestions' => $this->generateDomainSuggestions($domain)
                ];
            }

            // Update school with domain
            $school->update(['domain' => $domain]);

            // Create DNS records (if applicable)
            $dnsResult = $this->createDNSRecords($domain);

            // Test subdomain accessibility
            $accessibilityResult = $this->testSubdomainAccessibility($domain);

            return [
                'valid' => true,
                'domain' => $domain,
                'full_url' => $this->getFullUrl($domain),
                'dns_created' => $dnsResult['success'],
                'dns_message' => $dnsResult['message'],
                'accessible' => $accessibilityResult['accessible'],
                'accessibility_message' => $accessibilityResult['message']
            ];

        } catch (\Exception $e) {
            Log::error('Subdomain creation failed: ' . $e->getMessage(), [
                'school_id' => $school->id,
                'domain' => $domain,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'valid' => false,
                'message' => 'Failed to create subdomain: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate a domain name from school name
     */
    public function generateDomain(string $schoolName): string
    {
        // Remove special characters and convert to lowercase
        $domain = Str::slug($schoolName, '');
        
        // Remove numbers from the beginning
        $domain = preg_replace('/^[0-9]+/', '', $domain);
        
        // Ensure minimum length
        if (strlen($domain) < 3) {
            $domain = 'school' . $domain;
        }
        
        // Ensure maximum length (63 characters for DNS)
        if (strlen($domain) > 63) {
            $domain = substr($domain, 0, 63);
        }
        
        return $domain;
    }

    /**
     * Validate domain name
     */
    public function validateDomain(string $domain): array
    {
        $validator = Validator::make(['domain' => $domain], [
            'domain' => 'required|string|min:3|max:63|regex:/^[a-z0-9-]+$/'
        ]);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'message' => 'Invalid domain format. Use only lowercase letters, numbers, and hyphens.',
                'errors' => $validator->errors()
            ];
        }

        // Check for reserved words
        $reservedWords = ['www', 'mail', 'ftp', 'admin', 'api', 'app', 'test', 'dev', 'staging'];
        if (in_array(strtolower($domain), $reservedWords)) {
            return [
                'valid' => false,
                'message' => 'Domain contains reserved words that cannot be used.'
            ];
        }

        // Check for consecutive hyphens
        if (strpos($domain, '--') !== false) {
            return [
                'valid' => false,
                'message' => 'Domain cannot contain consecutive hyphens.'
            ];
        }

        // Check for leading/trailing hyphens
        if (substr($domain, 0, 1) === '-' || substr($domain, -1) === '-') {
            return [
                'valid' => false,
                'message' => 'Domain cannot start or end with a hyphen.'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Check if domain is available
     */
    public function isDomainAvailable(string $domain): bool
    {
        return !School::where('domain', $domain)->exists();
    }

    /**
     * Generate domain suggestions
     */
    public function generateDomainSuggestions(string $baseDomain): array
    {
        $suggestions = [];
        $counter = 1;
        
        while (count($suggestions) < 5) {
            $suggestion = $baseDomain . $counter;
            if ($this->isDomainAvailable($suggestion)) {
                $suggestions[] = $suggestion;
            }
            $counter++;
        }
        
        return $suggestions;
    }

    /**
     * Create DNS records for the subdomain
     */
    public function createDNSRecords(string $domain): array
    {
        try {
            // Get the main domain from config
            $mainDomain = config('app.domain', 'yourdomain.com');
            $fullDomain = $domain . '.' . $mainDomain;
            
            // This is where you would integrate with your DNS provider
            // Examples: Cloudflare, AWS Route53, GoDaddy, etc.
            
            // For now, we'll just log and return success
            Log::info('DNS record creation requested', [
                'domain' => $domain,
                'full_domain' => $fullDomain,
                'main_domain' => $mainDomain
            ]);
            
            return [
                'success' => true,
                'message' => 'DNS record creation initiated. Please configure your DNS provider.',
                'full_domain' => $fullDomain
            ];
            
        } catch (\Exception $e) {
            Log::error('DNS record creation failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to create DNS records: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test if subdomain is accessible
     */
    public function testSubdomainAccessibility(string $domain): array
    {
        try {
            $fullUrl = $this->getFullUrl($domain);
            
            // Test DNS resolution
            $ip = gethostbyname($fullUrl);
            if ($ip === $fullUrl) {
                return [
                    'accessible' => false,
                    'message' => 'DNS resolution failed. Subdomain may not be configured yet.'
                ];
            }
            
            // Test HTTP accessibility (optional - can be slow)
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'HEAD'
                ]
            ]);
            
            $result = @file_get_contents($fullUrl, false, $context);
            
            return [
                'accessible' => $result !== false,
                'message' => $result !== false ? 'Subdomain is accessible' : 'Subdomain is not responding'
            ];
            
        } catch (\Exception $e) {
            return [
                'accessible' => false,
                'message' => 'Accessibility test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get full URL for subdomain
     */
    public function getFullUrl(string $domain): string
    {
        $mainDomain = config('app.domain', 'yourdomain.com');
        $protocol = config('app.env') === 'production' ? 'https' : 'http';
        
        return $protocol . '://' . $domain . '.' . $mainDomain;
    }

    /**
     * Check subdomain health
     */
    public function checkSubdomainHealth(School $school): array
    {
        if (!$school->domain) {
            return [
                'status' => 'not_configured',
                'message' => 'No domain configured for this school'
            ];
        }

        $accessibilityResult = $this->testSubdomainAccessibility($school->domain);
        
        return [
            'status' => $accessibilityResult['accessible'] ? 'healthy' : 'unhealthy',
            'domain' => $school->domain,
            'full_url' => $this->getFullUrl($school->domain),
            'accessible' => $accessibilityResult['accessible'],
            'message' => $accessibilityResult['message']
        ];
    }

    /**
     * Get all subdomain health statuses
     */
    public function getAllSubdomainHealth(): array
    {
        $schools = School::whereNotNull('domain')->get();
        $results = [];
        
        foreach ($schools as $school) {
            $results[] = [
                'school_id' => $school->id,
                'school_name' => $school->name,
                'health' => $this->checkSubdomainHealth($school)
            ];
        }
        
        return $results;
    }
} 