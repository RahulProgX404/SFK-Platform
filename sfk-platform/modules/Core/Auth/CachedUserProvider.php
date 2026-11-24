<?php

declare(strict_types=1);

namespace Modules\Core\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Core\Application\Cache\ReferenceCache;

/**
 * Speed (pass 2026-11-24, docs/performance.md): the signed-in user was read from the database on every request — every
 * page and every Livewire click (~80 ms from Japan to Neon). The session's user is read through the ReferenceCache
 * instead (its column values, built again as a model). Every save or delete of a user bumps that cache
 * (CoreServiceProvider), so a changed password, 在籍状況, ユーザー種別 or 財務分析を表示 is read afresh on the next request
 * — signing out a deactivated user (EnsureAccountIsActive) and Filament's password check of the session included. Not
 * where the cache store is the database itself (Render: one query instead of another) or the reference cache is off.
 * The remember-me cookie is still checked against the database (retrieveByToken).
 */
final class CachedUserProvider extends EloquentUserProvider
{
    /** @param  mixed  $identifier */
    public function retrieveById($identifier): ?Authenticatable
    {
        if (! self::cacheable() || ! (is_int($identifier) || (is_string($identifier) && ctype_digit($identifier)))) {
            return parent::retrieveById($identifier);
        }

        $id = (int) $identifier;
        $user = ReferenceCache::rememberModels('auth-user:'.$id, $this->getModel(), function () use ($id): array {
            $user = parent::retrieveById($id);

            return $user !== null ? [$user] : [];
        })->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    private static function cacheable(): bool
    {
        return ReferenceCache::enabled() && config('cache.stores.'.config('cache.default').'.driver') !== 'database';
    }
}
