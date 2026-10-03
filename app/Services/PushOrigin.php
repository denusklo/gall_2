<?php

namespace App\Services;

class PushOrigin
{
    public function current(): string
    {
        $origin = $this->normalize(config('push.origin'));
        if ($origin === null) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(503, 'Push origin is unavailable');
        }
        return $origin;
    }

    public function normalize($value): ?string
    {
        if (!is_string($value) || $value === '' || preg_match('/[\s\\\\]/', $value)) {
            return null;
        }
        $parts = parse_url($value);
        if (!$parts || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            return null;
        }
        return $scheme . '://' . $host
            . (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80) ? '' : ':' . $port);
    }
}
