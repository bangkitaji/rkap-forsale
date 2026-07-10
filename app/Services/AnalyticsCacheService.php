<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Manages caching for the Analytics dashboard data.
 *
 * Cache is keyed by period_id so flushing is targeted per-period.
 *
 * Key pattern:
 *   analytics:v{version}:{period_id}:{user_id}:{isReport}
 *
 * Flush strategy:
 *   Increment the per-period version counter so all old keys become stale
 *   and expire naturally via TTL without needing Redis tags.
 */
class AnalyticsCacheService
{
    /** Cache TTL for analytics page data (seconds). */
    public const TTL = 900; // 15 minutes

    /** Cache TTL for detail modal endpoints. */
    public const DETAIL_TTL = 600; // 10 minutes

    /**
     * Cache key for the main analytics page (index / report).
     */
    public static function key(int $periodId, int $userId, bool $isReport): string
    {
        $version = self::periodVersion($periodId);
        $type    = $isReport ? 'report' : 'index';
        return "analytics:v{$version}:{$periodId}:{$userId}:{$type}";
    }

    /**
     * Cache key for detail modal endpoints (coaGroupDetail, cashflowGroupDetail, etc.)
     */
    public static function detailKey(string $type, int $periodId, int $groupId, ?array $bureauIds = null): string
    {
        $version   = self::periodVersion($periodId);
        $bureauKey = $bureauIds ? implode(',', $bureauIds) : 'all';
        return "analytics:v{$version}:{$type}:{$periodId}:{$groupId}:{$bureauKey}";
    }

    /**
     * Flush all cached analytics data for a given period by bumping its version counter.
     * All keys containing the old version will be naturally orphaned and expire via TTL.
     */
    public static function flushPeriod(int $periodId): void
    {
        Cache::increment(self::versionKey($periodId));
        Log::info('[AnalyticsCache] Flushed cache for period', ['period_id' => $periodId]);
    }

    /**
     * Flush analytics cache for ALL periods (e.g. when master data changes).
     */
    public static function flushAll(): void
    {
        // Flush known period version counters stored in cache
        // This is a best-effort clear since we don't track all period IDs here.
        // For a complete flush, call Cache::flush() or use Redis SCAN.
        Cache::increment('analytics:global_version');
        Log::info('[AnalyticsCache] Bumped global analytics cache version');
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private static function versionKey(int $periodId): string
    {
        return "analytics:period_version:{$periodId}";
    }

    /**
     * Get (or initialize) the version counter for a period.
     * Stored forever so the counter survives TTL; orphaned data keys expire on their own.
     */
    private static function periodVersion(int $periodId): int
    {
        return (int) Cache::rememberForever(self::versionKey($periodId), fn() => 1);
    }
}
