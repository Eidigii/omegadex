<?php
/**
 * Omegadex runtime configuration.
 * Toggle USE_RUNTIME_RENDERER to fall back to pre-generated extensionless files if needed.
 */
if (!defined('OMEGADEX_ROOT')) {
    define('OMEGADEX_ROOT', dirname(__DIR__));
}

if (!defined('OMEGADEX_USE_RUNTIME_RENDERER')) {
    define('OMEGADEX_USE_RUNTIME_RENDERER', true);
}

if (!defined('OMEGADEX_RENDER_CACHE_DIR')) {
    define('OMEGADEX_RENDER_CACHE_DIR', OMEGADEX_ROOT . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'rendered');
}

if (!defined('OMEGADEX_SEARCH_META_PATH')) {
    define('OMEGADEX_SEARCH_META_PATH', OMEGADEX_ROOT . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'search_index_meta.json');
}

/** Renderer version bump invalidates render cache when rules change */
if (!defined('OMEGADEX_RENDERER_VERSION')) {
    define('OMEGADEX_RENDERER_VERSION', '27');
}

// Polyfills for PHP < 8 (XAMPP may ship PHP 7.x)
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        return $len <= strlen($haystack) && substr($haystack, -$len) === $needle;
    }
}
