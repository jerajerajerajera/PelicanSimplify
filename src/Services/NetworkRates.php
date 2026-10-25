<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Cache;

/** Recent container traffic, sampled by real elapsed time rather than assumed intervals. */
final class NetworkRates
{
    public static function sample(string $key, array $counters, ?float $timestamp = null): array
    {
        $timestamp ??= microtime(true);
        return Cache::lock('simplify.network-rate-lock.'.$key, 5)->block(3, function () use ($key, $counters, $timestamp): array {
            $cacheKey = 'simplify.network-rate.'.$key;
            $previous = Cache::get($cacheKey);
            // The same sample again (a shared node list that was still cached) keeps the rates it produced.
            if (is_array($previous) && (float) ($previous['at'] ?? 0) >= $timestamp && is_array($previous['rates'] ?? null)) return $previous['rates'];
            $rates = self::calculate($counters, $timestamp, is_array($previous) ? $previous : null);
            Cache::put($cacheKey, ['at'=>$timestamp, 'counters'=>$counters, 'rates'=>$rates], 120);
            return $rates;
        });
    }
    public static function calculate(array $counters, float $timestamp, ?array $previous): array
    {
        $result = ['rx_per_second'=>null, 'tx_per_second'=>null];
        $elapsed = $timestamp - (float) ($previous['at'] ?? $timestamp);
        if ($elapsed <= 0 || $elapsed > 120 || !is_array($previous['counters'] ?? null)) return $result;
        $rx = 0.0; $tx = 0.0; $matched = 0;
        foreach ($counters as $id => $current) {
            $old = $previous['counters'][$id] ?? null;
            if (!$old) continue;
            foreach (['rx_bytes','tx_bytes'] as $field) {
                if (!is_numeric($current[$field] ?? null) || !is_numeric($old[$field] ?? null)
                    || !is_finite((float)$current[$field]) || !is_finite((float)$old[$field])
                    || (float)$current[$field] < 0 || (float)$old[$field] < 0) continue 2;
            }
            $matched++;
            // A restarted container has new counters; do not turn its reset into a spike.
            if ($current['rx_bytes'] < $old['rx_bytes'] || $current['tx_bytes'] < $old['tx_bytes']) continue;
            $rx += $current['rx_bytes'] - $old['rx_bytes']; $tx += $current['tx_bytes'] - $old['tx_bytes'];
        }
        return $matched ? ['rx_per_second'=>$rx/$elapsed,'tx_per_second'=>$tx/$elapsed] : $result;
    }
}
