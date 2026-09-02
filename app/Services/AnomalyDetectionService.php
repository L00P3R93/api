<?php

namespace App\Services;

use App\Models\RequestLog;
use App\Models\RiskEvent;
use Illuminate\Support\Facades\DB;

class AnomalyDetectionService
{
    public function checkRequestRate(string $ip, ?int $apiKeyId = null): ?RiskEvent
    {
        $recentCount = RequestLog::where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->count();

        if ($recentCount > 120) {
            return $this->createRiskEvent(
                ip: $ip,
                apiKeyId: $apiKeyId,
                eventType: 'rapid_requests',
                severity: $recentCount > 240 ? 'critical' : 'high',
                score: min(20 + (int) (($recentCount - 120) / 10), 40),
                details: ['requests_per_minute' => $recentCount]
            );
        }

        return null;
    }

    public function checkAmountAnomaly(float $amount, string $context, ?int $customerId = null, string $ip = '', ?int $apiKeyId = null): ?RiskEvent
    {
        $avgQuery = DB::table('ledger_entries')
            ->where('customer_id', $customerId)
            ->where('entry_type', $context)
            ->where('created_at', '>=', now()->subDays(30));

        $avgAmount = (float) $avgQuery->avg(DB::raw('ABS(debit - credit)'));

        if ($avgAmount <= 0) {
            return null;
        }

        $ratio = $amount / $avgAmount;

        if ($ratio > 10) {
            return $this->createRiskEvent(
                ip: $ip,
                apiKeyId: $apiKeyId,
                customerId: $customerId,
                eventType: 'high_amount',
                severity: 'critical',
                score: 40,
                details: ['amount' => $amount, 'average' => $avgAmount, 'ratio' => round($ratio, 2)]
            );
        }

        if ($ratio > 5) {
            return $this->createRiskEvent(
                ip: $ip,
                apiKeyId: $apiKeyId,
                customerId: $customerId,
                eventType: 'high_amount',
                severity: 'medium',
                score: 25,
                details: ['amount' => $amount, 'average' => $avgAmount, 'ratio' => round($ratio, 2)]
            );
        }

        return null;
    }

    public function checkFailedAuthAttempts(string $ip): ?RiskEvent
    {
        $recentFails = RequestLog::where('ip_address', $ip)
            ->where('status_code', 401)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($recentFails >= 3) {
            return $this->createRiskEvent(
                ip: $ip,
                eventType: 'failed_auth',
                severity: $recentFails >= 5 ? 'critical' : 'high',
                score: 30,
                details: ['failed_attempts' => $recentFails]
            );
        }

        return null;
    }

    public function checkTimeAnomaly(string $ip, ?int $apiKeyId = null): ?RiskEvent
    {
        $hour = (int) now()->format('H');

        if ($hour >= 2 && $hour <= 5) {
            return $this->createRiskEvent(
                ip: $ip,
                apiKeyId: $apiKeyId,
                eventType: 'unusual_hour',
                severity: 'low',
                score: 10,
                details: ['hour' => $hour]
            );
        }

        return null;
    }

    public function checkVelocity(int $customerId, string $type, string $ip = '', ?int $apiKeyId = null): ?RiskEvent
    {
        $recentCount = DB::table('ledger_entries')
            ->where('customer_id', $customerId)
            ->where('entry_type', $type)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentCount > 20) {
            return $this->createRiskEvent(
                ip: $ip,
                apiKeyId: $apiKeyId,
                customerId: $customerId,
                eventType: 'suspicious_pattern',
                severity: 'high',
                score: 15 + min($recentCount - 20, 15),
                details: ['transactions_in_10min' => $recentCount, 'type' => $type]
            );
        }

        return null;
    }

    public function calculateRiskScore(array $events): int
    {
        if (empty($events)) {
            return 0;
        }

        return max(array_map(fn ($e) => $e->score, $events));
    }

    public function shouldBlock(int $score): bool
    {
        return $score > 70;
    }

    public function shouldFlag(int $score): bool
    {
        return $score > 40;
    }

    private function createRiskEvent(
        string $ip,
        ?int $apiKeyId = null,
        ?int $customerId = null,
        string $eventType = '',
        string $severity = 'low',
        int $score = 0,
        ?array $details = null,
    ): RiskEvent {
        return RiskEvent::create([
            'ip_address' => $ip,
            'api_key_id' => $apiKeyId,
            'customer_id' => $customerId,
            'event_type' => $eventType,
            'severity' => $severity,
            'score' => $score,
            'details' => $details,
            'action_taken' => $this->shouldBlock($score) ? 'blocked' : ($this->shouldFlag($score) ? 'flagged' : 'logged'),
            'created_at' => now(),
        ]);
    }
}
