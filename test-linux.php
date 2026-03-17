<?php
/**
 * Linux Hosting Environment Test
 * Upload this file to test your new Linux hosting setup
 */

header('Content-Type: text/html; charset=utf-8');
echo "<h1>LastFallBack.org - Linux Hosting Test</h1>\n";

// Test 1: PHP Version
echo "<h2>PHP Environment</h2>\n";
echo "PHP Version: <strong>" . PHP_VERSION . "</strong><br>\n";
echo "OS: <strong>" . PHP_OS . "</strong><br>\n";

// Test 2: Required Extensions
echo "<h2>Required Extensions</h2>\n";
$extensions = ['curl', 'json'];
foreach ($extensions as $ext) {
    $status = extension_loaded($ext) ? '✅ Loaded' : '❌ Missing';
    echo "{$ext}: <strong>{$status}</strong><br>\n";
}

// Test 3: Directory Permissions
echo "<h2>Directory Tests</h2>\n";

// Test current directory
$writable = is_writable(__DIR__) ? '✅ Writable' : '❌ Not writable';
echo "Current directory: <strong>{$writable}</strong><br>\n";

// Test submissions directory
$submissions_dir = __DIR__ . '/submissions/';
if (!is_dir($submissions_dir)) {
    mkdir($submissions_dir, 0755, true);
}
$sub_writable = is_writable($submissions_dir) ? '✅ Writable' : '❌ Not writable';
echo "Submissions directory: <strong>{$sub_writable}</strong><br>\n";

// Test temp directory for rate limiting
$temp_dir = sys_get_temp_dir();
$temp_writable = is_writable($temp_dir) ? '✅ Writable' : '❌ Not writable';
echo "System temp directory: <strong>{$temp_writable}</strong> ({$temp_dir})<br>\n";

// Test 4: Brevo API Test (basic curl test)
echo "<h2>Network/API Test</h2>\n";
if (function_exists('curl_init')) {
    $ch = curl_init('https://api.brevo.com/v3/account');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($result !== false && $http_code > 0) {
        echo "Brevo API connectivity: <strong>✅ Reachable</strong> (HTTP {$http_code})<br>\n";
    } else {
        echo "Brevo API connectivity: <strong>❌ Failed</strong><br>\n";
    }
} else {
    echo "cURL: <strong>❌ Not available</strong><br>\n";
}

// Test 5: File creation test
echo "<h2>File Operations Test</h2>\n";
$test_file = $submissions_dir . 'test_write.tmp';
if (file_put_contents($test_file, 'test') !== false) {
    unlink($test_file);
    echo "File creation: <strong>✅ Success</strong><br>\n";
} else {
    echo "File creation: <strong>❌ Failed</strong><br>\n";
}

echo "<hr>\n";
echo "<p><strong>Next steps:</strong> If all tests pass, your form submission should work. Delete this test file when done.</p>\n";
echo "<p><em>Test completed at: " . date('Y-m-d H:i:s T') . "</em></p>\n";
?>