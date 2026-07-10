<?php
/**
 * SIXandCO Contact Form Handler
 * Premium email processing with validation, security, and branded email templates
 */

// ============================================
// CONFIGURATION
// ============================================

// Replace with your receiving email address
$receiving_email_address = 'info@sixandco6.co.za';

// Company details for email branding
$company_name = 'SIXandCO';
$company_email = 'info@sixandco6.co.za';
$company_phone = '+27 62 031 6488';
$company_website = 'https://sixandco6.co.za';
$company_address = 'South Africa';

// Enable/disable debugging (set to false in production)
$debug_mode = false;

// ============================================
// CORE FUNCTIONS
// ============================================

// Set error reporting based on debug mode
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

/**
 * Send JSON response
 */
function sendJsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email address (RFC 5322 compliant)
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number - supports international formats
 */
function validatePhone($phone) {
    // Remove all non-digit characters except + and -
    $cleaned = preg_replace('/[^+\d\-\(\)\s]/', '', $phone);
    // Check if it has at least 5 digits and matches international format
    $digits = preg_replace('/[^0-9]/', '', $cleaned);
    return strlen($digits) >= 5 && strlen($digits) <= 15;
}

/**
 * Validate name - only letters, spaces, hyphens, and apostrophes
 */
function validateName($name) {
    return preg_match('/^[a-zA-Z\s\-\']+$/', trim($name)) && strlen(trim($name)) >= 2;
}

/**
 * Validate subject
 */
function validateSubject($subject) {
    $subject = trim($subject);
    return strlen($subject) >= 3 && strlen($subject) <= 200;
}

/**
 * Validate message
 */
function validateMessage($message) {
    $message = trim($message);
    return strlen($message) >= 10 && strlen($message) <= 5000;
}

/**
 * Check for spam patterns
 */
