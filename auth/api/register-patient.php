<?php
// In production hide display errors to avoid sending HTML error pages to clients
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Convert errors into exceptions (but skip warnings from external functions like mail)
// Only throw exceptions for user-defined errors and fatal errors, not warnings
set_error_handler(function($severity, $message, $file, $line) {
    // Don't throw for warnings or notices from external functions (e.g., mail())
    // Only throw for user errors (E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE)
    if ($severity & (E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_CORE_ERROR | E_COMPILE_ERROR)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false; // Let default handler process other errors
});

header('Content-Type: application/json');
session_start();

try {

// Load .env into environment if present (search common parent locations so Apache/PHP uses project .env)
$candidates = [
    realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . '.env',      // auth/.env
    realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . '.env',   // project root /.env
    __DIR__ . DIRECTORY_SEPARATOR . '.env'                         // auth/api/.env
];
$envFile = null;
foreach ($candidates as $cand) {
    if ($cand && file_exists($cand)) { $envFile = $cand; break; }
}
if ($envFile) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // strip surrounding quotes
        $val = trim($val, "'\"");
        putenv("$key=$val");
        $_ENV[$key] = $val;
    }
} else {
    error_log('No .env file found in expected locations; environment mail settings may be missing.');
}

// DB config
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "lyingin_db";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"DB Connection failed: ".$conn->connect_error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"Invalid request method."]);
    exit;
}

// --- Step 1: Personal info ---
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$birthdate  = trim($_POST['birthdate'] ?? '');
$gender     = trim($_POST['gender'] ?? '');
$email      = trim($_POST['email'] ?? '');
$contact_no = trim($_POST['contact_no'] ?? '');
$address    = trim($_POST['address'] ?? '');

// --- Step 2: Account & ID ---
$username   = trim($_POST['username'] ?? '');
$rawPass    = $_POST['password'] ?? '';
$password   = password_hash($rawPass, PASSWORD_DEFAULT);
$id_type    = trim($_POST['id_type'] ?? '');
$id_number  = trim($_POST['id_number'] ?? '');

// Normalize and validate ID helper functions
function normalize_id($type, $value) {
    $v = trim($value);
    // For numeric IDs, strip non-digits
    if (in_array($type, ['national', 'philhealth', 'sss'])) {
        return preg_replace('/\D/', '', $v);
    }
    // For others, collapse whitespace
    return preg_replace('/\s+/', '', $v);
}

function validate_id($type, $value, &$errMsg = '') {
    switch ($type) {
        case 'passport':
            if (!preg_match('/^[A-Z0-9]{6,9}$/i', $value)) {
                $errMsg = 'Passport should be 6-9 letters or numbers.';
                return false;
            }
            return true;
        case 'drivers':
            if (!preg_match('/^[A-Z0-9\-]{5,20}$/i', $value)) {
                $errMsg = "Driver's License should be 5-20 characters (letters, numbers, dashes).";
                return false;
            }
            return true;
        case 'national':
            // Accept 10-16 digits to cover various national/legacy formats
            if (!preg_match('/^\d{10,16}$/', $value)) {
                $errMsg = 'National ID should be 10 to 16 digits.';
                return false;
            }
            return true;
        case 'philhealth':
            if (!preg_match('/^\d{12,14}$/', $value)) {
                $errMsg = 'PhilHealth ID should be 12 to 14 digits.';
                return false;
            }
            return true;
        case 'sss':
            if (!preg_match('/^\d{10,12}$/', $value)) {
                $errMsg = 'SSS ID should be 10 to 12 digits.';
                return false;
            }
            return true;
        default:
            if (!preg_match('/^[A-Z0-9\- ]{5,30}$/i', $value)) {
                $errMsg = 'Invalid ID format.';
                return false;
            }
            return true;
    }
}

function normalize_id_for_ocr($type, $value) {
    $v = strtoupper(trim($value));
    if (in_array($type, ['national', 'philhealth', 'sss'])) {
        return preg_replace('/\D/', '', $v);
    }
    return preg_replace('/[^A-Z0-9]/', '', $v);
}

function normalize_ocr_text($text) {
    $t = strtoupper($text);
    return preg_replace('/[^A-Z0-9]/', '', $t);
}

