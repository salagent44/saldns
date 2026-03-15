<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? '';

// Basic URL validation
if (empty($url)) {
    echo json_encode(['error' => 'Please enter a URL']);
    exit;
}

// Add https:// if no scheme provided
if (!preg_match('/^https?:\/\//i', $url)) {
    $url = 'https://' . $url;
}

// Validate URL format
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'Invalid URL format']);
    exit;
}

// Block private/internal IPs
$host = parse_url($url, PHP_URL_HOST);
if (!$host) {
    echo json_encode(['error' => 'Could not parse host from URL']);
    exit;
}

// Sanitize: only allow safe characters in URL
if (!preg_match('/^https?:\/\/[a-zA-Z0-9.\-:\/%?&=_~+#@!$,;()\[\]]+$/', $url)) {
    echo json_encode(['error' => 'URL contains invalid characters']);
    exit;
}

if (strlen($url) > 2048) {
    echo json_encode(['error' => 'URL too long']);
    exit;
}

$safeUrl = escapeshellarg($url);

// Use curl to get headers, following redirects
$cmd = "curl -sI -L --max-time 10 --max-redirs 10 $safeUrl 2>&1";
$output = shell_exec($cmd);

if (!$output) {
    echo json_encode(['error' => 'Failed to fetch headers. The server may be unreachable.']);
    exit;
}

// Split into redirect hops (each starts with HTTP/)
$hops = [];
$currentHop = [];
$currentHeaders = [];
$statusLine = '';

$lines = explode("\n", $output);
foreach ($lines as $line) {
    $line = rtrim($line, "\r\n");

    // New HTTP response starts
    if (preg_match('/^HTTP\/[\d.]+\s+(\d+)\s*(.*)$/i', $line)) {
        // Save previous hop if exists
        if ($statusLine) {
            $hops[] = [
                'status_line' => $statusLine,
                'headers' => $currentHeaders,
            ];
        }
        $statusLine = $line;
        $currentHeaders = [];
        continue;
    }

    // Empty line separates headers from body
    if (trim($line) === '') {
        continue;
    }

    // Parse header
    if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m)) {
        $currentHeaders[] = [
            'name' => trim($m[1]),
            'value' => trim($m[2]),
        ];
    }
}

// Save last hop
if ($statusLine) {
    $hops[] = [
        'status_line' => $statusLine,
        'headers' => $currentHeaders,
    ];
}

if (empty($hops)) {
    echo json_encode(['error' => 'Could not parse response headers']);
    exit;
}

// Security headers to highlight
$securityHeaders = [
    'strict-transport-security',
    'content-security-policy',
    'content-security-policy-report-only',
    'x-frame-options',
    'x-content-type-options',
    'x-xss-protection',
    'referrer-policy',
    'permissions-policy',
    'cross-origin-opener-policy',
    'cross-origin-embedder-policy',
    'cross-origin-resource-policy',
];

echo json_encode([
    'url' => $url,
    'hops' => $hops,
    'security_headers' => $securityHeaders,
]);
