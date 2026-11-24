<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class StoreContext
{
    protected static ?int $overrideStoreId = null;

    protected static bool $bypassed = false;

    protected static bool $resolvingAuth = false;

    public static function set(?int $storeId): void
    {
        self::$overrideStoreId = $storeId;
    }

    public static function clear(): void
    {
        self::$overrideStoreId = null;
        self::$bypassed = false;
    }

    public static function isBypassed(): bool
    {
        return self::$bypassed;
    }

    public static function id(): ?int
    {
        if (self::$bypassed) {
            return null;
        }

        if (self::$overrideStoreId !== null) {
            return self::$overrideStoreId;
        }

        if (self::$resolvingAuth) {
            return null;
        }

        self::$resolvingAuth = true;

        try {
            if (Auth::hasUser() || Auth::check()) {
                $storeId = Auth::user()?->store_id;

                return $storeId !== null ? (int) $storeId : null;
            }
        } finally {
            self::$resolvingAuth = false;
        }

        return null;
    }

    public static function withoutScope(callable $callback): mixed
    {
        $previous = self::$bypassed;
        self::$bypassed = true;

        try {
            return $callback();
        } finally {
            self::$bypassed = $previous;
        }
    }

    public static function runAs(int $storeId, callable $callback): mixed
    {
        $prevOverride = self::$overrideStoreId;
        $prevBypass = self::$bypassed;
        self::$overrideStoreId = $storeId;
        self::$bypassed = false;

        try {
            return $callback();
        } finally {
            self::$overrideStoreId = $prevOverride;
            self::$bypassed = $prevBypass;
        }
    }
}
