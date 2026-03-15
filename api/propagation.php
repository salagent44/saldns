<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$input = json_decode(file_get_contents('php://input'), true);
$domain = $input['domain'] ?? '';
$type = strtoupper(trim($input['type'] ?? 'A'));

// Sanitize domain
if (!preg_match('/^[a-zA-Z0-9.\-]+$/', $domain)) {
    echo json_encode(['error' => 'Invalid domain name']);
    exit;
}

if (strlen($domain) > 253) {
    echo json_encode(['error' => 'Domain too long']);
    exit;
}

$allowedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA', 'PTR', 'SRV', 'CAA'];
if (!in_array($type, $allowedTypes)) {
    echo json_encode(['error' => 'Invalid record type']);
    exit;
}

$resolvers = [
    ['name' => 'Google', 'ip' => '8.8.8.8'],
    ['name' => 'Cloudflare', 'ip' => '1.1.1.1'],
    ['name' => 'Quad9', 'ip' => '9.9.9.9'],
    ['name' => 'OpenDNS', 'ip' => '208.67.222.222'],
    ['name' => 'Comodo', 'ip' => '8.26.56.26'],
    ['name' => 'Verisign', 'ip' => '64.6.64.6'],
    ['name' => 'AdGuard', 'ip' => '94.140.14.14'],
    ['name' => 'CleanBrowsing', 'ip' => '185.228.168.9'],
];

$safeDomain = escapeshellarg($domain);
$safeType = escapeshellarg($type);

$results = [];
$allValues = [];

foreach ($resolvers as $resolver) {
    $safeServer = escapeshellarg('@' . $resolver['ip']);
    $cmd = "dig +noall +answer +stats $safeServer $safeDomain $safeType +time=5 +tries=1 2>&1";
    $output = shell_exec($cmd);

    $records = [];
    $queryTime = '';

    if ($output) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/Query time:\s*(.+)/i', $line, $m)) {
                $queryTime = $m[1];
                continue;
            }

            if (empty($line) || $line[0] === ';') continue;

            if (preg_match('/^(\S+)\s+(\d+)\s+IN\s+(\S+)\s+(.+)$/', $line, $m)) {
                $value = trim($m[4]);
                $records[] = $value;
                $allValues[] = $value;
            }
        }
    }

    $result = implode(', ', $records) ?: 'No response';

    $results[] = [
        'server' => $resolver['name'],
        'ip' => $resolver['ip'],
        'result' => $result,
        'query_time' => $queryTime,
    ];
}

// Check if all results are consistent
$uniqueValues = array_unique($allValues);
$consistent = count($uniqueValues) <= 1;

echo json_encode([
    'results' => $results,
    'consistent' => $consistent,
    'domain' => $domain,
    'type' => $type,
]);