function normalize_ocr_digits($text) {
    $t = normalize_ocr_text($text);
    $map = [
        'O' => '0',
        'Q' => '0',
        'I' => '1',
        'L' => '1',
        'Z' => '2',
        'S' => '5',
        'B' => '8',
        'G' => '6'
    ];
    return strtr($t, $map);
}

function extract_digit_candidates($text) {
    $candidates = [];
    if (!$text) return $candidates;

    if (preg_match_all('/\d{8,20}/', $text, $matches)) {
        foreach ($matches[0] as $m) {
            $candidates[$m] = true;
        }
    }

    $digits = preg_replace('/\D/', '', $text);
    if (strlen($digits) >= 8) {
        $candidates[$digits] = true;
    }

    return array_keys($candidates);
}

function run_ocr_tsv($imagePath) {
    $tesseract = getenv('TESSERACT_BIN');
    if (!$tesseract) {
        $tesseract = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    }
    if (!file_exists($tesseract)) {
        error_log('Tesseract not found at: ' . $tesseract);
        return null;
    }

    $base = tempnam(sys_get_temp_dir(), 'ocrt_');
    if ($base === false) return null;
    @unlink($base);

    $cmd = '"' . $tesseract . '" ' . escapeshellarg($imagePath) . ' ' . escapeshellarg($base) . ' --oem 1 --psm 6 tsv';
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    $tsvFile = $base . '.tsv';

    if ($code !== 0 || !file_exists($tsvFile)) {
        error_log('OCR tsv failed with code ' . $code . ' for file: ' . $imagePath);
        if (file_exists($tsvFile)) @unlink($tsvFile);
        return null;
    }

    $tsv = file_get_contents($tsvFile);
    @unlink($tsvFile);
    return $tsv !== false ? $tsv : null;
}

function find_id_number_roi($imagePath) {
    if (!function_exists('imagecreatefromjpeg')) return null;

    $preprocessed = preprocess_id_image($imagePath);
    $tsvText = run_ocr_tsv($preprocessed ?: $imagePath);
    if ($preprocessed && file_exists($preprocessed)) {
        @unlink($preprocessed);
    }
    if (!$tsvText) return null;

    $lines = preg_split('/\r?\n/', trim($tsvText));
    if (count($lines) < 2) return null;

    $lineMap = [];
    foreach ($lines as $i => $line) {
        if ($i === 0) continue; // header
        $cols = explode("\t", $line);
        if (count($cols) < 12) continue;
        $text = trim($cols[11] ?? '');
        if ($text === '') continue;

        $block = $cols[2];
        $par = $cols[3];
        $ln = $cols[4];
        $key = $block . '-' . $par . '-' . $ln;

        $left = (int)$cols[6];
        $top = (int)$cols[7];
        $width = (int)$cols[8];
        $height = (int)$cols[9];
        $right = $left + $width;
        $bottom = $top + $height;

        if (!isset($lineMap[$key])) {
            $lineMap[$key] = [
                'texts' => [],
                'left' => $left,
                'top' => $top,
                'right' => $right,
                'bottom' => $bottom
            ];
        }
        $lineMap[$key]['texts'][] = strtoupper($text);
        $lineMap[$key]['left'] = min($lineMap[$key]['left'], $left);
        $lineMap[$key]['top'] = min($lineMap[$key]['top'], $top);
        $lineMap[$key]['right'] = max($lineMap[$key]['right'], $right);
        $lineMap[$key]['bottom'] = max($lineMap[$key]['bottom'], $bottom);
    }

    foreach ($lineMap as $line) {
        $lineText = implode(' ', $line['texts']);
        if (strpos($lineText, 'ID') !== false && (strpos($lineText, 'NUMBER') !== false || strpos($lineText, 'NO') !== false || strpos($lineText, 'NUM') !== false)) {
            $imgSize = @getimagesize($imagePath);
            if (!$imgSize) return null;
            $imgW = $imgSize[0];
            $imgH = $imgSize[1];

            $x = max(0, $line['left']);
            $y = min($imgH - 1, $line['bottom'] + 5);
            $w = $imgW - $x;
            $h = min((int)round(($line['bottom'] - $line['top']) * 2.5), $imgH - $y);

            if ($w > 50 && $h > 20) {
                return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
            }
        }
    }

    return null;
}

