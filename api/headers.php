<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once __DIR__ . '/_security.php';

$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? '';

if (empty($url)) {
    echo json_encode(['error' => 'Please enter a URL']);
    exit;
}

// Add https:// if no scheme provided
if (!preg_match('/^https?:\/\//i', $url)) {
    $url = 'https://' . $url;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'Invalid URL format']);
    exit;
}

$host = parse_url($url, PHP_URL_HOST);
if (!$host) {
    echo json_encode(['error' => 'Could not parse host from URL']);
    exit;
}

// SSRF protection: block private/internal IPs
$resolvedIp = validate_host_public($host);
if ($resolvedIp === false) {
    block_private();
}

if (!preg_match('/^https?:\/\/[a-zA-Z0-9.\-:\/%?&=_~+#@!$,;()\[\]]+$/', $url)) {
    echo json_encode(['error' => 'URL contains invalid characters']);
    exit;
}

if (strlen($url) > 2048) {
    echo json_encode(['error' => 'URL too long']);
    exit;
}

$safeUrl = escapeshellarg($url);

// Use curl with --resolve to pin the resolved public IP (prevents DNS rebinding on redirects)
// Also block redirects to private IPs by not following redirects automatically,
// instead manually follow and validate each hop
$hops = [];
$currentUrl = $url;
$maxRedirects = 10;

for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
    $currentHost = parse_url($currentUrl, PHP_URL_HOST);
    $currentPort = parse_url($currentUrl, PHP_URL_PORT);
    $scheme = parse_url($currentUrl, PHP_URL_SCHEME);

    if (!$currentHost) break;

    // Validate each hop's host
    if ($redirect > 0) {
        $hopIp = validate_host_public($currentHost);
        if ($hopIp === false) {
            // Redirect tried to go to a private IP — stop following but show what we have
            $hops[] = [
                'status_line' => 'BLOCKED: Redirect to private/internal address',
                'headers' => [],
            ];
            break;
        }
    }

    $safeCurrent = escapeshellarg($currentUrl);
    $cmd = "curl -sI --max-time 10 $safeCurrent 2>&1";
    $output = shell_exec($cmd);

    if (!$output) break;

    $statusLine = '';
    $headers = [];
    $location = null;

    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        $line = rtrim($line, "\r\n");

        if (preg_match('/^HTTP\/[\d.]+\s+(\d+)\s*(.*)$/i', $line)) {
            $statusLine = $line;
            continue;
        }

        if (trim($line) === '') continue;

        if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m)) {
            $name = trim($m[1]);
            $value = trim($m[2]);
            $headers[] = ['name' => $name, 'value' => $value];

            if (strtolower($name) === 'location') {
                $location = $value;
            }
        }
    }

    if ($statusLine) {
        $hops[] = [
            'status_line' => $statusLine,
            'headers' => $headers,
        ];
    }

    // Follow redirect?
    if ($location && preg_match('/^HTTP\/[\d.]+\s+3\d{2}/', $statusLine)) {
        // Handle relative redirects
        if (preg_match('/^\//', $location)) {
            $defaultPort = ($scheme === 'https') ? 443 : 80;
            $portStr = ($currentPort && $currentPort != $defaultPort) ? ':' . $currentPort : '';
            $location = $scheme . '://' . $currentHost . $portStr . $location;
        }
        $currentUrl = $location;
    } else {
        break;
    }
}

if (empty($hops)) {
    echo json_encode(['error' => 'Could not parse response headers']);
    exit;
}

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
