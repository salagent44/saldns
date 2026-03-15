<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$input = json_decode(file_get_contents('php://input'), true);
$ip = $input['ip'] ?? '';

// Sanitize IP (IPv4 or IPv6)
if (!preg_match('/^[a-fA-F0-9.:]+$/', $ip)) {
    echo json_encode(['error' => 'Invalid IP address']);
    exit;
}

if (strlen($ip) > 45) {
    echo json_encode(['error' => 'IP address too long']);
    exit;
}

$safeIp = escapeshellarg($ip);

// Run dig -x for PTR lookup
$digCmd = "dig -x $safeIp +noall +answer +stats @8.8.8.8 2>&1";
$digOutput = shell_exec($digCmd);

$ptrRecords = [];
$queryTime = '';

if ($digOutput) {
    $lines = explode("\n", trim($digOutput));
    foreach ($lines as $line) {
        $line = trim($line);

        if (preg_match('/Query time:\s*(.+)/i', $line, $m)) {
            $queryTime = $m[1];
            continue;
        }

        if (empty($line) || $line[0] === ';') continue;

        if (preg_match('/^(\S+)\s+(\d+)\s+IN\s+PTR\s+(.+)$/', $line, $m)) {
            $ptrRecords[] = [
                'name' => rtrim($m[1], '.'),
                'ttl' => intval($m[2]),
                'value' => rtrim(trim($m[3]), '.'),
            ];
        }
    }
}

// Run host command as fallback
$hostCmd = "host $safeIp 2>&1";
$hostOutput = shell_exec($hostCmd);
$hostResult = trim($hostOutput ?? '');

echo json_encode([
    'ip' => $ip,
    'ptr_records' => $ptrRecords,
    'query_time' => $queryTime,
    'host_output' => $hostResult,
]);
