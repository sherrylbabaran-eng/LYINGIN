<?php
// Simple server-side proxy for Nominatim reverse geocoding
// This avoids browser CORS issues by letting the server request Nominatim
// IMPORTANT: Respect Nominatim usage policy (rate limits, identify with User-Agent/email)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$lat = isset($_GET['lat']) ? $_GET['lat'] : null;
$lon = isset($_GET['lon']) ? $_GET['lon'] : null;

if (!$lat || !$lon) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lat or lon']);
    exit;
}

$lat = filter_var($lat, FILTER_VALIDATE_FLOAT);
$lon = filter_var($lon, FILTER_VALIDATE_FLOAT);

if ($lat === false || $lon === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' . urlencode($lat) . '&lon=' . urlencode($lon) . '&zoom=18&addressdetails=1';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Per Nominatim policy, include a descriptive User-Agent (and email if needed)
curl_setopt($ch, CURLOPT_USERAGENT, 'LYINGIN-App/1.0 (admin@yourdomain.example)');
curl_setopt($ch, CURLOPT_TIMEOUT, 6);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to retrieve data from Nominatim', 'curl_error' => $curlErr]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo $response;
    exit;
}

// Relay Nominatim response directly
echo $response;
