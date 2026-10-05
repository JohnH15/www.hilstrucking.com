<?php
/**
 * HILS Trucking LLC — Driver Application Form Handler
 *
 * Handles:
 * 1. Configuration loading from config.php (ignored by git)
 * 2. Public config delivery (reCAPTCHA site key) via ?action=config
 * 3. Form input validation and anti-header-injection sanitization
 * 4. Google reCAPTCHA v2 token verification with Google API
 * 5. Multipart MIME (HTML + plain text) email generation and delivery via mail() or SMTP
 * 6. Audit logging and JSON API responses
 */

// Strict error reporting for reliability (errors logged, not displayed to client)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Send JSON headers for all responses
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Helper function to send JSON response and terminate
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// -----------------------------------------------------------------------------
// 1. Load Configuration
// -----------------------------------------------------------------------------
$configPath = __DIR__ . '/config.php';
$exampleConfigPath = __DIR__ . '/config.example.php';

if (file_exists($configPath)) {
    $config = require $configPath;
} elseif (file_exists($exampleConfigPath)) {
    $config = require $exampleConfigPath;
} else {
    jsonResponse([
        'success'    => false,
        'error_code' => 'config_missing',
        'message'    => 'Server configuration file (config.php) was not found.',
    ], 500);
}

// Helper: write to log file if logging is enabled
function logMessage(string $message, array $config): void
{
    if (!empty($config['logging']['enabled']) && !empty($config['logging']['log_file'])) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $logLine = "[{$timestamp}] [{$ip}] {$message}" . PHP_EOL;
        @file_put_contents($config['logging']['log_file'], $logLine, FILE_APPEND | LOCK_EX);
    }
}

// -----------------------------------------------------------------------------
// 2. GET Endpoint: Public Config (reCAPTCHA Site Key & Enabled status)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if (empty($action) && !empty($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $queryParams);
        $action = $queryParams['action'] ?? '';
    }

    if ($action === 'config') {
        jsonResponse([
            'success'            => true,
            'recaptcha_enabled'  => !empty($config['recaptcha']['enabled']),
            'recaptcha_site_key' => $config['recaptcha']['site_key'] ?? '',
        ]);
    }

    jsonResponse([
        'success'    => false,
        'error_code' => 'method_not_allowed',
        'message'    => 'GET method is not allowed on this endpoint. Send a POST request with form data.',
    ], 405);
}

// Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success'    => false,
        'error_code' => 'method_not_allowed',
        'message'    => 'Only POST requests are accepted.',
    ], 405);
}

// -----------------------------------------------------------------------------
// 3. Parse Input Data (supports $_POST, JSON, and raw urlencoded input)
// -----------------------------------------------------------------------------
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = $_POST;

if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    if (!empty($rawBody)) {
        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        } else {
            parse_str($rawBody, $parsed);
            if (is_array($parsed)) {
                $input = $parsed;
            }
        }
    }
}

// -----------------------------------------------------------------------------
// 4. Verify Google reCAPTCHA
// -----------------------------------------------------------------------------
$recaptchaEnabled = !empty($config['recaptcha']['enabled']);
$recaptchaSecret  = trim($config['recaptcha']['secret_key'] ?? '');

if ($recaptchaEnabled) {
    $recaptchaToken = trim($input['g-recaptcha-response'] ?? $input['recaptcha_token'] ?? '');

    if (empty($recaptchaToken)) {
        logMessage('RECAPTCHA REJECTED: Token is missing.', $config);
        jsonResponse([
            'success'    => false,
            'error_code' => 'recaptcha_missing',
            'message'    => 'Please verify that you are not a robot by completing the reCAPTCHA.',
        ], 400);
    }

    // Verify token with Google API
    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    $postParams = [
        'secret'   => $recaptchaSecret,
        'response' => $recaptchaToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    $verifyResponse = false;

    // Use cURL if available
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verifyUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $verifyResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($verifyResponse === false && !empty($curlError)) {
            logMessage("CURL ERROR on reCAPTCHA verification: {$curlError}", $config);
        }
    }

    // Fallback to file_get_contents if curl was not used or failed
    if ($verifyResponse === false) {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($postParams),
                'timeout' => 8,
            ],
            'ssl' => [
                'verify_peer' => true,
            ],
        ]);
        $verifyResponse = @file_get_contents($verifyUrl, false, $context);
    }

    $verifyData = is_string($verifyResponse) ? json_decode($verifyResponse, true) : null;

    if (!is_array($verifyData) || empty($verifyData['success'])) {
        $errorCodes = isset($verifyData['error-codes']) ? implode(', ', (array)$verifyData['error-codes']) : 'unknown';
        logMessage("RECAPTCHA FAILED: Google rejected token. Errors: [{$errorCodes}]", $config);

        jsonResponse([
            'success'     => false,
            'error_code'  => 'recaptcha_failed',
            'message'     => 'reCAPTCHA verification failed. Please try again.',
            'debug_codes' => $errorCodes,
        ], 400);
    }
}

