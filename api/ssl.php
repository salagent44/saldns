<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once __DIR__ . '/_security.php';

$input = json_decode(file_get_contents('php://input'), true);
$host = $input['host'] ?? '';
$port = intval($input['port'] ?? 443);

if (!preg_match('/^[a-zA-Z0-9.\-]+$/', $host)) {
    echo json_encode(['error' => 'Invalid hostname']);
    exit;
}

if (strlen($host) > 253) {
    echo json_encode(['error' => 'Hostname too long']);
    exit;
}

if ($port < 1 || $port > 65535) {
    echo json_encode(['error' => 'Invalid port']);
    exit;
}

// SSRF protection: block private/internal IPs
$resolvedIp = validate_host_public($host);
if ($resolvedIp === false) {
    block_private();
}

$safeHost = escapeshellarg($host);
$safePort = escapeshellarg((string)$port);

// Get full certificate details using openssl s_client + x509
$cmd = "echo | openssl s_client -connect {$safeHost}:{$safePort} -servername {$safeHost} 2>/dev/null | openssl x509 -noout -text -dates -fingerprint -sha256 2>&1";
$output = shell_exec($cmd);

if (empty($output) || strpos($output, 'unable to load certificate') !== false) {
    echo json_encode(['error' => 'Could not retrieve SSL certificate. Host may not support HTTPS.']);
    exit;
}

$cert = [];

// Subject
if (preg_match('/Subject:\s*(.+)/i', $output, $m)) {
    $cert['subject'] = trim($m[1]);
    if (preg_match('/CN\s*=\s*([^,\/]+)/', $m[1], $cn)) {
        $cert['common_name'] = trim($cn[1]);
    }
}

// Issuer
if (preg_match('/Issuer:\s*(.+)/i', $output, $m)) {
    $cert['issuer'] = trim($m[1]);
    if (preg_match('/O\s*=\s*([^,\/]+)/', $m[1], $org)) {
        $cert['issuer_org'] = trim($org[1]);
    }
    if (preg_match('/CN\s*=\s*([^,\/]+)/', $m[1], $cn)) {
        $cert['issuer_cn'] = trim($cn[1]);
    }
}

// Validity dates
if (preg_match('/notBefore=(.+)/i', $output, $m)) {
    $cert['not_before'] = trim($m[1]);
}
if (preg_match('/notAfter=(.+)/i', $output, $m)) {
    $cert['not_after'] = trim($m[1]);
    $expiry = strtotime(trim($m[1]));
    if ($expiry) {
        $cert['days_remaining'] = (int)round(($expiry - time()) / 86400);
    }
}

// Serial Number
if (preg_match('/Serial Number:\s*\n?\s*([0-9a-f:]+)/i', $output, $m)) {
    $cert['serial'] = trim($m[1]);
}

// Signature Algorithm
if (preg_match('/Signature Algorithm:\s*(.+)/i', $output, $m)) {
    $cert['signature_algorithm'] = trim($m[1]);
}

// Public Key info
if (preg_match('/Public Key Algorithm:\s*(.+)/i', $output, $m)) {
    $cert['key_algorithm'] = trim($m[1]);
}
if (preg_match('/Public-Key:\s*\((\d+) bit\)/i', $output, $m)) {
    $cert['key_size'] = $m[1] . ' bit';
}

// SHA-256 Fingerprint
if (preg_match('/sha256 Fingerprint=(.+)/i', $output, $m)) {
    $cert['fingerprint_sha256'] = trim($m[1]);
}

// Subject Alternative Names (SANs)
$sans = [];
if (preg_match('/X509v3 Subject Alternative Name:\s*\n\s*(.+)/i', $output, $m)) {
    $sanLine = trim($m[1]);
    preg_match_all('/DNS:([^,\s]+)/', $sanLine, $sanMatches);
    if (!empty($sanMatches[1])) {
        $sans = $sanMatches[1];
    }
    preg_match_all('/IP Address:([^,\s]+)/', $sanLine, $ipMatches);
    if (!empty($ipMatches[1])) {
        $sans = array_merge($sans, array_map(function($ip) { return 'IP:' . $ip; }, $ipMatches[1]));
    }
}

// Check certificate chain
$chainCmd = "echo | openssl s_client -connect {$safeHost}:{$safePort} -servername {$safeHost} -showcerts 2>/dev/null";
$chainOutput = shell_exec($chainCmd);
$chain = [];
if (preg_match_all('/-----BEGIN CERTIFICATE-----/', $chainOutput, $chainMatches)) {
    $chainCount = count($chainMatches[0]);
    preg_match_all('/s:(.+?)$/m', $chainOutput, $subjects);
    preg_match_all('/i:(.+?)$/m', $chainOutput, $issuers);
    for ($i = 0; $i < $chainCount; $i++) {
        $chain[] = [
            'subject' => isset($subjects[1][$i]) ? trim($subjects[1][$i]) : 'Unknown',
            'issuer' => isset($issuers[1][$i]) ? trim($issuers[1][$i]) : 'Unknown',
        ];
    }
}

// TLS version check
$protocols = [];
foreach (['tls1' => 'TLS 1.0', 'tls1_1' => 'TLS 1.1', 'tls1_2' => 'TLS 1.2', 'tls1_3' => 'TLS 1.3'] as $flag => $label) {
    $tlsCmd = "echo | openssl s_client -connect {$safeHost}:{$safePort} -servername {$safeHost} -{$flag} 2>&1";
    $tlsOut = shell_exec($tlsCmd);
    $supported = strpos($tlsOut, 'BEGIN CERTIFICATE') !== false;
    $protocols[] = ['version' => $label, 'supported' => $supported];
}

echo json_encode([
    'host' => $host,
    'port' => $port,
    'certificate' => $cert,
    'sans' => $sans,
    'chain' => $chain,
    'protocols' => $protocols,
]);