function crop_image_region($imagePath, $roi) {
    $mime = mime_content_type($imagePath);
    if ($mime === 'image/jpeg') {
        $img = @imagecreatefromjpeg($imagePath);
    } elseif ($mime === 'image/png') {
        $img = @imagecreatefrompng($imagePath);
    } else {
        return null;
    }
    if (!$img) return null;

    $crop = imagecreatetruecolor($roi['w'], $roi['h']);
    imagecopy($crop, $img, 0, 0, $roi['x'], $roi['y'], $roi['w'], $roi['h']);
    imagedestroy($img);

    $tmp = tempnam(sys_get_temp_dir(), 'ocrroi_');
    if (!$tmp) {
        imagedestroy($crop);
        return null;
    }
    $tmpPng = $tmp . '.png';
    @unlink($tmp);
    imagepng($crop, $tmpPng, 9);
    imagedestroy($crop);
    return $tmpPng;
}

function best_digit_distance($digits, $target) {
    $digits = preg_replace('/\D/', '', $digits);
    $target = preg_replace('/\D/', '', $target);
    $len = strlen($target);
    if ($len === 0 || strlen($digits) < $len) return null;

    $best = null;
    for ($i = 0; $i <= strlen($digits) - $len; $i++) {
        $window = substr($digits, $i, $len);
        $dist = 0;
        for ($j = 0; $j < $len; $j++) {
            if ($window[$j] !== $target[$j]) $dist++;
        }
        if ($best === null || $dist < $best) $best = $dist;
        if ($best === 0) return 0;
    }
    return $best;
}

function run_ocr_text($imagePath) {
    $tesseract = getenv('TESSERACT_BIN');
    if (!$tesseract) {
        $tesseract = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    }
    if (!file_exists($tesseract)) {
        error_log('Tesseract not found at: ' . $tesseract);
        return null;
    }

    $base = tempnam(sys_get_temp_dir(), 'ocr_');
    if ($base === false) return null;
    // Tesseract expects output base without extension
    @unlink($base);

    $cmd = '"' . $tesseract . '" ' . escapeshellarg($imagePath) . ' ' . escapeshellarg($base) . ' --oem 1 --psm 6';
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    $txtFile = $base . '.txt';

    if ($code !== 0 || !file_exists($txtFile)) {
        error_log('OCR failed with code ' . $code . ' for file: ' . $imagePath);
        if (file_exists($txtFile)) @unlink($txtFile);
        return null;
    }

    $text = file_get_contents($txtFile);
    @unlink($txtFile);
    return $text !== false ? $text : null;
}

function run_ocr_digits($imagePath, $psm = 7) {
    $tesseract = getenv('TESSERACT_BIN');
    if (!$tesseract) {
        $tesseract = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    }
    if (!file_exists($tesseract)) {
        error_log('Tesseract not found at: ' . $tesseract);
        return null;
    }

    $preprocessed = preprocess_id_image($imagePath);
    $inputPath = $preprocessed ?: $imagePath;

    $base = tempnam(sys_get_temp_dir(), 'ocrd_');
    if ($base === false) return null;
    @unlink($base);

    $cmd = '"' . $tesseract . '" ' . escapeshellarg($inputPath) . ' ' . escapeshellarg($base) . ' --oem 1 --psm ' . (int)$psm . ' -c tessedit_char_whitelist=0123456789 -c classify_bln_numeric_mode=1 -c user_defined_dpi=300';
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    $txtFile = $base . '.txt';

    if ($code !== 0 || !file_exists($txtFile)) {
        error_log('OCR digits failed with code ' . $code . ' for file: ' . $imagePath);
        if (file_exists($txtFile)) @unlink($txtFile);
        return null;
    }

    $text = file_get_contents($txtFile);
    @unlink($txtFile);
    if ($preprocessed && file_exists($preprocessed)) {
        @unlink($preprocessed);
    }
    return $text !== false ? $text : null;
}

function id_marker_present($idType, $ocrText) {
    $t = strtoupper($ocrText);
    switch ($idType) {
        case 'passport':
            return (strpos($t, 'PASSPORT') !== false);
        case 'drivers':
            return (strpos($t, 'DRIVER') !== false || strpos($t, 'LICENSE') !== false || strpos($t, 'LICENCE') !== false);
        case 'national':
            return (strpos($t, 'PHILIPPINES') !== false || strpos($t, 'PAMBANSANG') !== false || strpos($t, 'REPUBLIKA') !== false);
        case 'philhealth':
            return (strpos($t, 'PHILHEALTH') !== false || strpos($t, 'PHIL HEALTH') !== false);
        case 'sss':
            return (strpos($t, 'SSS') !== false || strpos($t, 'SOCIAL SECURITY') !== false);
        default:
            return true;
    }
}

