<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$input = json_decode(file_get_contents('php://input'), true);
$query = $input['query'] ?? '';

// Sanitize: only allow alphanumeric, dots, hyphens, colons (for IPv6), slashes (for CIDR)
if (!preg_match('/^[a-zA-Z0-9.\-:\/]+$/', $query)) {
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

// Smart parser: extract all key: value pairs from RPSL/WHOIS output
// This handles RIPE, APNIC, LACNIC, ARIN, and standard domain WHOIS
$parsed = [];

// First pass: detect which registry format we're dealing with
$isRPSL = preg_match('/^(inetnum|inet6num|aut-num|route|netname):/m', $output);
$isDomain = preg_match('/Domain Name:/i', $output);
$isARIN = preg_match('/^(NetRange|NetName|OrgName):/m', $output);

if ($isDomain) {
    // Domain WHOIS — pick out the important fields
    $domainPatterns = [
        'Domain Name' => '/Domain Name:\s*(.+)/i',
        'Registrar' => '/Registrar:\s*(.+)/i',
        'Created' => '/Creat(?:ion|ed) Date:\s*(.+)/i',
        'Expires' => '/(?:Registry )?Expir(?:y|ation) Date:\s*(.+)/i',
        'Updated' => '/Updated Date:\s*(.+)/i',
        'Status' => '/(?:Domain )?Status:\s*(.+)/i',
        'Name Servers' => '/Name Server:\s*(.+)/i',
        'Registrant Org' => '/Registrant Organization:\s*(.+)/i',
        'Registrant Country' => '/Registrant Country:\s*(.+)/i',
        'DNSSEC' => '/DNSSEC:\s*(.+)/i',
    ];
    foreach ($domainPatterns as $label => $pattern) {
        if (preg_match_all($pattern, $output, $matches)) {
            $values = array_unique(array_map('trim', $matches[1]));
            if ($label === 'Name Servers') {
                $parsed[$label] = implode(', ', $values);
            } elseif ($label === 'Status') {
                // Show all statuses
                $statuses = array_map(function($s) {
                    return preg_replace('/\s*https?:\/\/\S+/', '', $s);
                }, $values);
                $parsed[$label] = implode(', ', $statuses);
            } else {
                $parsed[$label] = $values[0];
            }
        }
    }
} elseif ($isARIN) {
    // ARIN format (North American IPs)
    $arinPatterns = [
        'Net Range' => '/NetRange:\s*(.+)/i',
        'CIDR' => '/CIDR:\s*(.+)/i',
        'Net Name' => '/NetName:\s*(.+)/i',
        'Organization' => '/OrgName:\s*(.+)/i',
        'Org ID' => '/OrgId:\s*(.+)/i',
        'Address' => '/Address:\s*(.+)/i',
        'City' => '/City:\s*(.+)/i',
        'State' => '/StateProv:\s*(.+)/i',
        'Country' => '/Country:\s*(.+)/i',
        'Updated' => '/Updated:\s*(.+)/i',
        'Ref' => '/Ref:\s*(.+)/i',
    ];
    foreach ($arinPatterns as $label => $pattern) {
        if (preg_match_all($pattern, $output, $matches)) {
            $values = array_unique(array_map('trim', $matches[1]));
            $parsed[$label] = $values[0];
        }
    }
} elseif ($isRPSL) {
    // RPSL format (RIPE, APNIC, LACNIC, AfriNIC)
    // Parse all objects and their fields
    $lines = explode("\n", $output);
    $objects = [];
    $currentObj = [];
    $currentType = null;

    foreach ($lines as $line) {
        $line = rtrim($line);
        // Skip comments and empty lines
        if (empty($line) || $line[0] === '%') {
            if (!empty($currentObj)) {
                $objects[] = ['type' => $currentType, 'fields' => $currentObj];
                $currentObj = [];
                $currentType = null;
            }
            continue;
        }

        if (preg_match('/^([a-zA-Z0-9\-]+):\s*(.*)$/', $line, $m)) {
            $key = strtolower($m[1]);
            $val = trim($m[2]);

            if ($currentType === null) {
                $currentType = $key;
            }

            if (isset($currentObj[$key])) {
                $currentObj[$key] .= ', ' . $val;
            } else {
                $currentObj[$key] = $val;
            }
        }
    }
    if (!empty($currentObj)) {
        $objects[] = ['type' => $currentType, 'fields' => $currentObj];
    }

    // Extract the most useful fields from each object type
    $fieldMap = [
        'inetnum' => 'IP Range',
        'inet6num' => 'IPv6 Range',
        'netname' => 'Net Name',
        'descr' => 'Description',
        'country' => 'Country',
        'status' => 'Status',
        'org' => 'Org Handle',
        'admin-c' => 'Admin Contact',
        'tech-c' => 'Tech Contact',
        'mnt-by' => 'Maintained By',
        'created' => 'Created',
        'last-modified' => 'Updated',
        'route' => 'Route',
        'origin' => 'Origin AS',
        'org-name' => 'Organization',
        'address' => 'Address',
        'phone' => 'Phone',
        'abuse-mailbox' => 'Abuse Email',
    ];

    foreach ($objects as $obj) {
        foreach ($fieldMap as $rpslKey => $label) {
            if (isset($obj['fields'][$rpslKey]) && !isset($parsed[$label])) {
                $parsed[$label] = $obj['fields'][$rpslKey];
            }
        }
    }

    // Also look for abuse contact in RIPE comments
    if (!isset($parsed['Abuse Email']) && preg_match("/Abuse contact for .+ is '([^']+)'/", $output, $m)) {
        $parsed['Abuse Email'] = $m[1];
    }
} else {
    // Generic fallback — extract any key: value pairs
    $genericPatterns = [
        'Organization' => '/(?:OrgName|Organization|org-name|organisation):\s*(.+)/i',
        'Net Range' => '/(?:NetRange|inetnum|inet6num):\s*(.+)/i',
        'Net Name' => '/(?:NetName|netname):\s*(.+)/i',
        'CIDR' => '/CIDR:\s*(.+)/i',
        'Country' => '/(?:Country|country):\s*(.+)/i',
        'Description' => '/descr:\s*(.+)/i',
        'Status' => '/(?:Status|status):\s*(.+)/i',
        'Created' => '/(?:Creat(?:ion|ed)(?: Date)?|created):\s*(.+)/i',
        'Updated' => '/(?:Updated(?: Date)?|last-modified|changed):\s*(.+)/i',
    ];
    foreach ($genericPatterns as $label => $pattern) {
        if (preg_match($pattern, $output, $m)) {
            $parsed[$label] = trim($m[1]);
        }
    }
}

echo json_encode([
    'parsed' => $parsed,
    'raw' => $output
]);
