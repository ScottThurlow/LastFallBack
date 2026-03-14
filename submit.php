<?php
/**
 * Last Fall Back Act — Form Submission Handler
 * Emails interest form data to signers@lastfallback.org
 * Place this file in the same directory as index.html on your Plesk server
 */

// ── Configuration ──────────────────────────────────────
define('TO_EMAIL',      'signers@lastfallback.org');
define('FROM_EMAIL',    'noreply@lastfallback.org');   // must be on your domain
define('SUBJECT',       'Last Fall Back Act — New Interest Form Submission');
define('ALLOWED_ORIGIN', 'https://lastfallback.org');  // update to your domain

// ── CORS / Origin check ────────────────────────────────
header('Content-Type: application/json');

// Allow same-origin and your domain
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed = [
    'https://lastfallback.org',
    'https://www.lastfallback.org',
    'http://localhost',     // for local testing
    'http://127.0.0.1',
];
if (in_array($origin, $allowed)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Only accept POST ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Rate limiting (simple file-based) ─────────────────
// Prevents a single IP from flooding submissions
$rate_limit_dir = sys_get_temp_dir() . '/lfba_rl/';
if (!is_dir($rate_limit_dir)) {
    mkdir($rate_limit_dir, 0700, true);
}
$ip_hash = md5($_SERVER['REMOTE_ADDR']);
$rate_file = $rate_limit_dir . $ip_hash . '.txt';
$now = time();
$window = 3600;   // 1 hour window
$max_submissions = 5;

if (file_exists($rate_file)) {
    $data = json_decode(file_get_contents($rate_file), true);
    // Remove entries outside the window
    $data['times'] = array_filter($data['times'], fn($t) => ($now - $t) < $window);
    if (count($data['times']) >= $max_submissions) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many submissions. Please try again later.']);
        exit;
    }
    $data['times'][] = $now;
} else {
    $data = ['times' => [$now]];
}
file_put_contents($rate_file, json_encode($data));

// ── Parse JSON body ────────────────────────────────────
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body) {
    // Fall back to POST form data
    $body = $_POST;
}

// ── Sanitize helper ────────────────────────────────────
function clean($val) {
    if (!isset($val)) return '';
    // Strip any newlines from fields that could enable header injection
    $val = str_replace(["\r", "\n", "\t"], ' ', $val);
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

// ── Extract and validate fields ────────────────────────
$firstName  = clean($body['firstName'] ?? '');
$lastName   = clean($body['lastName']  ?? '');
$email      = filter_var(trim($body['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$city       = clean($body['city']      ?? '');
$waVoter    = clean($body['waVoter']   ?? 'No');
$wantsUpdates = clean($body['wantsUpdates'] ?? 'No');
$timestamp  = date('Y-m-d H:i:s T');
$ip         = $_SERVER['REMOTE_ADDR'];

// Required field validation
if (empty($firstName) || empty($lastName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'First and last name are required.']);
    exit;
}
if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid email address is required.']);
    exit;
}

// ── Honeypot check (spam trap) ─────────────────────────
// The HTML form has a hidden field named "website" — real users leave it blank
$honeypot = trim($body['website'] ?? '');
if (!empty($honeypot)) {
    // Silently accept but don't send — looks like success to bots
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

// ── Compose email ──────────────────────────────────────
$to      = TO_EMAIL;
$subject = SUBJECT;

$message  = "New interest form submission — Last Fall Back Act\n";
$message .= str_repeat('─', 50) . "\n\n";
$message .= "Name:           {$firstName} {$lastName}\n";
$message .= "Email:          {$email}\n";
$message .= "City:           " . ($city ?: '(not provided)') . "\n";
$message .= "WA Voter:       {$waVoter}\n";
$message .= "Wants Updates:  {$wantsUpdates}\n";
$message .= "\n";
$message .= str_repeat('─', 50) . "\n";
$message .= "Submitted:      {$timestamp}\n";
$message .= "IP Address:     {$ip}\n";
$message .= str_repeat('─', 50) . "\n";

$headers  = "From: Last Fall Back Act <" . FROM_EMAIL . ">\r\n";
$headers .= "Reply-To: {$firstName} {$lastName} <{$email}>\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// ── Log submission to CSV (backup record) ─────────────
// Written to a private directory outside web root if possible,
// otherwise to a protected subdirectory
$log_dir = dirname(__FILE__) . '/submissions/';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0700, true);
    // Write .htaccess to block direct web access
    file_put_contents($log_dir . '.htaccess', "Deny from all\n");
}
$log_file = $log_dir . 'signers.csv';
$log_exists = file_exists($log_file);
$log_handle = fopen($log_file, 'a');
if ($log_handle) {
    if (!$log_exists) {
        // Write header row
        fputcsv($log_handle, ['Timestamp', 'First Name', 'Last Name', 'Email', 'City', 'WA Voter', 'Wants Updates', 'IP']);
    }
    fputcsv($log_handle, [$timestamp, $firstName, $lastName, $email, $city, $waVoter, $wantsUpdates, $ip]);
    fclose($log_handle);
}

// ── Send email ─────────────────────────────────────────
$sent = mail($to, $subject, $message, $headers);

if ($sent) {
    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    // Email failed but we logged the submission — don't lose the lead
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Mail delivery failed. Your submission was recorded. Please also email us directly at info@lastfallback.org'
    ]);
}
