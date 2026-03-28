<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$input = json_decode(file_get_contents('php://input'), true);
$domain = $input['domain'] ?? '';
$types = $input['types'] ?? ['A'];
$server = $input['server'] ?? null;

// Sanitize domain
if (!preg_match('/^[a-zA-Z0-9.\-_]+$/', $domain)) {
    echo json_encode(['error' => 'Invalid domain name']);
    exit;
}

if (strlen($domain) > 253) {
    echo json_encode(['error' => 'Domain too long']);
    exit;
}

// Sanitize server
if ($server && !preg_match('/^[a-zA-Z0-9.\-:]+$/', $server)) {
    echo json_encode(['error' => 'Invalid DNS server']);
    exit;
}

$allowedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA', 'PTR', 'SRV', 'CAA', 'ANY'];
$results = [];

foreach ($types as $type) {
    $type = strtoupper(trim($type));
    if (!in_array($type, $allowedTypes)) continue;

    $safeDomain = escapeshellarg($domain);
    $safeType = escapeshellarg($type);
    $cmd = "dig +noall +answer +stats $safeDomain $safeType";

    if ($server) {
        $safeServer = escapeshellarg('@' . $server);
        $cmd = "dig +noall +answer +stats $safeServer $safeDomain $safeType";
    }

    $output = shell_exec("$cmd 2>&1");
    $records = [];
    $queryTime = '';

    if ($output) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $line = trim($line);

            // Parse query time
            if (preg_match('/Query time:\s*(.+)/i', $line, $m)) {
                $queryTime = $m[1];
                continue;
            }

            // Skip comments and empty
            if (empty($line) || $line[0] === ';') continue;

            // Parse answer lines: name ttl class type value
            if (preg_match('/^(\S+)\s+(\d+)\s+IN\s+(\S+)\s+(.+)$/', $line, $m)) {
                $records[] = [
                    'name' => rtrim($m[1], '.'),
                    'ttl' => intval($m[2]),
                    'type' => $m[3],
                    'value' => trim($m[4])
                ];
            }
        }
    }

    $results[] = [
        'type' => $type,
        'records' => $records,
        'query_time' => $queryTime
    ];
}

echo json_encode(['results' => $results]);
