<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class QueryOptimizer
{
    /**
     * Apply eager loading to query with common relationships
     */
    public static function withCommonRelations(Builder $query, string $modelType): Builder
    {
        $relations = self::getCommonRelations($modelType);
        return $query->with($relations);
    }

    /**
     * Get common relations for model type
     */
    private static function getCommonRelations(string $modelType): array
    {
        $relations = [
            'program' => ['managerProgram', 'productionTeam.members.user'],
            'episode' => ['program.managerProgram', 'program.productionTeam.members.user', 'deadlines'],
            'creative_work' => ['episode.program', 'createdBy', 'reviewedBy'],
            'music_arrangement' => ['episode.program', 'createdBy', 'song', 'singer'],
            'recording' => ['episode.program', 'musicArrangement', 'createdBy', 'reviewedBy'],
            'editor_work' => ['episode.program', 'createdBy'],
            'quality_control' => ['episode.program', 'createdBy', 'reviewedBy'],
        ];

        return $relations[$modelType] ?? [];
    }

    /**
     * Cache query result with TTL
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Cache query result for user-specific data
     */
    public static function rememberForUser(string $key, int $userId, int $ttl, callable $callback)
    {
        $cacheKey = "{$key}_user_{$userId}";
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Clear cache by pattern
     */
    public static function clearCache(string $pattern): void
    {
        // For file-based cache, we need to clear manually
        // For Redis/Memcached, use tags if available
        Cache::flush(); // Simple approach - clear all cache
    }

    /**
     * Get cache key for model
     */
    public static function getCacheKey(string $model, int $id, ?string $suffix = null): string
    {
        $key = strtolower(class_basename($model)) . "_{$id}";
        return $suffix ? "{$key}_{$suffix}" : $key;
    }
}

