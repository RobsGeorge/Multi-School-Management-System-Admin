<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\SubdomainService;
use Illuminate\Console\Command;

class CheckSubdomainsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subdomains:check {--fix : Attempt to fix broken subdomains} {--school-id= : Check specific school only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the health of all subdomains';

    /**
     * Execute the console command.
     */
    public function handle(SubdomainService $subdomainService)
    {
        $this->info('🔍 Checking subdomain health...');
        
        $schoolId = $this->option('school-id');
        $shouldFix = $this->option('fix');
        
        if ($schoolId) {
            $school = School::find($schoolId);
            if (!$school) {
                $this->error("School with ID {$schoolId} not found.");
                return 1;
            }
            $this->checkSingleSchool($school, $subdomainService, $shouldFix);
        } else {
            $this->checkAllSchools($subdomainService, $shouldFix);
        }
        
        return 0;
    }
    
    private function checkSingleSchool(School $school, SubdomainService $subdomainService, bool $shouldFix)
    {
        $this->info("\n📋 Checking school: {$school->name} (ID: {$school->id})");
        
        $health = $subdomainService->checkSubdomainHealth($school);
        
        $this->displayHealthResult($health, $school);
        
        if ($shouldFix && $health['status'] === 'unhealthy') {
            $this->attemptFix($school, $subdomainService);
        }
    }
    
    private function checkAllSchools(SubdomainService $subdomainService, bool $shouldFix)
    {
        $schools = School::whereNotNull('domain')->get();
        
        if ($schools->isEmpty()) {
            $this->warn('No schools with domains found.');
            return;
        }
        
        $this->info("\n📊 Found {$schools->count()} schools with domains");
        
        $healthyCount = 0;
        $unhealthyCount = 0;
        $notConfiguredCount = 0;
        
        foreach ($schools as $school) {
            $health = $subdomainService->checkSubdomainHealth($school);
            
            $this->displayHealthResult($health, $school);
            
            switch ($health['status']) {
                case 'healthy':
                    $healthyCount++;
                    break;
                case 'unhealthy':
                    $unhealthyCount++;
                    if ($shouldFix) {
                        $this->attemptFix($school, $subdomainService);
                    }
                    break;
                case 'not_configured':
                    $notConfiguredCount++;
                    break;
            }
        }
        
        $this->displaySummary($healthyCount, $unhealthyCount, $notConfiguredCount);
    }
    
    private function displayHealthResult(array $health, School $school)
    {
        $status = match($health['status']) {
            'healthy' => '✅',
            'unhealthy' => '❌',
            'not_configured' => '⚠️',
            default => '❓'
        };
        
        $this->line("  {$status} {$school->name}");
        $this->line("     Domain: " . ($health['domain'] ?? 'Not set'));
        $this->line("     URL: " . ($health['full_url'] ?? 'N/A'));
        $this->line("     Status: {$health['message']}");
        
        if (isset($health['accessible']) && !$health['accessible']) {
            $this->warn("     ⚠️  Subdomain is not accessible");
        }
        
        $this->line('');
    }
    
    private function displaySummary(int $healthy, int $unhealthy, int $notConfigured)
    {
        $this->info("\n📈 Summary:");
        $this->line("  ✅ Healthy: {$healthy}");
        $this->line("  ❌ Unhealthy: {$unhealthy}");
        $this->line("  ⚠️  Not configured: {$notConfigured}");
        
        if ($unhealthy > 0) {
            $this->warn("\n💡 Run with --fix option to attempt automatic fixes");
        }
    }
    
    private function attemptFix(School $school, SubdomainService $subdomainService)
    {
        $this->info("  🔧 Attempting to fix subdomain for {$school->name}...");
        
        // Recreate the subdomain
        $result = $subdomainService->createSubdomain($school, $school->domain);
        
        if ($result['valid']) {
            $this->info("  ✅ Subdomain fixed successfully");
            $this->line("     New URL: {$result['full_url']}");
        } else {
            $this->error("  ❌ Failed to fix subdomain: {$result['message']}");
        }
    }
} 