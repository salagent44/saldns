<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$input = json_decode(file_get_contents('php://input'), true);
$query = $input['query'] ?? '';

// Sanitize: only allow alphanumeric, dots, hyphens, colons (for IPv6)
if (!preg_match('/^[a-zA-Z0-9.\-:]+$/', $query)) {
    echo json_encode(['error' => 'Invalid query. Only domains and IP addresses are allowed.']);
    exit;
}

if (strlen($query) > 253) {
    echo json_encode(['error' => 'Query too long']);
    exit;
}

$query = escapeshellarg($query);
$output = shell_exec("whois $query 2>&1");

if (empty($output)) {
    echo json_encode(['error' => 'No WHOIS data returned']);
    exit;
}

// Parse key fields from raw output
$parsed = [];
$patterns = [
    'Domain Name' => '/Domain Name:\s*(.+)/i',
    'Registrar' => '/Registrar:\s*(.+)/i',
    'Registration Date' => '/Creat(?:ion|ed) Date:\s*(.+)/i',
    'Expiry Date' => '/Expir(?:y|ation) Date:\s*(.+)/i',
    'Updated Date' => '/Updated Date:\s*(.+)/i',
    'Name Servers' => '/Name Server:\s*(.+)/i',
    'Status' => '/Status:\s*(.+)/i',
    'Registrant Org' => '/Registrant Organization:\s*(.+)/i',
    'Registrant Country' => '/Registrant Country:\s*(.+)/i',
    // IP-specific fields
    'NetName' => '/NetName:\s*(.+)/i',
    'NetRange' => '/NetRange:\s*(.+)/i',
    'CIDR' => '/CIDR:\s*(.+)/i',
    'Organization' => '/Organization:\s*(.+)/i',
    'OrgName' => '/OrgName:\s*(.+)/i',
    'Country' => '/[Cc]ountry:\s*(.+)/i',
];

foreach ($patterns as $label => $pattern) {
    if (preg_match_all($pattern, $output, $matches)) {
        $values = array_unique(array_map('trim', $matches[1]));
        if ($label === 'Name Servers') {
            $parsed[$label] = implode(', ', $values);
        } else {
            $parsed[$label] = $values[0];
        }
    }
}

echo json_encode([
    'parsed' => $parsed,
    'raw' => $output
]);