function preprocess_id_image($imagePath) {
    if (!function_exists('imagecreatefromjpeg')) return null;

    $mime = mime_content_type($imagePath);
    if ($mime === 'image/jpeg') {
        $img = @imagecreatefromjpeg($imagePath);
    } elseif ($mime === 'image/png') {
        $img = @imagecreatefrompng($imagePath);
    } else {
        return null;
    }
    if (!$img) return null;

    $w = imagesx($img);
    $h = imagesy($img);
    $targetW = $w < 1400 ? 1400 : $w;
    $scale = $targetW / $w;
    $targetH = (int)round($h * $scale);

    $resized = imagecreatetruecolor($targetW, $targetH);
    imagecopyresampled($resized, $img, 0, 0, 0, 0, $targetW, $targetH, $w, $h);
    imagedestroy($img);

    imagefilter($resized, IMG_FILTER_GRAYSCALE);
    imagefilter($resized, IMG_FILTER_CONTRAST, -30);
    imagefilter($resized, IMG_FILTER_BRIGHTNESS, 10);

    // Simple thresholding to boost digit contrast
    $black = imagecolorallocate($resized, 0, 0, 0);
    $white = imagecolorallocate($resized, 255, 255, 255);
    for ($y = 0; $y < $targetH; $y++) {
        for ($x = 0; $x < $targetW; $x++) {
            $rgb = imagecolorat($resized, $x, $y);
            $val = $rgb & 0xFF;
            imagesetpixel($resized, $x, $y, ($val > 155) ? $white : $black);
        }
    }

    $tmp = tempnam(sys_get_temp_dir(), 'ocrp_');
    if (!$tmp) {
        imagedestroy($resized);
        return null;
    }
    $tmpPng = $tmp . '.png';
    @unlink($tmp);
    imagepng($resized, $tmpPng, 9);
    imagedestroy($resized);
    return $tmpPng;
}

