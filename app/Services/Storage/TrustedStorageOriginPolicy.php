<?php

namespace App\Services\Storage;

use App\Models\StorageCredential;
use RuntimeException;

/** Hosted providers only. Neither stored URLs nor provider replies become request targets. */
class TrustedStorageOriginPolicy
{
    public function identity(StorageCredential $credential): array
    {
        if ($credential->provider === 'supabase') {
            $origin = $credential->supabase_url;
            $host = $this->host($origin);
            if (!preg_match('/^([a-z0-9]{20})\.supabase\.co$/D', $host, $matches)) {
                throw new RuntimeException('untrusted_account');
            }
            return ['provider' => 'supabase', 'identity' => $matches[1], 'origin' => 'https://' . $host];
        }
        if ($credential->provider === 'vercel') {
            if (!preg_match('/^vercel_blob_rw_([A-Za-z0-9]+)_[A-Za-z0-9_-]+$/D', $credential->vercel_blob_token ?? '', $matches)) {
                throw new RuntimeException('untrusted_account');
            }
            $host = strtolower($matches[1]) . '.public.blob.vercel-storage.com';
            if ($credential->vercel_blob_store_url) {
                $savedHost = $this->host($credential->vercel_blob_store_url);
                // Historical app default is a placeholder, never an account or request target.
                // Only this exact legacy host is exempt; real foreign store origins still conflict.
                if ($savedHost !== $host && $savedHost !== 'blob.vercel-storage.com') {
                    throw new RuntimeException('account_identity_conflict');
                }
            }
            return ['provider' => 'vercel', 'identity' => strtolower($matches[1]), 'origin' => 'https://' . $host];
        }
        throw new RuntimeException('unsupported_provider');
    }

    private function host($origin): string
    {
        if (!is_string($origin) || preg_match('/[\s\\\\]/', $origin)) {
            throw new RuntimeException('untrusted_account');
        }
        $parts = parse_url($origin);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('untrusted_account');
        }
        return strtolower($parts['host']);
    }

    public function transportOptions(string $origin): array
    {
        $host = $this->host($origin);
        if ($host !== 'vercel.com' && !preg_match('/^[a-z0-9]{20}\.supabase\.co$/D', $host)) {
            throw new RuntimeException('untrusted_transport');
        }
        if (!defined('CURLOPT_RESOLVE') || (!function_exists('curl_exec') && !function_exists('curl_multi_exec'))) {
            throw new RuntimeException('secure_transport_unavailable');
        }
        $addresses = $this->resolveAddresses($host);
        if (!$addresses) {
            throw new RuntimeException('dns_unavailable');
        }
        foreach ($addresses as $address) {
            // Reject IPv4-mapped IPv6 as well as private/reserved addresses.
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || $this->reservedAddress($address)) {
                throw new RuntimeException('unsafe_dns');
            }
        }
        $ip = $addresses[0];
        return ['allow_redirects' => false, 'proxy' => '', 'verify' => true,
            'curl' => [CURLOPT_RESOLVE => [$host . ':443:' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip)]]];
    }

    private function reservedAddress(string $address): bool
    {
        $bytes = inet_pton($address);
        $ranges = strlen($bytes) === 4
            ? ['0.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '192.0.0.0/24',
                '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/3']
            : ['::/96', '::ffff:0:0/96', '64:ff9b::/96', '100::/64', '2001::/23', '2002::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8'];
        foreach ($ranges as $range) {
            [$network, $bits] = explode('/', $range);
            $base = inet_pton($network);
            $full = intdiv((int) $bits, 8);
            $remaining = (int) $bits % 8;
            if (substr($bytes, 0, $full) === substr($base, 0, $full)
                && (!$remaining || ((ord($bytes[$full]) ^ ord($base[$full])) & (255 << (8 - $remaining))) === 0)) return true;
        }
        return false;
    }

    protected function resolveAddresses(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];
        foreach ($records ?: [] as $record) {
            if (isset($record['ip'])) $addresses[] = $record['ip'];
            if (isset($record['ipv6'])) $addresses[] = $record['ipv6'];
        }
        return array_values(array_unique($addresses));
    }
}