// -----------------------------------------------------------------------------
// 5. Sanitize & Validate Form Fields
// -----------------------------------------------------------------------------
function cleanString(?string $value, int $maxLength = 255): string
{
    if ($value === null) {
        return '';
    }
    // Remove null bytes
    $clean = str_replace(chr(0), '', $value);
    // Strip control characters except newline and tab
    $clean = preg_replace('/[^\P{C}\n\r\t]/u', '', $clean);
    $clean = trim($clean);
    return mb_substr($clean, 0, $maxLength, 'UTF-8');
}

// Prevent header injection in single-line fields
function sanitizeHeader(string $value): string
{
    return trim(str_replace(["\r", "\n", "%0a", "%0d"], '', $value));
}

$name    = cleanString($input['name'] ?? $input['driverName'] ?? '', 100);
$phone   = cleanString($input['phone'] ?? $input['driverPhone'] ?? '', 40);
$email   = cleanString($input['email'] ?? $input['driverEmail'] ?? '', 120);
$role    = cleanString($input['role'] ?? $input['driverRole'] ?? '', 80);
$exp     = cleanString($input['exp'] ?? $input['driverExp'] ?? '', 80);
$state   = cleanString($input['state'] ?? $input['driverState'] ?? '', 50);
$notes   = cleanString($input['notes'] ?? $input['driverNotes'] ?? '', 3000);
$consent = isset($input['consent']) || isset($input['driverConsent']);

$errors = [];

if (mb_strlen($name, 'UTF-8') < 2) {
    $errors['name'] = 'Full name is required (at least 2 characters).';
}

if (mb_strlen($phone, 'UTF-8') < 5) {
    $errors['phone'] = 'A valid phone number is required.';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'A valid email address is required.';
}

if (empty($role)) {
    $errors['role'] = 'Please select the position you are applying for.';
}

if (empty($exp)) {
    $errors['exp'] = 'Please select your driving experience.';
}

if (empty($state)) {
    $errors['state'] = 'CDL state of issue is required.';
}

if (!$consent && empty($input['driverConsent'])) {
    $errors['consent'] = 'You must confirm your CDL validity and information accuracy.';
}

if (!empty($errors)) {
    logMessage('VALIDATION FAILED: ' . json_encode($errors, JSON_UNESCAPED_UNICODE), $config);
    jsonResponse([
        'success'    => false,
        'error_code' => 'validation_error',
        'message'    => 'Please fill out all required fields correctly.',
        'errors'     => $errors,
    ], 422);
}

// -----------------------------------------------------------------------------
// 6. Build Email Subject & Content
// -----------------------------------------------------------------------------
$toEmail = trim($config['to_email'] ?? 'info@hilstrucking.com');
$ccEmail = trim($config['cc_email'] ?? '');
$fromEmail = sanitizeHeader($config['from_email'] ?? 'no-reply@hilstrucking.com');
$fromName  = sanitizeHeader($config['from_name'] ?? 'HILS Trucking Website');
$subjectPrefix = sanitizeHeader($config['subject_prefix'] ?? '[HILS Trucking Application]');

// Clean for headers
$cleanName  = sanitizeHeader($name);
$cleanEmail = sanitizeHeader($email);
$cleanRole  = sanitizeHeader($role);

