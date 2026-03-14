<?php
/**
 * Last Fall Back Act — Form Submission Handler
 * Uses direct SMTP (no external libraries) via GoDaddy mail servers.
 *
 * ── SETUP: Fill in your noreply@lastfallback.org mailbox password below ──────
 */

define('TO_EMAIL',       'signers@lastfallback.org');
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'lastfallback@gmail.com');
define('SMTP_PASS',      'uvcoexoydwqlgnpi');  // no spaces
define('SMTP_FROM_NAME', 'Last Fall Back Act');
define('SMTP_ENCRYPTION','tls');

// ── CORS ──────────────────────────────────────────────────────────────────────
header('Content-Type: application/json');
$allowed = ['https://lastfallback.org','https://www.lastfallback.org','http://localhost','http://127.0.0.1'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed)) header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }

// ── Rate limit (5 submissions per IP per hour) ────────────────────────────────
$rl_dir  = sys_get_temp_dir() . '/lfba_rl/';
if (!is_dir($rl_dir)) mkdir($rl_dir, 0700, true);
$rl_file = $rl_dir . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.json';
$now     = time();
$rl      = file_exists($rl_file) ? (json_decode(file_get_contents($rl_file), true) ?: ['times'=>[]]) : ['times'=>[]];
$rl['times'] = array_values(array_filter($rl['times'], fn($t) => ($now - $t) < 3600));
if (count($rl['times']) >= 5) { http_response_code(429); echo json_encode(['success'=>false,'error'=>'Too many submissions. Please try again later.']); exit; }
$rl['times'][] = $now;
file_put_contents($rl_file, json_encode($rl));

// ── Parse & sanitise ──────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
function clean($v) { return htmlspecialchars(strip_tags(str_replace(["\r","\n","\t"],' ',trim($v??''))),ENT_QUOTES,'UTF-8'); }

$firstName    = clean($body['firstName']    ?? '');
$lastName     = clean($body['lastName']     ?? '');
$email        = filter_var(trim($body['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$city         = clean($body['city']         ?? '');
$waVoter      = clean($body['waVoter']      ?? 'No');
$wantsUpdates = clean($body['wantsUpdates'] ?? 'No');
$honeypot     = trim($body['website']       ?? '');
$timestamp    = date('Y-m-d H:i:s T');
$ip           = $_SERVER['REMOTE_ADDR'] ?? '';

// Honeypot check — bots fill this hidden field, real users don't
if (!empty($honeypot)) { http_response_code(200); echo json_encode(['success'=>true]); exit; }

// Required fields
if (empty($firstName) || empty($lastName)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'First and last name are required.']); exit; }
if (!$email) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'A valid email address is required.']); exit; }

// ── CSV backup (always write before attempting email) ─────────────────────────
$log_dir = __DIR__ . '/submissions/';
if (!is_dir($log_dir)) { mkdir($log_dir, 0700, true); file_put_contents($log_dir.'.htaccess',"Require all denied\n"); }
$log_file   = $log_dir . 'signers.csv';
$log_exists = file_exists($log_file);
$fh = fopen($log_file, 'a');
if ($fh) {
    if (!$log_exists) fputcsv($fh, ['Timestamp','First Name','Last Name','Email','City','WA Voter','Wants Updates','IP']);
    fputcsv($fh, [$timestamp,$firstName,$lastName,$email,$city,$waVoter,$wantsUpdates,$ip]);
    fclose($fh);
}

// ── Email content ─────────────────────────────────────────────────────────────
// [SIGNER] prefix lets you create an auto-file rule in your email client
$subject = "[SIGNER] Last Fall Back Act — {$firstName} {$lastName}";

$msg  = "New interest form submission — Last Fall Back Act\n";
$msg .= str_repeat('-', 52) . "\n\n";
$msg .= "Name:           {$firstName} {$lastName}\n";
$msg .= "Email:          {$email}\n";
$msg .= "City:           " . ($city ?: '(not provided)') . "\n";
$msg .= "WA Voter:       {$waVoter}\n";
$msg .= "Wants Updates:  {$wantsUpdates}\n\n";
$msg .= str_repeat('-', 52) . "\n";
$msg .= "Submitted:      {$timestamp}\n";
$msg .= "IP:             {$ip}\n";
$msg .= str_repeat('-', 52) . "\n";

