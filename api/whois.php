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

// Detect registry format
$isRPSL = preg_match('/^(inetnum|inet6num|aut-num|route|netname):/m', $output);
$isDomain = preg_match('/Domain Name:/i', $output);
$isARIN = preg_match('/^(NetRange|NetName|OrgName):/m', $output);

$sections = [];

if ($isDomain) {
    // --- Domain WHOIS ---
    $info = [];

    // Simple single-value fields
    $singleFields = [
        'Domain Name' => '/Domain Name:\s*(.+)/i',
        'Registrar' => '/Registrar:\s*(.+)/i',
        'Registrant Organization' => '/Registrant Organization:\s*(.+)/i',
        'Registrant Country' => '/Registrant Country:\s*(.+)/i',
        'DNSSEC' => '/DNSSEC:\s*(.+)/i',
    ];
    foreach ($singleFields as $label => $pattern) {
        if (preg_match($pattern, $output, $m)) {
            $info[$label] = trim($m[1]);
        }
    }

    // Dates
    $dates = [];
    if (preg_match('/Creat(?:ion|ed) Date:\s*(.+)/i', $output, $m)) {
        $dates['Created'] = trim($m[1]);
    }
    if (preg_match('/(?:Registry )?Expir(?:y|ation) Date:\s*(.+)/i', $output, $m)) {
        $dates['Expires'] = trim($m[1]);
    }
    if (preg_match('/Updated Date:\s*(.+)/i', $output, $m)) {
        $dates['Updated'] = trim($m[1]);
    }

    // Name servers — collect unique, case-insensitive
    $nameservers = [];
    if (preg_match_all('/Name Server:\s*(.+)/i', $output, $matches)) {
        $seen = [];
        foreach ($matches[1] as $ns) {
            $ns = trim(strtolower($ns));
            if (!isset($seen[$ns])) {
                $seen[$ns] = true;
                $nameservers[] = $ns;
            }
        }
    }

    // Statuses — deduplicate, strip URLs
    $statuses = [];
    if (preg_match_all('/(?:Domain )?Status:\s*(.+)/i', $output, $matches)) {
        $seen = [];
        foreach ($matches[1] as $s) {
            $s = trim(preg_replace('/\s*https?:\/\/\S+/', '', $s));
            // Also strip trailing parens from partial URL removal
            $s = rtrim($s, ' (');
            $lower = strtolower($s);
            if (!isset($seen[$lower]) && !empty($s)) {
                $seen[$lower] = true;
                $statuses[] = $s;
            }
        }
    }

    if ($info) {
        $sections[] = ['title' => 'Registration', 'fields' => $info];
    }
    if ($dates) {
        $sections[] = ['title' => 'Dates', 'fields' => $dates];
    }
    if ($nameservers) {
        $sections[] = ['title' => 'Name Servers', 'list' => $nameservers];
    }
    if ($statuses) {
        $sections[] = ['title' => 'Status', 'list' => $statuses];
    }

} elseif ($isARIN) {
    // --- ARIN format ---
    $net = [];
    $arinNet = [
        'Net Range' => '/NetRange:\s*(.+)/i',
        'CIDR' => '/CIDR:\s*(.+)/i',
        'Net Name' => '/NetName:\s*(.+)/i',
        'Net Type' => '/NetType:\s*(.+)/i',
        'Origin AS' => '/OriginAS:\s*(.+)/i',
    ];
    foreach ($arinNet as $label => $pattern) {
        if (preg_match($pattern, $output, $m)) {
            $val = trim($m[1]);
            if ($val) $net[$label] = $val;
        }
    }

    $org = [];
    $arinOrg = [
        'Organization' => '/OrgName:\s*(.+)/i',
        'Org ID' => '/OrgId:\s*(.+)/i',
        'Address' => '/Address:\s*(.+)/i',
        'City' => '/City:\s*(.+)/i',
        'State' => '/StateProv:\s*(.+)/i',
        'Country' => '/Country:\s*(.+)/i',
    ];
    foreach ($arinOrg as $label => $pattern) {
        if (preg_match($pattern, $output, $m)) {
            $val = trim($m[1]);
            if ($val) $org[$label] = $val;
        }
    }

    // Abuse contact
    $abuse = [];
    if (preg_match('/OrgAbuseEmail:\s*(.+)/i', $output, $m)) {
        $abuse['Abuse Email'] = trim($m[1]);
    }
    if (preg_match('/OrgAbusePhone:\s*(.+)/i', $output, $m)) {
        $abuse['Abuse Phone'] = trim($m[1]);
    }

    // Dates
    $dates = [];
    if (preg_match('/RegDate:\s*(.+)/i', $output, $m)) {
        $dates['Registered'] = trim($m[1]);
    }
    if (preg_match('/Updated:\s*(.+)/i', $output, $m)) {
        $dates['Updated'] = trim($m[1]);
    }

    if ($net) $sections[] = ['title' => 'Network', 'fields' => $net];
    if ($org) $sections[] = ['title' => 'Organization', 'fields' => $org];
    if ($abuse) $sections[] = ['title' => 'Abuse Contact', 'fields' => $abuse];
    if ($dates) $sections[] = ['title' => 'Dates', 'fields' => $dates];

} elseif ($isRPSL) {
    // --- RPSL format (RIPE, APNIC, LACNIC, AfriNIC) ---
    $lines = explode("\n", $output);
    $objects = [];
    $currentObj = [];
    $currentType = null;

    foreach ($lines as $line) {
        $line = rtrim($line);
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
            if ($currentType === null) $currentType = $key;
            if (isset($currentObj[$key])) {
                if (!is_array($currentObj[$key])) {
                    $currentObj[$key] = [$currentObj[$key]];
                }
                $currentObj[$key][] = $val;
            } else {
                $currentObj[$key] = $val;
            }
        }
    }
    if (!empty($currentObj)) {
        $objects[] = ['type' => $currentType, 'fields' => $currentObj];
    }

    // Network info from inetnum/inet6num object
    $net = [];
    $netObj = null;
    foreach ($objects as $obj) {
        if (in_array($obj['type'], ['inetnum', 'inet6num'])) { $netObj = $obj; break; }
    }
    if ($netObj) {
        $f = $netObj['fields'];
        $netMap = [
            'inetnum' => 'IP Range', 'inet6num' => 'IPv6 Range',
            'netname' => 'Net Name', 'status' => 'Status', 'country' => 'Country',
        ];
        foreach ($netMap as $k => $label) {
            if (isset($f[$k])) {
                $v = is_array($f[$k]) ? implode(', ', $f[$k]) : $f[$k];
                if ($v) $net[$label] = $v;
            }
        }
        // Description — may be multi-value
        if (isset($f['descr'])) {
            $v = is_array($f['descr']) ? implode(', ', $f['descr']) : $f['descr'];
            if ($v && $v !== ($net['Net Name'] ?? '')) $net['Description'] = $v;
        }
    }

    // Organization from role/organisation object
    $org = [];
    foreach ($objects as $obj) {
        if (in_array($obj['type'], ['role', 'organisation', 'org-name'])) {
            $f = $obj['fields'];
            if (isset($f['org-name'])) $org['Organization'] = is_array($f['org-name']) ? $f['org-name'][0] : $f['org-name'];
            if (isset($f['role'])) $org['Role'] = is_array($f['role']) ? $f['role'][0] : $f['role'];
            if (isset($f['address'])) {
                $addr = is_array($f['address']) ? $f['address'] : [$f['address']];
                $org['Address'] = implode(', ', $addr);
            }
            if (isset($f['phone'])) $org['Phone'] = is_array($f['phone']) ? $f['phone'][0] : $f['phone'];
            break;
        }
    }

    // Route info
    $route = [];
    foreach ($objects as $obj) {
        if ($obj['type'] === 'route' || $obj['type'] === 'route6') {
            $f = $obj['fields'];
            if (isset($f['route'])) $route['Route'] = is_array($f['route']) ? $f['route'][0] : $f['route'];
            if (isset($f['route6'])) $route['Route'] = is_array($f['route6']) ? $f['route6'][0] : $f['route6'];
            if (isset($f['origin'])) $route['Origin AS'] = is_array($f['origin']) ? $f['origin'][0] : $f['origin'];
            break;
        }
    }

    // Abuse contact
    $abuse = [];
    if (preg_match("/Abuse contact for .+ is '([^']+)'/", $output, $m)) {
        $abuse['Abuse Email'] = $m[1];
    }
    foreach ($objects as $obj) {
        if (isset($obj['fields']['abuse-mailbox']) && !isset($abuse['Abuse Email'])) {
            $v = $obj['fields']['abuse-mailbox'];
            $abuse['Abuse Email'] = is_array($v) ? $v[0] : $v;
        }
    }

    // Dates from net object
    $dates = [];
    if ($netObj) {
        if (isset($netObj['fields']['created'])) {
            $v = $netObj['fields']['created'];
            $dates['Created'] = is_array($v) ? $v[0] : $v;
        }
        if (isset($netObj['fields']['last-modified'])) {
            $v = $netObj['fields']['last-modified'];
            $dates['Updated'] = is_array($v) ? $v[0] : $v;
        }
    }

    // Maintainer
    $maint = [];
    if ($netObj && isset($netObj['fields']['mnt-by'])) {
        $v = $netObj['fields']['mnt-by'];
        $maint['Maintained By'] = is_array($v) ? implode(', ', $v) : $v;
    }

    if ($net) $sections[] = ['title' => 'Network', 'fields' => $net];
    if ($org) $sections[] = ['title' => 'Organization', 'fields' => $org];
    if ($route) $sections[] = ['title' => 'Routing', 'fields' => $route];
    if ($abuse) $sections[] = ['title' => 'Abuse Contact', 'fields' => $abuse];
    if ($dates) $sections[] = ['title' => 'Dates', 'fields' => $dates];
    if ($maint) $sections[] = ['title' => 'Maintenance', 'fields' => $maint];

} else {
    // --- Generic fallback ---
    $fields = [];
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
            $fields[$label] = trim($m[1]);
        }
    }
    if ($fields) $sections[] = ['title' => 'WHOIS Info', 'fields' => $fields];
}

echo json_encode([
    'sections' => $sections,
    'raw' => $output
]);
