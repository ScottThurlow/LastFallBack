<?php
/**
 * Last Fall Back Act — Form Submission Handler
 * Sends via Brevo (formerly Sendinblue) HTTPS API — works on GoDaddy Windows hosting.
 *
 * ── SETUP ─────────────────────────────────────────────────────────────────────
 * 1. Get your API key from brevo.com → SMTP & API → API Keys
 * 2. Replace YOUR_BREVO_API_KEY_HERE below with your key
 * ──────────────────────────────────────────────────────────────────────────────
 */

define('TO_EMAIL',       'signers@lastfallback.org');
define('TO_NAME',        'Last Fall Back Act Signers');
define('FROM_EMAIL',     'noreply@lastfallback.org');
define('FROM_NAME',      'Last Fall Back Act');
define('BREVO_API_KEY',  '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');  // <- paste your Brevo API key here
define('BREVO_API_URL',  'https://api.brevo.com/v3/smtp/email');

// -- CORS ---------------------------------------------------------------------
header('Content-Type: application/json');
$allowed = ['https://lastfallback.org','https://www.lastfallback.org','http://localhost','http://127.0.0.1'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed)) header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }

// -- Rate limit (5 per IP per hour) -------------------------------------------
$rl_dir  = sys_get_temp_dir() . '/lastfallback_org_ratelimit/';
if (!is_dir($rl_dir)) mkdir($rl_dir, 0700, true);
$rl_file = $rl_dir . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.json';
$now     = time();
$rl      = file_exists($rl_file) ? (json_decode(file_get_contents($rl_file), true) ?: ['times'=>[]]) : ['times'=>[]];
$rl['times'] = array_values(array_filter($rl['times'], fn($t) => ($now - $t) < 3600));
if (count($rl['times']) >= 5) { http_response_code(429); echo json_encode(['success'=>false,'error'=>'Too many submissions. Please try again later.']); exit; }
$rl['times'][] = $now;
file_put_contents($rl_file, json_encode($rl));

// -- Parse & sanitise ---------------------------------------------------------
$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
function clean($v) { return htmlspecialchars(strip_tags(str_replace(["\r","\n","\t"],' ',trim($v??''))),ENT_QUOTES,'UTF-8'); }

$firstName    = clean($body['firstName']    ?? '');
$lastName     = clean($body['lastName']     ?? '');
$email        = filter_var(trim($body['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$city         = clean($body['city']         ?? '');
$waVoter      = clean($body['waVoter']      ?? 'No');
$wantsUpdates = clean($body['wantsUpdates'] ?? 'No');
$volunteer    = clean($body['volunteer']    ?? 'No');
$honeypot     = trim($body['website']       ?? '');
$timestamp    = date('Y-m-d H:i:s T');
$ip           = $_SERVER['REMOTE_ADDR'] ?? '';

// Honeypot
if (!empty($honeypot)) { http_response_code(200); echo json_encode(['success'=>true]); exit; }

// Validation
if (empty($firstName) || empty($lastName)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'First and last name are required.']); exit; }
if (!$email) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'A valid email address is required.']); exit; }

// -- CSV backup (uses submissions dir within web root) --------------------
$log_dir = __DIR__ . '/submissions/';

if (!is_dir($log_dir)) @mkdir($log_dir, 0755, true);
$log_file   = $log_dir . 'lastfallback_org_signers.csv';
$log_exists = file_exists($log_file);
$fh = fopen($log_file, 'a');
if ($fh) {
    if (!$log_exists) fputcsv($fh, ['Timestamp','First Name','Last Name','Email','City','WA Voter','Wants Updates','Volunteer','IP']);
    fputcsv($fh, [$timestamp,$firstName,$lastName,$email,$city,$waVoter,$wantsUpdates,$volunteer,$ip]);
    fclose($fh);
}

// -- Email content ------------------------------------------------------------
$subject  = "[SIGNER]" . ($volunteer === 'Yes' ? "[VOLUNTEER]" : "") . " Last Fall Back Act -- {$firstName} {$lastName}";
$textBody  = "New interest form submission -- Last Fall Back Act\n";
$textBody .= str_repeat('-', 52) . "\n\n";
$textBody .= "Name:           {$firstName} {$lastName}\n";
$textBody .= "Email:          {$email}\n";
$textBody .= "City:           " . ($city ?: '(not provided)') . "\n";
$textBody .= "WA Voter:       {$waVoter}\n";
$textBody .= "Wants Updates:  {$wantsUpdates}\n";
$textBody .= "Volunteer:      {$volunteer}\n\n";
$textBody .= str_repeat('-', 52) . "\n";
$textBody .= "Submitted:      {$timestamp}\n";
$textBody .= "IP:             {$ip}\n";
$textBody .= str_repeat('-', 52) . "\n";

// -- Send via Brevo HTTPS API -------------------------------------------------
$payload = json_encode([
    'sender'      => ['name' => FROM_NAME, 'email' => FROM_EMAIL],
    'to'          => [['email' => TO_EMAIL, 'name' => TO_NAME]],
    'subject'     => $subject,
    'textContent' => $textBody,
]);

$ch = curl_init(BREVO_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'api-key: ' . BREVO_API_KEY,
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    error_log('LastFallBack.org Brevo error: HTTP ' . $httpCode . ' -- ' . ($curlError ?: $response));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Mail delivery failed. Your submission was saved -- please also email us at info@lastfallback.org'
    ]);
}