// If username is not provided, derive one from email local-part or generate a fallback
if (empty($username)) {
    $local = strstr($email, '@', true);
    $candidate = $local ? preg_replace('/[^A-Za-z0-9._-]/', '', $local) : 'user_' . uniqid();
    // Ensure uniqueness
    $check = $conn->prepare("SELECT COUNT(*) as cnt FROM RegPatient WHERE username = ?");
    if (!$check) {
        error_log('DB prepare failed (username check): ' . $conn->error);
        echo json_encode(["status"=>"error","message"=>"Server error. Please try again later."]); exit;
    }
    $check->bind_param('s', $candidate);
    $check->execute();
    $res = $check->get_result()->fetch_assoc();
    if ($res && $res['cnt'] > 0) {
        $candidate .= '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
    $username = $candidate;
}

// --- Validate required fields ---
if (
    !$first_name || !$last_name || !$birthdate || !$gender || !$email ||
    !$contact_no || !$address || !$rawPass || !$id_type || !$id_number
) {
    echo json_encode(["status"=>"error","message"=>"All fields are required."]);
    exit;
}

// Normalize and validate ID server-side
$normalized_id = normalize_id($id_type, $id_number);
$errMsg = '';
if (!validate_id($id_type, $normalized_id, $errMsg)) {
    echo json_encode(["status"=>"error","message"=>"Invalid ID: $errMsg"]);
    exit;
}
// Replace with canonical normalized value to store in DB
$id_number = $normalized_id;

// --- Handle file upload ---
if (!isset($_FILES['idFile']) || $_FILES['idFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status"=>"error","message"=>"Please upload a valid ID file."]);
    exit;
}

$allowedTypes = ['image/jpeg','image/png','application/pdf'];
$fileType = mime_content_type($_FILES['idFile']['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(["status"=>"error","message"=>"Invalid file type. Only JPG, PNG, PDF allowed."]);
    exit;
}

// OCR-based ID number matching (images only)
if ($fileType === 'application/pdf') {
    echo json_encode(["status"=>"error","message"=>"Please upload an image of your ID so we can verify the ID number."]);
    exit;
}

$ocrText = run_ocr_text($_FILES['idFile']['tmp_name']);
if ($ocrText === null) {
    echo json_encode(["status"=>"error","message"=>"Unable to read the ID image. Please upload a clearer photo."]);
    exit;
}

if (!id_marker_present($id_type, $ocrText)) {
    echo json_encode(["status"=>"error","message"=>"Uploaded image does not look like a valid ID. Please upload the correct ID."]);
    exit;
}

$ocrDigitsSource = $_FILES['idFile']['tmp_name'];
$ocrDigitsTemp = null;
if (!empty($_POST['id_crop'])) {
    $crop = $_POST['id_crop'];
    if (preg_match('/^data:image\/(png|jpeg);base64,/', $crop)) {
        $crop = substr($crop, strpos($crop, ',') + 1);
        $cropData = base64_decode($crop, true);
        if ($cropData !== false) {
            $ocrDigitsTemp = tempnam(sys_get_temp_dir(), 'idcrop_');
            if ($ocrDigitsTemp) {
                file_put_contents($ocrDigitsTemp, $cropData);
                $ocrDigitsSource = $ocrDigitsTemp;
            }
        }
    }
}

$ocrDigitsText7 = run_ocr_digits($ocrDigitsSource, 7);
$ocrDigitsText6 = run_ocr_digits($ocrDigitsSource, 6);
$ocrDigitsText11 = run_ocr_digits($ocrDigitsSource, 11);

$roiDigitsText = null;
$roiTemp = null;
$roi = find_id_number_roi($_FILES['idFile']['tmp_name']);
if ($roi) {
    $roiTemp = crop_image_region($_FILES['idFile']['tmp_name'], $roi);
    if ($roiTemp) {
        $roiDigitsText = run_ocr_digits($roiTemp, 7);
        @unlink($roiTemp);
    }
}

$ocrNormalized = normalize_ocr_text($ocrText);
$ocrDigits = normalize_ocr_digits($ocrText);

$digitCandidates = [];
foreach ([$ocrDigitsText7, $ocrDigitsText6, $ocrDigitsText11, $roiDigitsText, $ocrText] as $txt) {
    foreach (extract_digit_candidates($txt) as $cand) {
        $digitCandidates[$cand] = true;
    }
}

if ($ocrDigitsTemp && file_exists($ocrDigitsTemp)) {
    @unlink($ocrDigitsTemp);
}

$ocrDigitsOnly = '';
foreach (array_keys($digitCandidates) as $cand) {
    if (strlen($cand) > strlen($ocrDigitsOnly)) {
        $ocrDigitsOnly = $cand;
    }
}
$idNormalized = normalize_id_for_ocr($id_type, $id_number);

if (in_array($id_type, ['national', 'philhealth', 'sss']) && strlen($ocrDigitsOnly) < 6) {
    // Log digits-only output for debugging
    $debugDir = __DIR__ . '/../logs/ocr/';
    if (!is_dir($debugDir)) {
        @mkdir($debugDir, 0755, true);
    }
    $debugFile = $debugDir . date('Ymd_His') . '_ocr_digits_only.txt';
    $debugPayload = "ID_TYPE={$id_type}\n" .
        "ID_INPUT={$id_number}\n" .
        "OCR_DIGITS_ONLY={$ocrDigitsOnly}\n";
    @file_put_contents($debugFile, $debugPayload);

    echo json_encode(["status"=>"error","message"=>"Unable to read the ID number clearly. Please upload a clearer ID photo."]);
    exit;
}

$match = $idNormalized && (strpos($ocrNormalized, $idNormalized) !== false);
if (!$match && in_array($id_type, ['national', 'philhealth', 'sss'])) {
    $match = isset($digitCandidates[$idNormalized]);
}

if (!$match && in_array($id_type, ['national', 'philhealth', 'sss'])) {
    $bestDist = best_digit_distance($ocrDigitsOnly, $idNormalized);
    if ($bestDist !== null && $bestDist <= 2) {
        $match = true;
    }
}

if (!$match) {
    // Temporary OCR debug log to diagnose mismatches
    $debugDir = __DIR__ . '/../logs/ocr/';
    if (!is_dir($debugDir)) {
        @mkdir($debugDir, 0755, true);
    }
    $debugFile = $debugDir . date('Ymd_His') . '_ocr_debug.txt';
    $bestDistLog = isset($bestDist) ? $bestDist : 'n/a';
    $debugPayload = "ID_TYPE={$id_type}\n" .
        "ID_INPUT={$id_number}\n" .
        "ID_NORMALIZED={$idNormalized}\n" .
        "OCR_NORMALIZED={$ocrNormalized}\n" .
        "OCR_DIGITS={$ocrDigits}\n" .
        "OCR_DIGITS_ONLY={$ocrDigitsOnly}\n" .
        "OCR_BEST_DISTANCE={$bestDistLog}\n" .
        "OCR_RAW=\n" . $ocrText . "\n";
    @file_put_contents($debugFile, $debugPayload);

    echo json_encode(["status"=>"error","message"=>"The ID number does not match the uploaded ID or could not be read. Please upload a clearer photo of the correct ID."]);
    exit;
}

$targetDir = __DIR__ . "/../../uploads/patients/";
if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

$fileName = uniqid("id_", true) . "_" . basename($_FILES['idFile']['name']);
$id_file_path = $targetDir . $fileName;

if (!move_uploaded_file($_FILES['idFile']['tmp_name'], $id_file_path)) {
    echo json_encode(["status"=>"error","message"=>"Failed to upload ID file."]);
    exit;
}

// --- Insert into DB ---
// First, check if an unapproved/failed registration exists for this email and clean it up
$cleanup_stmt = $conn->prepare("DELETE FROM RegPatient WHERE email = ? AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
if ($cleanup_stmt) {
    $cleanup_stmt->bind_param("s", $email);
    $cleanup_stmt->execute();
    $cleanup_stmt->close();
}

// Only mark face_verified if client explicitly confirmed face verification; defaults to 0
$face_verified = (isset($_POST['face_verified']) && $_POST['face_verified'] == '1') ? 1 : 0;

// Use OTP session verification and skip post-registration email verification
$session_email = $_SESSION['verified_email'] ?? '';
$email_verified = ($session_email && strcasecmp($session_email, $email) === 0) ? 1 : 0;
if (!$email_verified) {
    echo json_encode(["status" => "error", "message" => "Please verify your email with OTP before registering."]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO RegPatient
    (first_name, last_name, birthdate, gender, email, contact_no, address,
     username, password, id_type, id_number, id_file, face_verified, email_verified)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    error_log('DB prepare failed (insert): ' . $conn->error);
    echo json_encode(["status"=>"error","message"=>"Server error. Please try again later."]); exit;
}

$stmt->bind_param("ssssssssssssii",
    $first_name,
    $last_name,
    $birthdate,
    $gender,
    $email,
    $contact_no,
    $address,
    $username,
    $password,
    $id_type,
    $id_number,
    $id_file_path,
    $face_verified,
    $email_verified
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Patient registered successfully!",
        "redirect" => "/auth/login.html"
    ]);
} else {
    // Handle INSERT errors, especially duplicate token issues
    $error = $stmt->error;
    error_log('Registration INSERT failed: ' . $error . ' for email: ' . $email);
    
    // Check if it's a duplicate token error (shouldn't happen, but cleanup doesn't always catch it)
    if (strpos($error, 'email_verification_token') !== false && strpos($error, 'Duplicate') !== false) {
        // Force cleanup: delete any existing records with same email and token
        $force_cleanup = $conn->prepare("DELETE FROM RegPatient WHERE email = ?");
        if ($force_cleanup) {
            $force_cleanup->bind_param("s", $email);
            $force_cleanup->execute();
            $force_cleanup->close();
        }
        echo json_encode(["status"=>"error","message"=>"A previous registration attempt exists for this email. Please try registering again."]);
        exit;
    }
    
    // Check for duplicate email registration
    if (strpos($error, 'email') !== false && strpos($error, 'Duplicate') !== false) {
        echo json_encode(["status"=>"error","message"=>"This email is already registered. Please use a different email or log in."]);
        exit;
    }
    
    echo json_encode(["status"=>"error","message"=>"DB Error: ".$error]);
}

$stmt->close();
$conn->close();
} catch (Throwable $e) {
    error_log('Register-patient error: ' . $e->getMessage() . " in " . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>"Server error: " . $e->getMessage()]);
    exit;
}

/**
 * Send Email via Gmail SMTP (Real Gmail)
 */
function sendVerificationEmail($to_email, $subject, $html_body) {
    // 1) Try SendGrid API if configured
    $sendgridKey = getenv('SENDGRID_API_KEY');
    if ($sendgridKey) {
        $payload = [
            'personalizations' => [[
                'to' => [[ 'email' => $to_email ]]
            ]],
            'from' => ['email' => getenv('MAIL_FROM') ?: 'noreply@lyinginclinic.com'],
            'subject' => $subject,
            'content' => [[ 'type' => 'text/html', 'value' => $html_body ]]
        ];

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $sendgridKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($code === 202) return 'OK';
        return 'sendgrid_failed: ' . ($curlErr ?: $resp ?: 'unexpected_code_' . $code);
    }

    // 2) Try SMTP if credentials provided
    $smtpHost = getenv('MAIL_SMTP_HOST');
    $smtpUser = getenv('MAIL_USERNAME');
    $smtpPass = getenv('MAIL_PASSWORD');
    $smtpPort = getenv('MAIL_SMTP_PORT') ?: 587;
    $smtpSecure = getenv('MAIL_SMTP_SECURE') ?: 'tls';
    $mailFrom = getenv('MAIL_FROM') ?: 'noreply@lyinginclinic.com';

    if ($smtpHost && $smtpUser && $smtpPass) {
        $transport = ($smtpSecure === 'ssl') ? 'ssl://' : '';
        $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $socket = @stream_socket_client($transport . $smtpHost . ':' . $smtpPort, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) return "socket_error: $errstr ($errno)";

        stream_set_timeout($socket, 5);
        $banner = fgets($socket);
        if ($banner === false) { fclose($socket); return 'smtp_no_banner'; }

        fputs($socket, "EHLO localhost\r\n");
        // Read server responses (skip detailed parsing)
        while (($line = fgets($socket)) !== false) {
            if (substr($line, 3, 1) !== '-') break;
            if (substr($line, 0, 3) == '250') break;
        }

        if ($smtpSecure === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $startResp = fgets($socket);
            if (strpos($startResp, '220') === false) { fclose($socket); return 'starttls_failed: ' . trim($startResp); }
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO localhost\r\n");
            fgets($socket);
        }

        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket);
        fputs($socket, base64_encode($smtpUser) . "\r\n");
        fgets($socket);
        fputs($socket, base64_encode($smtpPass) . "\r\n");
        $authResp = fgets($socket);
        if (strpos($authResp, '235') === false) { fclose($socket); return 'auth_failed: ' . trim($authResp); }

        fputs($socket, "MAIL FROM:<$mailFrom>\r\n"); fgets($socket);
        fputs($socket, "RCPT TO:<$to_email>\r\n"); $rcptResp = fgets($socket);
        if (strpos($rcptResp, '250') === false && strpos($rcptResp, '251') === false) { fclose($socket); return 'rcpt_failed: ' . trim($rcptResp); }
        fputs($socket, "DATA\r\n"); fgets($socket);

        $headers = "From: LYINGIN <$mailFrom>\r\n";
        $headers .= "To: $to_email\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";

        fputs($socket, $headers . $html_body . "\r\n.\r\n");
        $dataResp = fgets($socket);
        if (strpos($dataResp, '250') === false) { fclose($socket); return 'data_failed: ' . trim($dataResp); }
        fputs($socket, "QUIT\r\n"); fclose($socket);
        return 'OK';
    }

    // 3) Try PHP mail() if available
    if (function_exists('mail')) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: " . $mailFrom . "\r\n";
        // Suppress warnings from mail() - it will still return false if it fails
        $sent = @mail($to_email, $subject, $html_body, $headers);
        return $sent ? 'OK' : 'mail_failed';
    }

    return 'no_mail_transport_configured';
}

/**
 * Save the email to server logs (useful for development when SMTP is blocked)
 */
function saveEmailToFile($to_email, $subject, $html_body) {
    $dir = __DIR__ . "/../logs/emails/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $dir . date('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $to_email) . '.html';
    $content = "<!-- To: $to_email | Subject: $subject | Generated: " . date('c') . " -->\n" . $html_body;
    file_put_contents($file, $content);
    return $file;
}
?>
