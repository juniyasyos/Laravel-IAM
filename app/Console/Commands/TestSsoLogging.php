<?php

namespace App\Console\Commands;

use App\Services\Sso\SsoLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TestSsoLogging extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sso:test-logging';

    /**
     * The console command description.
     */
    protected $description = 'Test SSO logging system with sample data';

    /**
     * Execute the console command.
     */
    public function handle(SsoLogger $logger): int
    {
        $this->info('🧪 Testing SSO Logging System...');
        $this->newLine();

        // Test 1: Authentication Flow
        $this->info('📝 Testing Authentication Flow Logging...');

        $logger->logLoginAttempt(
            email: 'test@example.com',
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0 Test Browser',
            additionalContext: [
                'test_run' => true,
                'intended_app' => 'test-client',
            ]
        );

        $logger->logLoginSuccess(
            userId: 999,
            email: 'test@example.com',
            ipAddress: '192.168.1.100',
            additionalContext: [
                'test_run' => true,
                'remember_me' => false,
            ]
        );

        $logger->logSsoRedirect(
            userId: 999,
            appKey: 'test-client',
            callbackUrl: 'https://client.example.com/callback',
            additionalContext: [
                'test_run' => true,
            ]
        );

        $this->line('   ✅ Authentication flow logs created');

        // Test 2: Token Management
        $this->info('📝 Testing Token Management Logging...');

        $logger->logTokenIssued(
            userId: 999,
            appKey: 'test-client',
            tokenPreview: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
            ttl: 300,
            additionalContext: [
                'test_run' => true,
                'issuer' => 'test-iam-server',
            ]
        );

        $testPayload = [
            'sub' => '999',
            'email' => 'test@example.com',
            'app' => 'test-client',
            'iss' => 'test-iam-server',
            'exp' => Carbon::now()->addMinutes(5)->timestamp,
        ];

        $logger->logTokenVerified(
            tokenPreview: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
            payload: $testPayload,
            additionalContext: [
                'test_run' => true,
            ]
        );

        $logger->logTokenVerificationFailed(
            tokenPreview: 'invalid.token.here...',
            reason: 'Token signature invalid',
            additionalContext: [
                'test_run' => true,
            ]
        );

        $this->line('   ✅ Token management logs created');

        // Test 3: Security Events
        $this->info('📝 Testing Security Events Logging...');

        $logger->logSecurityViolation('suspicious_user_agent', [
            'user_agent' => 'python-requests/2.25.1',
            'ip_address' => '10.0.0.1',
            'test_run' => true,
        ]);

        $logger->logRateLimit(
            identifier: '192.168.1.100',
            action: 'sso_requests',
            context: [
                'request_count' => 35,
                'time_window' => '1_minute',
                'test_run' => true,
            ]
        );

        $logger->logSecurity('invalid_token_format', [
            'token_preview' => 'malformed-token...',
            'ip_address' => '192.168.1.100',
            'test_run' => true,
        ]);

        $this->line('   ✅ Security event logs created');

        // Test 4: Performance Metrics
        $this->info('📝 Testing Performance Metrics Logging...');

        $trackingId1 = $logger->startPerformanceTracking('test_fast_operation');
        usleep(100000); // 100ms
        $logger->endPerformanceTracking($trackingId1, [
            'test_run' => true,
            'operation_type' => 'fast',
        ]);

        $trackingId2 = $logger->startPerformanceTracking('test_slow_operation');
        sleep(3); // 3 seconds - should trigger slow operation alert
        $logger->endPerformanceTracking($trackingId2, [
            'test_run' => true,
            'operation_type' => 'slow',
        ]);

        $logger->logPerformance('custom_metric', [
            'operation' => 'database_query',
            'duration_ms' => 1250.5,
            'query_count' => 5,
            'test_run' => true,
        ]);

        $this->line('   ✅ Performance metric logs created');

        // Test 5: Exception Logging
        $this->info('📝 Testing Exception Logging...');

        try {
            throw new \RuntimeException('Test exception for logging');
        } catch (\Throwable $exception) {
            $logger->logException($exception, SsoLogger::CATEGORY_TOKEN_MGMT, [
                'test_run' => true,
                'operation' => 'test_exception',
            ]);
        }

        $this->line('   ✅ Exception logs created');

        $this->newLine();
        $this->info('🎉 All logging tests completed successfully!');
        $this->newLine();

        $this->info('📊 You can now analyze the test logs using:');
        $this->line('   php artisan sso:analyze-logs --from=today');
        $this->line('   tail -f storage/logs/laravel.log | grep SSO');
        $this->line('   grep "test_run" storage/logs/laravel.log');

        return Command::SUCCESS;
    }
}