function isSpam($data) {
    // Check for URL injection
    $url_pattern = '/https?:\/\/[^\s]+/i';
    if (preg_match_all($url_pattern, $data, $matches)) {
        // Allow up to 2 URLs
        if (count($matches[0]) > 2) {
            return true;
        }
    }
    
    // Check for excessive special characters (potential spam)
    $special_chars = preg_replace('/[a-zA-Z0-9\s\.,!?\-\']/', '', $data);
    if (strlen($special_chars) > 50) {
        return true;
    }
    
    // Check for common spam keywords
    $spam_keywords = [
        'viagra', 'casino', 'poker', 'bitcoin', 'crypto', 'lottery',
        'xxx', 'porn', 'sex', 'dating', 'loan', 'credit', 'mortgage',
        'insurance', 'weight loss', 'pills', 'pharmacy'
    ];
    foreach ($spam_keywords as $keyword) {
        if (stripos($data, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================
// MAIN PROCESSING
// ============================================

// Start session for CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method.');
}

// Verify CSRF token
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!verifyCsrfToken($csrf_token)) {
    sendJsonResponse(false, 'Security validation failed. Please refresh the page and try again.');
}

// Get and sanitize form data
$name = isset($_POST['name']) ? sanitizeInput($_POST['name']) : '';
$email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
$phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
$subject = isset($_POST['subject']) ? sanitizeInput($_POST['subject']) : '';
$message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';

// ============================================
// VALIDATION
// ============================================

$errors = [];

// Validate name
if (empty($name)) {
    $errors['name'] = 'Name is required.';
} elseif (!validateName($name)) {
    $errors['name'] = 'Please enter a valid name (letters and spaces only).';
}

// Validate email
if (empty($email)) {
    $errors['email'] = 'Email address is required.';
} elseif (!validateEmail($email)) {
    $errors['email'] = 'Please enter a valid email address.';
}

// Validate phone (optional but must be valid if provided)
if (!empty($phone) && !validatePhone($phone)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}

// Validate subject
if (empty($subject)) {
    $errors['subject'] = 'Subject is required.';
} elseif (!validateSubject($subject)) {
    $errors['subject'] = 'Subject must be between 3 and 200 characters.';
}

// Validate message
if (empty($message)) {
    $errors['message'] = 'Message is required.';
} elseif (!validateMessage($message)) {
    $errors['message'] = 'Message must be between 10 and 5000 characters.';
}

// Check for spam
$full_message = $name . ' ' . $email . ' ' . $subject . ' ' . $message;
if (isSpam($full_message)) {
    // Silently reject spam without revealing detection
    sleep(2);
    sendJsonResponse(true, 'Your message has been sent successfully.');
}

// If there are validation errors
if (!empty($errors)) {
    sendJsonResponse(false, 'Please fix the errors in your form.', $errors);
}

// ============================================
// BUILD BRANDED EMAIL
// ============================================

$timestamp = date('Y-m-d H:i:s');
$client_ip = $_SERVER['REMOTE_ADDR'];
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';

// Email subject with company branding
$email_subject = "📩 New Contact Form Submission from {$name} - {$company_name}";

// Build HTML email template
$html_email = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Contact Form Submission</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background-color: #f4f6f8;
      padding: 40px 20px;
      color: #1e293b;
    }
    .container {
      max-width: 650px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,0.08);
    }
    .header {
      background: linear-gradient(135deg, #0ea5e9 0%, #10b981 100%);
      padding: 32px 40px;
      text-align: center;
    }
    .header img {
      max-height: 50px;
      margin-bottom: 12px;
      background: rgba(255,255,255,0.95);
      padding: 8px 20px;
      border-radius: 8px;
    }
    .header h1 {
      color: #ffffff;
      font-size: 22px;
      font-weight: 600;
      margin: 0;
      letter-spacing: 0.5px;
    }
    .header p {
      color: rgba(255,255,255,0.85);
      font-size: 14px;
      margin: 4px 0 0 0;
    }
    .content {
      padding: 40px 40px 30px 40px;
    }
    .content .greeting {
      font-size: 20px;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 8px;
    }
    .content .sub-greeting {
      color: #64748b;
      font-size: 15px;
      margin-bottom: 28px;
      border-bottom: 2px solid #f1f5f9;
      padding-bottom: 20px;
    }
    .detail-group {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .detail-row {
      display: flex;
      flex-direction: column;
      background: #f8fafc;
      border-radius: 10px;
      padding: 14px 18px;
      border-left: 4px solid #0ea5e9;
    }
    .detail-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #94a3b8;
      font-weight: 600;
      margin-bottom: 4px;
    }
    .detail-value {
      font-size: 16px;
      font-weight: 500;
      color: #0f172a;
      word-break: break-word;
    }
    .detail-value a {
      color: #0ea5e9;
      text-decoration: none;
    }
    .detail-value a:hover {
      text-decoration: underline;
    }
    .message-box {
      background: #f1f5f9;
      border-radius: 10px;
      padding: 18px 20px;
      margin-top: 4px;
      border: 1px solid #e2e8f0;
    }
    .message-box p {
      font-size: 15px;
      line-height: 1.7;
      color: #1e293b;
      margin: 0;
      white-space: pre-wrap;
    }
    .badge {
      display: inline-block;
      background: #dcfce7;
      color: #16a34a;
      font-size: 12px;
      font-weight: 600;
      padding: 4px 12px;
      border-radius: 20px;
      margin-top: 4px;
    }
    .footer {
      padding: 20px 40px 32px 40px;
      background: #f8fafc;
      border-top: 1px solid #e2e8f0;
      text-align: center;
    }
    .footer .brand {
      font-size: 16px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 4px;
    }
    .footer .brand span {
      background: linear-gradient(135deg, #0ea5e9, #10b981);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .footer .details {
      font-size: 13px;
      color: #94a3b8;
      margin-bottom: 6px;
    }
    .footer .details i {
      display: inline-block;
      margin: 0 6px;
    }
    .footer .divider {
      height: 1px;
      background: #e2e8f0;
      margin: 12px 0 12px 0;
    }
    .footer .meta {
      font-size: 12px;
      color: #94a3b8;
    }
    @media (max-width: 480px) {
      body { padding: 20px 12px; }
      .header { padding: 24px 20px; }
      .content { padding: 24px 20px 20px 20px; }
      .footer { padding: 16px 20px 24px 20px; }
      .detail-row { padding: 12px 14px; }
      .detail-value { font-size: 14px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <img src="https://sixandco6.co.za/assets/img/logo.png" alt="{$company_name} Logo">
      <h1>✨ New Contact Form Submission</h1>
      <p>A potential client has reached out through the website</p>
    </div>
    
    <div class="content">
      <div class="greeting">👋 New Lead Received</div>
      <div class="sub-greeting">{$name} has submitted a contact request on {$timestamp}</div>
      
      <div class="detail-group">
        <div class="detail-row">
          <span class="detail-label">Name</span>
          <span class="detail-value">{$name}</span>
        </div>
        
        <div class="detail-row">
          <span class="detail-label">Email</span>
          <span class="detail-value"><a href="mailto:{$email}">{$email}</a></span>
        </div>
        
        <div class="detail-row">
          <span class="detail-label">Phone</span>
          <span class="detail-value">" . (!empty($phone) ? "<a href=\"tel:{$phone}\">{$phone}</a>" : "<span style=\"color:#94a3b8;\">Not provided</span>") . "</span>
        </div>
        
        <div class="detail-row">
          <span class="detail-label">Subject</span>
          <span class="detail-value">{$subject}</span>
        </div>
        
        <div class="detail-row" style="border-left-color: #10b981;">
          <span class="detail-label">Message</span>
          <div class="message-box">
            <p>{$message}</p>
          </div>
        </div>
      </div>
      
      <div style="margin-top: 24px; padding: 16px 20px; background: #f0f9ff; border-radius: 10px; border: 1px solid #bae6fd;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
          <span style="font-weight: 600; color: #0369a1;">📋 Quick Actions:</span>
          <a href="mailto:{$email}?subject=Re: Your enquiry at {$company_name}" style="color: #0ea5e9; font-weight: 500; text-decoration: none; background: rgba(14,165,233,0.1); padding: 4px 14px; border-radius: 20px;">Reply</a>
          <a href="tel:{$phone}" style="color: #0ea5e9; font-weight: 500; text-decoration: none; background: rgba(14,165,233,0.1); padding: 4px 14px; border-radius: 20px;">Call</a>
          <span class="badge">📱 Mobile Friendly</span>
        </div>
      </div>
    </div>
    
    <div class="footer">
      <div class="brand"><span>{$company_name}</span></div>
      <div class="details">
        {$company_email} <i>•</i> {$company_phone} <i>•</i> {$company_address}
      </div>
      <div class="divider"></div>
      <div class="meta">
        📍 IP: {$client_ip} <i>•</i> 🖥️ {$user_agent}
      </div>
      <div style="margin-top: 8px; font-size: 12px; color: #cbd5e1;">
        This email was generated automatically from {$company_website}
      </div>
    </div>
  </div>
</body>
</html>
HTML;

// Plain text version for email clients that don't support HTML
$plain_text = <<<TEXT
NEW CONTACT FORM SUBMISSION - {$company_name}
=============================================

Name: {$name}
Email: {$email}
Phone: " . (!empty($phone) ? $phone : 'Not provided') . "
Subject: {$subject}
Timestamp: {$timestamp}

Message:
{$message}

---
{$company_name}
{$company_email} | {$company_phone}
{$company_website}
TEXT;

// ============================================
// SEND EMAIL
// ============================================

// Email headers
$headers = [
    'From: "' . $company_name . '" <' . $company_email . '>',
    'Reply-To: "' . $name . '" <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: ' . $company_name . ' Contact Form',
    'X-Priority: 1 (Highest)',
    'X-MSMail-Priority: High'
];

// Send to main recipient
$to = $receiving_email_address;

// Send HTML email
$html_sent = mail($to, $email_subject, $html_email, implode("\r\n", $headers));

// If HTML fails, send plain text
if (!$html_sent) {
    $headers_plain = [
        'From: "' . $company_name . '" <' . $company_email . '>',
        'Reply-To: "' . $name . '" <' . $email . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: ' . $company_name . ' Contact Form'
    ];
    mail($to, $email_subject, $plain_text, implode("\r\n", $headers_plain));
}

// ============================================
// SEND AUTO-RESPONSE TO CLIENT
// ============================================

$auto_response_subject = "Thank you for contacting {$company_name}";

$auto_response_html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Thank You</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #f4f6f8; padding: 40px 20px; color: #1e293b; }
    .container { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.08); }
    .header { background: linear-gradient(135deg, #0ea5e9 0%, #10b981 100%); padding: 32px 32px 24px 32px; text-align: center; }
    .header h1 { color: #ffffff; font-size: 24px; font-weight: 600; margin: 0; }
    .header p { color: rgba(255,255,255,0.85); font-size: 14px; margin: 4px 0 0 0; }
    .content { padding: 32px; }
    .content .greeting { font-size: 22px; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
    .content .sub { color: #64748b; font-size: 15px; margin-bottom: 20px; }
    .content p { line-height: 1.7; color: #475569; margin-bottom: 16px; }
    .divider { height: 1px; background: #e2e8f0; margin: 20px 0; }
    .footer { padding: 20px 32px 28px 32px; background: #f8fafc; text-align: center; border-top: 1px solid #e2e8f0; }
    .footer .brand { font-size: 15px; font-weight: 700; color: #0f172a; }
    .footer .brand span { background: linear-gradient(135deg, #0ea5e9, #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .footer .details { font-size: 13px; color: #94a3b8; margin-top: 2px; }
    @media (max-width: 480px) { body { padding: 20px 12px; } .header { padding: 24px 20px; } .content { padding: 24px 20px; } .footer { padding: 16px 20px 24px 20px; } }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🎉 Thank You, {$name}!</h1>
      <p>We've received your message and we're excited to connect</p>
    </div>
    <div class="content">
      <div class="greeting">Hello {$name},</div>
      <div class="sub">Thank you for reaching out to {$company_name}.</div>
      <p>We've received your enquiry and our team is reviewing it right now. We're passionate about helping businesses like yours build powerful, memorable brands.</p>
      <p><strong>What happens next?</strong><br>
      One of our brand specialists will get back to you within <strong>24 hours</strong> to discuss your project in detail.</p>
      <p>In the meantime, feel free to explore our <a href="{$company_website}/services.html" style="color: #0ea5e9; text-decoration: none; font-weight: 500;">services</a> or check out our <a href="{$company_website}/portfolio.html" style="color: #0ea5e9; text-decoration: none; font-weight: 500;">portfolio</a> to see how we've helped other brands stand out.</p>
      <div class="divider"></div>
      <p style="font-size: 14px; color: #94a3b8; text-align: center;">If you need immediate assistance, feel free to call us at <a href="tel:{$company_phone}" style="color: #0ea5e9; text-decoration: none; font-weight: 500;">{$company_phone}</a></p>
    </div>
    <div class="footer">
      <div class="brand"><span>{$company_name}</span></div>
      <div class="details">{$company_email} • {$company_phone}</div>
      <div style="font-size: 12px; color: #cbd5e1; margin-top: 4px;">{$company_website}</div>
    </div>
  </div>
</body>
</html>
HTML;

// Auto-response headers
$auto_headers = [
    'From: "' . $company_name . '" <' . $company_email . '>',
    'Reply-To: "' . $company_name . '" <' . $company_email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: ' . $company_name . ' Auto-Responder'
];

// Send auto-response
$auto_sent = mail($email, $auto_response_subject, $auto_response_html, implode("\r\n", $auto_headers));

// ============================================
// LOGGING (Optional - uncomment to enable)
// ============================================

// $log_file = 'contact_log.txt';
// $log_entry = date('Y-m-d H:i:s') . " | {$name} | {$email} | {$subject} | IP: {$client_ip}\n";
// file_put_contents($log_file, $log_entry, FILE_APPEND);

// ============================================
// RESPONSE
// ============================================

if ($html_sent) {
    sendJsonResponse(true, 'Your message has been sent successfully! We\'ll get back to you within 24 hours.');
} else {
    sendJsonResponse(false, 'We\'re experiencing technical difficulties. Please call us directly at ' . $company_phone . ' or try again later.');
}
?>