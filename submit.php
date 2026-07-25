<?php
/**
 * Last Fall Back Act — Form Submission Handler
 * Records submissions to a CSV data store outside the web root.
 * Email notifications are disabled.
 */

// Derive home directory (works under Apache where $_SERVER['HOME'] may be unset)
// DOCUMENT_ROOT is e.g. /home/user/public_html/lastfallback.org — go up two levels
$home = getenv('HOME') ?: ($_SERVER['HOME'] ?? dirname($_SERVER['DOCUMENT_ROOT'], 2));

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

// -- Record to CSV data store (outside web root for security & deploy safety) --
$log_dir = $home . '/lastfallback_data/';

if (!is_dir($log_dir)) @mkdir($log_dir, 0755, true);
$log_file   = $log_dir . 'lastfallback_org_signers.csv';
$log_exists = file_exists($log_file);
$fh = fopen($log_file, 'a');
if ($fh) {
    // Explicit $escape: default is deprecated in PHP 8.4+ and the warning would corrupt the JSON response
    if (!$log_exists) fputcsv($fh, ['Timestamp','First Name','Last Name','Email','City','WA Voter','Wants Updates','Volunteer','IP'], ',', '"', '\\');
    fputcsv($fh, [$timestamp,$firstName,$lastName,$email,$city,$waVoter,$wantsUpdates,$volunteer,$ip], ',', '"', '\\');
    fclose($fh);
    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    error_log('LastFallBack.org: could not open ' . $log_file . ' for writing');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Could not save your submission. Please email us at info@lastfallback.org'
    ]);
}