$subject = "{$subjectPrefix} {$cleanName} — {$cleanRole}";
$nowDate = date('F j, Y, g:i a T');
$ipAddr  = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$userAgent = htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');

// Escaped values for safe HTML rendering
$eName  = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$ePhone = htmlspecialchars($phone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$eEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$eRole  = htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$eExp   = htmlspecialchars($exp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$eState = htmlspecialchars($state, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$eNotes = !empty($notes) ? nl2br(htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) : '<em>None provided</em>';

// 6a. HTML Email Body
$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Driver Application</title>
</head>
<body style="margin:0; padding:24px 0; background-color:#f1f5f9; font-family:'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif; color:#1e293b; line-height:1.6;">
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td align="center">
        <table role="presentation" width="620" border="0" cellspacing="0" cellpadding="0" style="max-width:620px; width:100%; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.08); border:1px solid #e2e8f0;">
          
          <!-- Header Banner -->
          <tr>
            <td style="background:linear-gradient(135deg, #071e3d 0%, #004b9b 100%); padding:28px 32px; color:#ffffff;">
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td>
                    <div style="font-size:12px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:#38bdf8; margin-bottom:4px;">
                      HILS TRUCKING LLC &bull; RECRUITING
                    </div>
                    <div style="font-size:22px; font-weight:800; color:#ffffff; line-height:1.2;">
                      New Driver Application
                    </div>
                  </td>
                  <td align="right">
                    <span style="display:inline-block; padding:6px 14px; background:#00a859; color:#ffffff; font-weight:700; font-size:13px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px;">
                      Active Lead
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Summary Intro -->
          <tr>
            <td style="padding:24px 32px 12px 32px; border-bottom:1px solid #f1f5f9;">
              <p style="margin:0; font-size:15px; color:#475569;">
                A new driver application was submitted through the vacancy landing page form. Please review the applicant details below:
              </p>
            </td>
          </tr>

          <!-- Details Table -->
          <tr>
            <td style="padding:16px 32px;">
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="font-size:14px; border-collapse:separate; border-spacing:0 8px;">
                <tr>
                  <td width="38%" style="padding:10px 14px; background:#f8fafc; color:#64748b; font-weight:600; border-radius:8px 0 0 8px;">Applicant Name:</td>
                  <td width="62%" style="padding:10px 14px; background:#f8fafc; color:#0f172a; font-weight:700; font-size:15px; border-radius:0 8px 8px 0;">{$eName}</td>
                </tr>
                <tr>
                  <td style="padding:10px 14px; background:#f8fafc; color:#64748b; font-weight:600; border-radius:8px 0 0 8px;">Phone Number:</td>
                  <td style="padding:10px 14px; background:#f8fafc; color:#004b9b; font-weight:700; font-size:15px; border-radius:0 8px 8px 0;">
                    <a href="tel:{$ePhone}" style="color:#004b9b; text-decoration:none;">{$ePhone}</a>
                  </td>
                </tr>
                <tr>
                  <td style="padding:10px 14px; background:#f8fafc; color:#64748b; font-weight:600; border-radius:8px 0 0 8px;">Email Address:</td>
                  <td style="padding:10px 14px; background:#f8fafc; color:#004b9b; font-weight:600; border-radius:0 8px 8px 0;">
                    <a href="mailto:{$eEmail}" style="color:#004b9b; text-decoration:none;">{$eEmail}</a>
                  </td>
                </tr>
                <tr>
                  <td style="padding:10px 14px; background:#f8fafc; color:#64748b; font-weight:600; border-radius:8px 0 0 8px;">Applying For:</td>
                  <td style="padding:10px 14px; background:#f8fafc; color:#0f172a; font-weight:700; border-radius:0 8px 8px 0;">
                    <span style="display:inline-block; padding:3px 10px; background:#e6f0fa; color:#004b9b; border-radius:6px; font-weight:700;">{$eRole}</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:10px 14px; background:#f8fafc; color:#64748b; font-weight:600; border-radius:8px 0 0 8px;">CDL-A OTR Experience:</td>
                  <td style="padding:10px 14px; background:#f8fafc; color:#0f172a; font-weight:600; border-radius:0 8px 8px 0;">{$eExp}</td>
                </tr>
                <tr>
                  <td style="padding:10px 14px; background:#f8fafc; color:#64748b; font-weight:600; border-radius:8px 0 0 8px;">CDL State of Issue:</td>
                  <td style="padding:10px 14px; background:#f8fafc; color:#0f172a; font-weight:600; border-radius:0 8px 8px 0;">{$eState}</td>
                </tr>
                <tr>
                  <td valign="top" style="padding:10px 14px; background:#f8fafc; color:#64748b; font-weight:600; border-radius:8px 0 0 8px;">Comments / Equipment:</td>
                  <td style="padding:10px 14px; background:#f8fafc; color:#334155; line-height:1.5; border-radius:0 8px 8px 0;">{$eNotes}</td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Quick Action Buttons -->
          <tr>
            <td style="padding:12px 32px 24px 32px;" align="center">
              <a href="tel:{$ePhone}" style="display:inline-block; padding:12px 24px; background:#00a859; color:#ffffff; text-decoration:none; font-weight:700; font-size:14px; border-radius:8px; margin-right:10px; box-shadow:0 3px 10px rgba(0,168,89,0.3);">
                &phone; Call Applicant
              </a>
              <a href="mailto:{$eEmail}?subject=Regarding%20your%20HILS%20Trucking%20Application" style="display:inline-block; padding:12px 24px; background:#004b9b; color:#ffffff; text-decoration:none; font-weight:700; font-size:14px; border-radius:8px; box-shadow:0 3px 10px rgba(0,75,155,0.3);">
                &#9993; Reply via Email
              </a>
            </td>
          </tr>

          <!-- Technical Metadata Footer -->
          <tr>
            <td style="padding:20px 32px; background:#f8fafc; border-top:1px solid #e2e8f0; font-size:12px; color:#94a3b8; line-height:1.5;">
              <div><strong>Submitted:</strong> {$nowDate} &bull; <strong>IP:</strong> {$ipAddr}</div>
              <div><strong>User Agent:</strong> {$userAgent}</div>
              <div style="margin-top:6px; color:#cbd5e1;">
                HILS Trucking LLC &bull; 7800 Bristol Pike, Levittown, PA 19057 &bull; U.S. DOT #4454223 &bull; MC #1755680
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

// 6b. Plain-Text Email Body
$plainBody = <<<TEXT
HILS TRUCKING LLC — NEW DRIVER APPLICATION
==========================================

Applicant Name:       {$name}
Phone Number:         {$phone}
Email Address:        {$email}
Position Applying:    {$role}
CDL-A Experience:     {$exp}
CDL State of Issue:   {$state}

Comments / Equipment:
{$notes}

------------------------------------------
Submitted At:         {$nowDate}
Applicant IP:         {$ipAddr}
User Agent:           {$userAgent}

HILS Trucking LLC | 7800 Bristol Pike, Levittown, PA 19057
U.S. DOT #4454223 | MC #1755680
TEXT;

// -----------------------------------------------------------------------------
// 7. MIME Headers & Dispatch
// -----------------------------------------------------------------------------
$boundary = "==Multipart_Boundary_x" . md5(uniqid((string)mt_rand(), true)) . "x";

// Encoded subject for standard RFC compliance
$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
$encodedReplyName = '=?UTF-8?B?' . base64_encode($name) . '?=';

$headers = [];
$headers[] = "MIME-Version: 1.0";
$headers[] = "From: {$encodedFromName} <{$fromEmail}>";
$headers[] = "Reply-To: {$encodedReplyName} <{$cleanEmail}>";
$headers[] = "X-Mailer: PHP/" . phpversion();

if (!empty($ccEmail)) {
    $headers[] = "Cc: " . sanitizeHeader($ccEmail);
}

$headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

// Combined Multipart Body
$mimeMessage  = "--{$boundary}\r\n";
$mimeMessage .= "Content-Type: text/plain; charset=UTF-8\r\n";
$mimeMessage .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$mimeMessage .= $plainBody . "\r\n\r\n";
$mimeMessage .= "--{$boundary}\r\n";
$mimeMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
$mimeMessage .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$mimeMessage .= $htmlBody . "\r\n\r\n";
$mimeMessage .= "--{$boundary}--\r\n";

$headersString = implode("\r\n", $headers);

// -----------------------------------------------------------------------------
// 8. SMTP Delivery Function (fallback / optional direct transport)
// -----------------------------------------------------------------------------
function sendViaSmtp(string $to, string $subject, string $data, array $headers, array $smtpConfig): bool
{
    $host = $smtpConfig['host'] ?? 'localhost';
    $port = (int)($smtpConfig['port'] ?? 587);
    $secure = strtolower($smtpConfig['secure'] ?? 'tls');
    $timeout = 15;

    $prefix = '';
    if ($secure === 'ssl') {
        $prefix = 'ssl://';
    }

    $socket = @stream_socket_client($prefix . $host . ':' . $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return false;
    }

    $readResponse = function() use ($socket) {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    };

    $sendCommand = function(string $cmd) use ($socket, $readResponse) {
        fputs($socket, $cmd . "\r\n");
        return $readResponse();
    };

    $greeting = $readResponse();
    if (substr($greeting, 0, 3) !== '220') {
        fclose($socket);
        return false;
    }

    $sendCommand("EHLO " . (gethostname() ?: 'localhost'));

    // If STARTTLS requested on non-SSL socket
    if ($secure === 'tls') {
        $tlsResp = $sendCommand("STARTTLS");
        if (substr($tlsResp, 0, 3) === '220') {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $sendCommand("EHLO " . (gethostname() ?: 'localhost'));
        }
    }

    // Authenticate if required
    if (!empty($smtpConfig['auth']) && !empty($smtpConfig['username'])) {
        $authResp = $sendCommand("AUTH LOGIN");
        if (substr($authResp, 0, 3) !== '334') {
            fclose($socket);
            return false;
        }
        $sendCommand(base64_encode($smtpConfig['username']));
        $pwdResp = $sendCommand(base64_encode($smtpConfig['password'] ?? ''));
        if (substr($pwdResp, 0, 3) !== '235') {
            fclose($socket);
            return false;
        }
    }

    $from = $smtpConfig['username'] ?? 'no-reply@hilstrucking.com';
    $sendCommand("MAIL FROM:<{$from}>");
    $rcptResp = $sendCommand("RCPT TO:<{$to}>");
    if (substr($rcptResp, 0, 3) !== '250') {
        fclose($socket);
        return false;
    }

    $dataResp = $sendCommand("DATA");
    if (substr($dataResp, 0, 3) !== '354') {
        fclose($socket);
        return false;
    }

    $rawEmail  = "To: <{$to}>\r\n";
    $rawEmail .= "Subject: {$subject}\r\n";
    $rawEmail .= implode("\r\n", $headers) . "\r\n\r\n";
    $rawEmail .= $data . "\r\n.\r\n";

    fputs($socket, $rawEmail);
    $sendResp = $readResponse();
    $sendCommand("QUIT");
    fclose($socket);

    return substr($sendResp, 0, 3) === '250';
}

// -----------------------------------------------------------------------------
// 9. Dispatch Email
// -----------------------------------------------------------------------------
$mailSent = false;

if (!empty($config['smtp']['enabled'])) {
    $mailSent = sendViaSmtp($toEmail, $encodedSubject, $mimeMessage, $headers, $config['smtp']);
} else {
    // Standard PHP mail()
    $mailSent = @mail($toEmail, $encodedSubject, $mimeMessage, $headersString);
}

if ($mailSent) {
    logMessage("SUCCESS: Driver application from [{$name}] <{$email}> ({$phone}) sent to <{$toEmail}>.", $config);

    jsonResponse([
        'success' => true,
        'message' => 'Thank you! Your application has been successfully submitted. Our team will review your info and get back to you shortly.',
    ]);
} else {
    logMessage("MAIL ERROR: Failed to send email to <{$toEmail}>. Check server MTA / sendmail or SMTP configuration.", $config);

    jsonResponse([
        'success'    => false,
        'error_code' => 'mail_send_failed',
        'message'    => 'Unable to send notification email. Please call us directly at (445) 444-02-04 or reach out via WhatsApp/Telegram.',
    ], 500);
}
