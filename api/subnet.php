<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$input = json_decode(file_get_contents('php://input'), true);
$cidr = $input['cidr'] ?? '';
$targetPrefix = intval($input['prefix'] ?? 24);

// Validate CIDR
if (!preg_match('/^(\d{1,3}\.){3}\d{1,3}\/(\d{1,2})$/', $cidr)) {
    echo json_encode(['error' => 'Invalid CIDR format. Use format like 10.0.0.0/16']);
    exit;
}

list($ip, $prefix) = explode('/', $cidr);
$prefix = intval($prefix);

if ($prefix < 0 || $prefix > 32) {
    echo json_encode(['error' => 'Prefix must be between 0 and 32']);
    exit;
}

if ($targetPrefix <= $prefix || $targetPrefix > 32) {
    echo json_encode(['error' => "Target prefix must be between " . ($prefix + 1) . " and 32"]);
    exit;
}

if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    echo json_encode(['error' => 'Invalid IP address']);
    exit;
}

// Cap output to prevent browser overload
$numSubnets = pow(2, $targetPrefix - $prefix);
if ($numSubnets > 65536) {
    echo json_encode(['error' => "Too many subnets ($numSubnets). Max is 65,536. Try a larger target prefix."]);
    exit;
}

$ipLong = ip2long($ip);
$networkMask = (-1 << (32 - $prefix)) & 0xFFFFFFFF;
$networkAddr = $ipLong & $networkMask;

$subnetSize = pow(2, 32 - $targetPrefix);
$subnets = [];

for ($i = 0; $i < $numSubnets; $i++) {
    $subnetStart = $networkAddr + ($i * $subnetSize);
    $subnetEnd = $subnetStart + $subnetSize - 1;
    $hosts = $subnetSize - 2;
    if ($hosts < 0) $hosts = 0;
    if ($targetPrefix === 32) $hosts = 1;
    if ($targetPrefix === 31) $hosts = 2;

    $subnets[] = [
        'cidr' => long2ip($subnetStart) . '/' . $targetPrefix,
        'first_ip' => long2ip($subnetStart),
        'last_ip' => long2ip($subnetEnd),
        'hosts' => $hosts
    ];
}

echo json_encode(['subnets' => $subnets]);
