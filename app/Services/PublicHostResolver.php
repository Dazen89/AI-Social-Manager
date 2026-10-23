<?php

namespace App\Services;

use App\Exceptions\EditorialSourceVerificationException;

class PublicHostResolver
{
    public function resolve(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! $this->isPublicAddress($host)) {
                throw new EditorialSourceVerificationException('Il dominio della fonte non punta a un indirizzo Internet pubblico.');
            }

            return $host;
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];

        foreach ($records ?: [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            throw new EditorialSourceVerificationException('Non riesco a risolvere il dominio della fonte. Controlla URL e DNS.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new EditorialSourceVerificationException('Il dominio della fonte risolve anche verso una rete privata o riservata. Verifica il DNS.');
            }
        }

        return $addresses[0];
    }

    public function isPublicAddress(string $address): bool
    {
        $packed = inet_pton($address);

        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16 && $this->inRange($packed, '::ffff:0:0', 96)) {
            $mapped = inet_ntop(substr($packed, 12));

            return is_string($mapped) && $this->isPublicAddress($mapped);
        }

        if (strlen($packed) === 4) {
            foreach ([
                ['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10],
                ['127.0.0.0', 8], ['169.254.0.0', 16], ['172.16.0.0', 12],
                ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.88.99.0', 24],
                ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24],
                ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4],
            ] as [$network, $bits]) {
                if ($this->inRange($packed, $network, $bits)) {
                    return false;
                }
            }

            return true;
        }

        // Only globally routable IPv6 unicast is eligible; exclude known special-use
        // ranges within 2000::/3, including documentation and transition prefixes.
        if (! $this->inRange($packed, '2000::', 3)) {
            return false;
        }

        foreach ([
            ['2001::', 32], ['2001:2::', 48], ['2001:10::', 28],
            ['2001:20::', 28], ['2001:db8::', 32], ['2002::', 16],
        ] as [$network, $bits]) {
            if ($this->inRange($packed, $network, $bits)) {
                return false;
            }
        }

        return true;
    }

    private function inRange(string $address, string $network, int $bits): bool
    {
        $networkBytes = inet_pton($network);

        if ($networkBytes === false || strlen($address) !== strlen($networkBytes)) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);

        if (substr($address, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }

        $remainingBits = $bits % 8;

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($address[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }
}
