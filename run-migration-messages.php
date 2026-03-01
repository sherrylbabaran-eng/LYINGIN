<?php
/**
 * Run migration: Add admin messages and notifications
 * Execute once from browser or CLI: php run-migration-messages.php
 */

require_once 'auth/api/security.php';

// Read migration file
$migrationFile = __DIR__ . '/database/migrations/20260301_add_admin_messages_notifications.sql';
$sql = file_get_contents($migrationFile);

// Remove comments
$sql = preg_replace('/--.*$/m', '', $sql);

// Split into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt);
    }
);

// Execute each statement
$successCount = 0;
$errorCount = 0;
$errors = [];

foreach ($statements as $statement) {
    if (mysqli_query($conn, $statement)) {
        $successCount++;
        echo "✓ Executed successfully\n";
    } else {
        $errorCount++;
        $error = mysqli_error($conn);
        $errors[] = $error;
        echo "✗ Error: " . $error . "\n";
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Migration Results:\n";
echo "  Success: $successCount\n";
echo "  Errors: $errorCount\n";

if ($errorCount > 0) {
    echo "\nErrors encountered:\n";
    foreach ($errors as $idx => $error) {
        echo "  " . ($idx + 1) . ". $error\n";
    }
}

mysqli_close($conn);
?>
