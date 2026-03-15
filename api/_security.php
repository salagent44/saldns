<?php
/**
 * Shared security helpers for API endpoints.
 * Blocks requests targeting private/internal networks (SSRF protection).
 */

function is_private_ip($ip) {
    // Remove IPv6 brackets if present
    $ip = trim($ip, '[]');

    // IPv6 loopback and link-local
    if ($ip === '::1' || strpos($ip, 'fe80:') === 0 || strpos($ip, 'fc00:') === 0 || strpos($ip, 'fd') === 0) {
        return true;
    }

    // IPv4 private/reserved ranges
    $privateRanges = [
        '127.0.0.0/8',       // Loopback
        '10.0.0.0/8',        // RFC 1918
        '172.16.0.0/12',     // RFC 1918
        '192.168.0.0/16',    // RFC 1918
        '169.254.0.0/16',    // Link-local
        '0.0.0.0/8',         // Current network
        '100.64.0.0/10',     // Shared address space (CGN)
        '192.0.0.0/24',      // IETF Protocol
        '192.0.2.0/24',      // TEST-NET-1
        '198.51.100.0/24',   // TEST-NET-2
        '203.0.113.0/24',    // TEST-NET-3
        '224.0.0.0/4',       // Multicast
        '240.0.0.0/4',       // Reserved
        '255.255.255.255/32' // Broadcast
    ];

    // IPv6 public addresses: if it passed the checks above, it's not private
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return false;
    }

    $ipLong = ip2long($ip);
    if ($ipLong === false) return true; // Can't parse = block it

    foreach ($privateRanges as $range) {
        list($subnet, $mask) = explode('/', $range);
        $subnetLong = ip2long($subnet);
        $maskLong = ~((1 << (32 - $mask)) - 1);
        if (($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
            return true;
        }
    }

    return false;
}

/**
 * Resolve a hostname and check if ANY of its IPs are private.
 * Returns the resolved public IP on success, or false if private/unresolvable.
 */
function validate_host_public($hostname) {
    // If it's already an IP, check directly
    if (filter_var($hostname, FILTER_VALIDATE_IP)) {
        return is_private_ip($hostname) ? false : $hostname;
    }

    // Resolve hostname using dig (not PHP's dns_get_record which we want to avoid)
    $safeHost = escapeshellarg($hostname);
    $output = shell_exec("dig +short $safeHost A 2>/dev/null");
    $ips = array_filter(array_map('trim', explode("\n", $output)));

    // Also check AAAA
    $output6 = shell_exec("dig +short $safeHost AAAA 2>/dev/null");
    $ips6 = array_filter(array_map('trim', explode("\n", $output6)));

    $allIps = array_merge($ips, $ips6);

    if (empty($allIps)) {
        return false; // Can't resolve
    }

    // Check ALL resolved IPs — if any are private, block
    foreach ($allIps as $ip) {
        // Skip CNAME responses
        if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
        if (is_private_ip($ip)) {
            return false;
        }
    }

    // Return first valid IP
    foreach ($allIps as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }

    return false;
}

function block_private($message = 'Requests to private/internal addresses are not allowed.') {
    echo json_encode(['error' => $message]);
    exit;
}
