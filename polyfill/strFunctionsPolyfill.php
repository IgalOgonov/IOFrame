<?php

/* Polyfill for older PHP versions */
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle): bool {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle): bool {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle): bool {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}