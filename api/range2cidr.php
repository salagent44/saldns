<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$input = json_decode(file_get_contents('php://input'), true);
$startIp = $input['start'] ?? '';
$endIp = $input['end'] ?? '';

if (!filter_var($startIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    echo json_encode(['error' => 'Invalid start IP']);
    exit;
}

if (!filter_var($endIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    echo json_encode(['error' => 'Invalid end IP']);
    exit;
}

$start = ip2long($startIp);
$end = ip2long($endIp);

if ($start > $end) {
    echo json_encode(['error' => 'Start IP must be less than or equal to end IP']);
    exit;
}

// Convert IP range to minimal set of CIDR blocks
$cidrs = [];
while ($start <= $end) {
    // Find the largest block that fits
    $maxBits = 32;
    while ($maxBits > 0) {
        $mask = (-1 << (32 - ($maxBits - 1))) & 0xFFFFFFFF;
        $network = $start & $mask;
        $broadcast = $network | (~$mask & 0xFFFFFFFF);
        if ($network === $start && $broadcast <= $end) {
            $maxBits--;
        } else {
            break;
        }
    }

    $mask = (-1 << (32 - $maxBits)) & 0xFFFFFFFF;
    $broadcast = $start | (~$mask & 0xFFFFFFFF);

    $cidrs[] = [
        'cidr' => long2ip($start) . '/' . $maxBits,
        'first_ip' => long2ip($start),
        'last_ip' => long2ip($broadcast),
        'hosts' => ($maxBits == 32 ? 1 : ($maxBits == 31 ? 2 : (1 << (32 - $maxBits)) - 2))
    ];

    $start = $broadcast + 1;
    if ($start === 0) break; // overflow

    if (count($cidrs) > 1000) {
        echo json_encode(['error' => 'Range too large — produces over 1000 CIDR blocks']);
        exit;
    }
}

echo json_encode(['cidrs' => $cidrs]);
