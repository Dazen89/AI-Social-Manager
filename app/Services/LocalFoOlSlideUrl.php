<?php

namespace App\Services;

class LocalFoOlSlideUrl
{
    public function isAllowed(string $url): bool
    {
        if (config('app.env') !== 'local') {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'http'
            && strtolower($parts['host'] ?? '') === 'localhost'
            && isset($parts['port'])
            && $parts['port'] >= 1024
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment']);
    }
}
