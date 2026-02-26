<?php
// Server-side proxy for Nominatim reverse geocoding.
// Handles transient upstream failures with retries and logs diagnostics.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function respondJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function logReverseGeocodeError(string $message, array $context = []): void
{
    $logPath = __DIR__ . '/../logs/error.log';
    $line = sprintf(
        "[%s] [reverse-geocode] %s %s\n",
        date('d-M-Y H:i:s T'),
        $message,
        $context ? json_encode($context) : ''
    );
    @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}

$latRaw = $_GET['lat'] ?? null;
$lonRaw = $_GET['lon'] ?? null;

if ($latRaw === null || $lonRaw === null || $latRaw === '' || $lonRaw === '') {
    respondJson(400, ['error' => 'Missing lat or lon']);
}

$lat = filter_var($latRaw, FILTER_VALIDATE_FLOAT);
$lon = filter_var($lonRaw, FILTER_VALIDATE_FLOAT);

if ($lat === false || $lon === false || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    respondJson(400, ['error' => 'Invalid coordinates']);
}

$query = [
    'format' => 'json',
    'lat' => $lat,
    'lon' => $lon,
    'zoom' => 18,
    'addressdetails' => 1,
    'namedetails' => 1,
    'extratags' => 1
];

$zoomRaw = $_GET['zoom'] ?? null;
if ($zoomRaw !== null && $zoomRaw !== '') {
    $zoom = filter_var($zoomRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 3, 'max_range' => 18]]);
    if ($zoom !== false) {
        $query['zoom'] = $zoom;
    }
}

$langRaw = $_GET['lang'] ?? null;
if (is_string($langRaw) && trim($langRaw) !== '') {
    $query['accept-language'] = trim($langRaw);
}

$contactEmail = getenv('NOMINATIM_EMAIL');
if (is_string($contactEmail) && trim($contactEmail) !== '') {
    $query['email'] = trim($contactEmail);
}

$url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query($query);
$userAgent = getenv('NOMINATIM_USER_AGENT') ?: 'LYINGIN-App/1.0 (local-dev@localhost)';

$maxAttempts = 3;
$attempt = 0;
$lastCurlErr = '';
$lastHttpCode = 0;
$lastBody = '';

while ($attempt < $maxAttempts) {
    $attempt++;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrNo = curl_errno($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $lastHttpCode = $httpCode;
    $lastCurlErr = $curlErr;
    $lastBody = is_string($body) ? $body : '';

    if ($body !== false && $httpCode === 200) {
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            logReverseGeocodeError('Invalid JSON from upstream', [
                'attempt' => $attempt,
                'lat' => $lat,
                'lon' => $lon,
                'json_error' => json_last_error_msg()
            ]);
        } else {
            echo $body;
            exit;
        }
    }

    $shouldRetry = ($curlErrNo !== 0) || $httpCode === 429 || ($httpCode >= 500 && $httpCode <= 599);
    if ($shouldRetry && $attempt < $maxAttempts) {
        usleep($attempt * 350000);
        continue;
    }

    break;
}

logReverseGeocodeError('Upstream reverse geocode failed', [
    'lat' => $lat,
    'lon' => $lon,
    'http_code' => $lastHttpCode,
    'curl_error' => $lastCurlErr,
    'response_excerpt' => substr($lastBody, 0, 400)
]);

respondJson(502, [
    'error' => 'Failed to retrieve data from Nominatim',
    'upstream_code' => $lastHttpCode ?: null
]);