// ── Direct SMTP function (no PHPMailer needed) ────────────────────────────────
function smtp_send($cfg, $to, $subject, $body) {
    $errno = 0; $errstr = ''; $timeout = 15;

    // Open socket — ssl:// for port 465, plain for 587 (STARTTLS follows)
    $host_str = ($cfg['enc'] === 'ssl') ? "ssl://{$cfg['host']}" : $cfg['host'];
    $conn = @fsockopen($host_str, $cfg['port'], $errno, $errstr, $timeout);
    if (!$conn) return "Connection failed ({$errno}): {$errstr}";

    stream_set_timeout($conn, $timeout);

    // Read one SMTP response (handles multi-line responses)
    $read = function() use ($conn) {
        $out = '';
        while (!feof($conn)) {
            $line = fgets($conn, 512);
            $out .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break; // end of response
        }
        return $out;
    };
    $cmd = function($c) use ($conn, $read) { fwrite($conn, $c."\r\n"); return $read(); };

    // Banner
    $r = $read();
    if (substr($r,0,3) !== '220') { fclose($conn); return "Bad banner: {$r}"; }

    // EHLO
    $cmd("EHLO lastfallback.org");

    // STARTTLS upgrade (port 587 / tls mode only)
    if ($cfg['enc'] === 'tls') {
        $r = $cmd("STARTTLS");
        if (substr($r,0,3) !== '220') { fclose($conn); return "STARTTLS failed: {$r}"; }
        stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $cmd("EHLO lastfallback.org");
    }

    // AUTH LOGIN
    $r = $cmd("AUTH LOGIN");
    if (substr($r,0,3) !== '334') { fclose($conn); return "AUTH rejected: {$r}"; }
    $r = $cmd(base64_encode($cfg['user']));
    if (substr($r,0,3) !== '334') { fclose($conn); return "Username rejected: {$r}"; }
    $r = $cmd(base64_encode($cfg['pass']));
    if (substr($r,0,3) !== '235') { fclose($conn); return "Password rejected — check SMTP_PASS in submit.php: {$r}"; }

    // Envelope
    $r = $cmd("MAIL FROM:<{$cfg['user']}>");
    if (substr($r,0,3) !== '250') { fclose($conn); return "MAIL FROM rejected: {$r}"; }
    $r = $cmd("RCPT TO:<{$to}>");
    if (substr($r,0,3) !== '250') { fclose($conn); return "RCPT TO rejected: {$r}"; }

    // Data
    $r = $cmd("DATA");
    if (substr($r,0,3) !== '354') { fclose($conn); return "DATA rejected: {$r}"; }

    $enc_subj = '=?UTF-8?B?'.base64_encode($subject).'?=';
    $full  = "Date: ".date('r')."\r\n";
    $full .= "From: {$cfg['name']} <{$cfg['user']}>\r\n";
    $full .= "To: <{$to}>\r\n";
    $full .= "Subject: {$enc_subj}\r\n";
    $full .= "Message-ID: <".time().".".md5(uniqid())."@lastfallback.org>\r\n";
    $full .= "MIME-Version: 1.0\r\n";
    $full .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $full .= "Content-Transfer-Encoding: 8bit\r\n";
    $full .= "\r\n".$body."\r\n.";

    $r = $cmd($full);
    $cmd("QUIT");
    fclose($conn);

    return (substr($r,0,3) === '250') ? null : "Send failed: {$r}";
}

// ── Send ──────────────────────────────────────────────────────────────────────
$cfg = [
    'host' => SMTP_HOST,
    'port' => SMTP_PORT,
    'enc'  => SMTP_ENCRYPTION,
    'user' => SMTP_USER,
    'pass' => SMTP_PASS,
    'name' => SMTP_FROM_NAME,
];

$error = smtp_send($cfg, TO_EMAIL, $subject, $msg);

if ($error === null) {
    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    error_log('LFBA SMTP error: ' . $error);
    // Submission is already in the CSV — lead is not lost even if email fails
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Mail delivery failed. Your submission was saved — please also email us at info@lastfallback.org'
    ]);
}
