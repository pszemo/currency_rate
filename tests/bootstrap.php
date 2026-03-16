<?php

/**
 * Bootstrap for PHPUnit tests.
 *
 * Defines the minimal PrestaShop constants and stubs required
 * to load module classes outside of a running PS environment.
 */

define('_PS_VERSION_', '9.0.0');
define('_DB_PREFIX_', 'ps_');
define('_PS_CACHE_DIR_', sys_get_temp_dir() . '/currency_rate_test_cache/');
define('_MYSQL_ENGINE_', 'InnoDB');

// Ensure cache dir exists
if (!is_dir(_PS_CACHE_DIR_)) {
    mkdir(_PS_CACHE_DIR_, 0777, true);
}

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Minimal stub for PrestaShop's Cache facade.
 * The real Cache class is a singleton backed by CacheFs/Memcache.
 * In tests we replace it with a simple in-memory array.
 */
if (!class_exists('Cache')) {
    class Cache
    {
        private static array $store = [];
        private static ?Cache $instance = null;

        public static function getInstance(): static
        {
            if (self::$instance === null) {
                self::$instance = new static();
            }
            return self::$instance;
        }

        public function get(string $key): mixed
        {
            return self::$store[$key] ?? false;
        }

        public function set(string $key, mixed $value, int $ttl = 0): bool
        {
            self::$store[$key] = $value;
            return true;
        }

        public function exists(string $key): bool
        {
            return array_key_exists($key, self::$store);
        }

        public function delete(string $key): bool
        {
            unset(self::$store[$key]);
            return true;
        }

        public static function isStored(string $key): bool
        {
            return array_key_exists($key, self::$store);
        }

        public static function retrieve(string $key): mixed
        {
            return self::$store[$key] ?? null;
        }

        public static function store(string $key, mixed $value, int $ttl = 0): void
        {
            self::$store[$key] = $value;
        }

        public static function clean(string $key): void
        {
            if (str_ends_with($key, '*')) {
                $prefix = rtrim($key, '*');
                foreach (array_keys(self::$store) as $k) {
                    if (str_starts_with($k, $prefix)) {
                        unset(self::$store[$k]);
                    }
                }
            } else {
                unset(self::$store[$key]);
            }
        }

        public static function reset(): void
        {
            self::$store = [];
            self::$instance = null;
        }

        public static function dump(): array
        {
            return self::$store;
        }
    }
}

if (!class_exists('PrestaShopLogger')) {
    class PrestaShopLogger
    {
        public static array $logs = [];

        public static function addLog(string $message, int $severity = 1, ?int $errorCode = null, ?string $objectType = null): bool
        {
            self::$logs[] = compact('message', 'severity', 'objectType');
            return true;
        }

        public static function reset(): void
        {
            self::$logs = [];
        }
    }
}
