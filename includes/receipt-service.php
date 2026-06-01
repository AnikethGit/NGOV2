<?php
/**
 * ReceiptService
 * Generates a unique receipt number, builds the HTML receipt,
 * triggers email delivery and SMS notification after a successful payment.
 *
 * Usage (from payment-callback.php):
 *   require_once '../includes/receipt-service.php';
 *   ReceiptService::dispatch($donation, $user);
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class ReceiptService
{
    // ──────────────────────────────────────────────────────────────────────────
    // Public entry point — call once after payment is confirmed
    // ──────────────────────────────────────────────────────────────────────────
    public static function dispatch(array $donation, array $user): void
    {
        try {
            $db = Database::getInstance();

            // 1. Generate receipt number if not already set
            if (empty($donation['receipt_number'])) {
                $receipt = self::generateReceiptNumber($donation['id']);
                $db->query(
                    "UPDATE donations SET receipt_number = ? WHERE id = ?",
                    [$receipt, $donation['id']]
                );
                $donation['receipt_number'] = $receipt;
            }

            // 2. Merge user info into donation array for templates.
            //    Prefer values already on the donation row (filled at checkout),
            //    fall back to the user profile if the donation field is empty.
            $donation['donor_name']    = $user['name'] ?? $user['full_name'] ?? 'Donor';
            $donation['donor_email']   = $user['email'] ?? '';
            $donation['donor_phone']   = $donation['donor_phone'] ?? $user['phone'] ?? '';
            $donation['donor_pan']     = $donation['donor_pan']     ?? $user['pan_number'] ?? '';
            $donation['donor_address'] = $donation['donor_address'] ?? $user['address']    ?? '';

            // 3. Send email (if donor provided one)
            if (!empty($donation['donor_email'])) {
                self::sendEmail($donation);
            }

            // 4. Send WhatsApp receipt (always, phone is now required)
            if (!empty($donation['donor_phone'])) {
                self::sendWhatsApp($donation);
            }

            // 5. Send SMS fallback
            if (!empty($donation['donor_phone'])) {
                self::sendSms($donation);
            }

            // 5. Log dispatch
            error_log("ReceiptService: dispatched receipt {$donation['receipt_number']} for donation #{$donation['id']}");

        } catch (Throwable $e) {
            // Never let receipt dispatch crash the payment flow
            error_log('ReceiptService::dispatch error: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Receipt Number  →  SDSMBT-YYYYMMDD-XXXXXX
    // e.g.  SDSMBT-20260414-000042
    // ──────────────────────────────────────────────────────────────────────────
    public static function generateReceiptNumber(int $donationId): string
    {
        $prefix = 'SDSMBT';          // Sri Datta Sai Manga Bharadwaja Trust
        $date   = date('Ymd');       // 20260414
        $seq    = str_pad($donationId, 6, '0', STR_PAD_LEFT);  // 000042
        return "{$prefix}-{$date}-{$seq}";
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Email
    // ──────────────────────────────────────────────────────────────────────────
    private static function sendEmail(array $d): void
    {
        $appName  = Config::app('name');
        $fromMail = Config::app('email');
        $appUrl   = Config::app('url');
        $appPhone = Config::app('phone');

        $amount       = '₹' . number_format((float)$d['amount'], 2);
        $receipt      = htmlspecialchars($d['receipt_number']);
        $txn          = htmlspecialchars($d['transaction_id']);
        $donorName    = htmlspecialchars($d['donor_name']);
        $donorEmail   = filter_var(trim($d['donor_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
        if ($donorEmail === '') {
            error_log("ReceiptService: invalid or missing donor email — email skipped for receipt {$receipt}");
            return;
        }
        $cause        = htmlspecialchars(ucwords(str_replace('-', ' ', $d['cause'] ?? 'General')));
        $gateway      = strtolower($d['payment_gateway'] ?? '');
        $rawMethod    = $d['payment_method'] ?? 'Online';
        $payMode      = htmlspecialchars(($gateway === 'razorpay') ? 'Online: Razorpay' : $rawMethod);
        $date         = date('d M Y, h:i A', strtotime($d['updated_at'] ?? $d['created_at'] ?? 'now'));
        $rawId    = $d['donor_pan'] ?? '';
        // Auto-detect: 12 all-digit chars = Aadhaar, otherwise PAN
        $idLabel  = preg_match('/^\d{12}$/', preg_replace('/\s/', '', $rawId)) ? 'Aadhaar Number' : 'PAN Number';
        $pan      = !empty($rawId) ? htmlspecialchars($rawId) : 'Not provided';
        $dashboardUrl = rtrim($appUrl, '/') . '/donor-dashboard.html';

        $subject = self::encodeSubject("Donation Receipt #{$receipt} — {$appName}");

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donation Receipt</title>
</head>
<body style="margin:0;padding:0;background:#f0f4f0;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f0;padding:32px 16px;">
    <tr><td align="center">

      <!-- Card -->
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#01696f 0%,#0c4e54 100%);padding:36px 40px;text-align:center;">
            <p style="margin:0 0 8px;font-size:13px;color:#a8d8db;letter-spacing:2px;text-transform:uppercase;">Sri Datta Sai Manga Bharadwaja Trust</p>
            <h1 style="margin:0;font-size:28px;color:#ffffff;font-weight:700;">Donation Receipt</h1>
            <p style="margin:12px 0 0;font-size:15px;color:#cdeaec;">Your generosity changes lives 🙏</p>
          </td>
        </tr>

        <!-- Amount Hero -->
        <tr>
          <td style="background:#f7fffe;padding:32px 40px;text-align:center;border-bottom:1px solid #e0f0ef;">
            <p style="margin:0 0 4px;font-size:13px;color:#6b9e9f;text-transform:uppercase;letter-spacing:1px;">Amount Donated</p>
            <p style="margin:0;font-size:52px;font-weight:800;color:#01696f;line-height:1.1;">{$amount}</p>
            <p style="margin:8px 0 0;font-size:13px;color:#6b9e9f;">Receipt No: <strong style="color:#0c4e54;">{$receipt}</strong></p>
          </td>
        </tr>

        <!-- Greeting -->
        <tr>
          <td style="padding:28px 40px 8px;">
            <p style="margin:0;font-size:16px;color:#1a1a1a;">Dear <strong>{$donorName}</strong>,</p>
            <p style="margin:12px 0 0;font-size:15px;color:#444;line-height:1.7;">Thank you for your generous donation to <strong>{$appName}</strong>. Your contribution supports our cause of <em>{$cause}</em> and directly impacts the communities we serve.</p>
          </td>
        </tr>

        <!-- Details Table -->
        <tr>
          <td style="padding:20px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f7fffe;border:1px solid #d0e8e8;border-radius:8px;overflow:hidden;">
              <tr style="background:#e0f2f1;">
                <td colspan="2" style="padding:12px 20px;font-size:13px;font-weight:700;color:#01696f;text-transform:uppercase;letter-spacing:1px;">Payment Details</td>
              </tr>
              <tr>
                <td style="padding:10px 20px;font-size:14px;color:#666;border-bottom:1px solid #e0f0ef;">Transaction ID</td>
                <td style="padding:10px 20px;font-size:14px;font-weight:600;color:#1a1a1a;border-bottom:1px solid #e0f0ef;">{$txn}</td>
              </tr>
              <tr>
                <td style="padding:10px 20px;font-size:14px;color:#666;border-bottom:1px solid #e0f0ef;">Cause / Purpose</td>
                <td style="padding:10px 20px;font-size:14px;font-weight:600;color:#1a1a1a;border-bottom:1px solid #e0f0ef;">{$cause}</td>
              </tr>
              <tr>
                <td style="padding:10px 20px;font-size:14px;color:#666;border-bottom:1px solid #e0f0ef;">Payment Mode</td>
                <td style="padding:10px 20px;font-size:14px;font-weight:600;color:#1a1a1a;border-bottom:1px solid #e0f0ef;">{$payMode}</td>
              </tr>
              <tr>
                <td style="padding:10px 20px;font-size:14px;color:#666;border-bottom:1px solid #e0f0ef;">Date &amp; Time</td>
                <td style="padding:10px 20px;font-size:14px;font-weight:600;color:#1a1a1a;border-bottom:1px solid #e0f0ef;">{$date}</td>
              </tr>
              <tr>
                <td style="padding:10px 20px;font-size:14px;color:#666;">{$idLabel}</td>
                <td style="padding:10px 20px;font-size:14px;font-weight:600;color:#1a1a1a;">{$pan}</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- 80G Notice -->
        <tr>
          <td style="padding:8px 40px 24px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;">
              <tr>
                <td style="padding:14px 20px;font-size:14px;color:#92400e;">
                  <strong>📄 80G Tax Exemption:</strong> This donation is eligible for tax exemption under Section 80G of the Income Tax Act. Your official 80G certificate will be issued within 7 working days and will be available in your <a href="{$dashboardUrl}" style="color:#01696f;font-weight:600;">donor dashboard</a>.
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- CTA -->
        <tr>
          <td style="padding:0 40px 32px;text-align:center;">
            <a href="{$dashboardUrl}" style="display:inline-block;background:#01696f;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 36px;border-radius:8px;">View My Dashboard →</a>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f7fffe;border-top:1px solid #d0e8e8;padding:24px 40px;text-align:center;">
            <p style="margin:0;font-size:13px;color:#6b9e9f;"><strong style="color:#0c4e54;">{$appName}</strong></p>
            <p style="margin:6px 0 0;font-size:12px;color:#9bb8b8;">{$appPhone} &nbsp;|&nbsp; {$fromMail}</p>
            <p style="margin:8px 0 0;font-size:11px;color:#b0c8c8;">This is an automated receipt. Please keep it for your records.<br>Registered NGO — Donations eligible under 80G of the IT Act.</p>
          </td>
        </tr>

      </table>
      <!-- /Card -->

    </td></tr>
  </table>
</body>
</html>
HTML;

        // Attempt to generate PDF receipt attachment
        $pdfContent  = null;
        $pdfFilename = null;
        try {
            require_once __DIR__ . '/PdfReceipt.php';
            $pdfContent  = PdfReceipt::generate($d);
            $pdfFilename = 'DonationReceipt-' . preg_replace('/[^A-Za-z0-9\-]/', '', $receipt) . '.pdf';
        } catch (Throwable $pe) {
            error_log('ReceiptService: PDF generation skipped — ' . $pe->getMessage());
        }

        $boundary = 'SDSMBT_' . md5(uniqid('', true));

        if ($pdfContent !== null) {
            $headers  = "From: {$appName} <{$fromMail}>\r\n";
            $headers .= "Reply-To: {$fromMail}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($html));
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/pdf\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n\r\n";
            $body .= chunk_split(base64_encode($pdfContent)) . "\r\n";
            $body .= "--{$boundary}--";

            $sent = mail($donorEmail, $subject, $body, $headers);
        } else {
            // Fallback: HTML-only email if PDF generation failed
            $headers  = "From: {$appName} <{$fromMail}>\r\n";
            $headers .= "Reply-To: {$fromMail}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";

            $sent = mail($donorEmail, $subject, chunk_split(base64_encode($html)), $headers);
        }

        if (!$sent) {
            error_log("ReceiptService: mail() failed for {$donorEmail}, receipt {$receipt}");
            // Queue fallback in email_notifications table
            try {
                $db = Database::getInstance();
                $db->insert('email_notifications', [
                    'recipient_email' => $donorEmail,
                    'recipient_name'  => $d['donor_name'],
                    'subject'         => $subject,
                    'body'            => $html,
                    'template_name'   => 'donation_receipt',
                    'priority'        => 'high',
                    'status'          => 'pending',
                ]);
            } catch (Throwable $qe) {
                error_log('ReceiptService: queue fallback failed: ' . $qe->getMessage());
            }
        } else {
            error_log("ReceiptService: receipt email sent to {$donorEmail}");
        }

        // Admin copy (plain HTML, no attachment)
        $adminMail     = $fromMail;
        $adminSubject  = self::encodeSubject("New Donation: {$amount} from {$d['donor_name']} — {$receipt}");
        $adminBody     = self::buildAdminEmail($d, $amount, $receipt, $cause, $date);
        $adminHeaders  = "From: {$appName} <{$fromMail}>\r\n";
        $adminHeaders .= "MIME-Version: 1.0\r\n";
        $adminHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";
        $adminHeaders .= "Content-Transfer-Encoding: base64\r\n";
        if (!mail($adminMail, $adminSubject, chunk_split(base64_encode($adminBody)), $adminHeaders)) {
            error_log("ReceiptService: admin notification email failed for receipt {$receipt}");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // WhatsApp  via Meta Cloud API
    // Docs: https://developers.facebook.com/docs/whatsapp/cloud-api
    //
    // Add to .env:
    //   WHATSAPP_PHONE_NUMBER_ID=<from Meta Developer Console>
    //   WHATSAPP_ACCESS_TOKEN=<permanent System User token>
    //   WHATSAPP_TEMPLATE_NAME=donation_receipt   (your approved template name)
    //
    // Template to create in Meta Business Manager (category: UTILITY):
    //   Header : DOCUMENT  (receives the PDF receipt)
    //   Body   : Dear {{1}}, your donation of {{2}} to Sri Datta Sai Manga
    //            Bharadwaja Trust is confirmed! 🙏
    //            🧾 Receipt No: {{3}}
    //            💳 Transaction ID: {{4}}
    //            🎯 Cause: {{5}}
    //            📅 Date: {{6}}
    //            Your 80G certificate will be issued within 7 working days.
    //   Footer : Sri Datta Sai Manga Bharadwaja Trust
    //
    // If the template has no DOCUMENT header, remove the header component block
    // below and set WHATSAPP_TEMPLATE_NAME to a body-only template name.
    // ──────────────────────────────────────────────────────────────────────────
    private static function sendWhatsApp(array $d): void
    {
        $envPath     = __DIR__ . '/../.env';
        $env         = function_exists('load_env_file') ? load_env_file($envPath) : [];
        $phoneNumId  = trim($env['WHATSAPP_PHONE_NUMBER_ID'] ?? WHATSAPP_PHONE_NUMBER_ID);
        $accessToken = trim($env['WHATSAPP_ACCESS_TOKEN']    ?? WHATSAPP_ACCESS_TOKEN);
        $template    = trim($env['WHATSAPP_TEMPLATE_NAME']   ?? WHATSAPP_TEMPLATE_NAME);

        if (empty($phoneNumId) || empty($accessToken)) {
            error_log('ReceiptService: WhatsApp not configured — skipped.');
            return;
        }

        // Normalise to 10-digit Indian number, then prepend country code
        $phone = preg_replace('/[^0-9]/', '', $d['donor_phone'] ?? '');
        if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) !== 10) {
            error_log("ReceiptService WhatsApp: invalid phone '{$d['donor_phone']}' — skipped.");
            return;
        }
        $waPhone = '91' . $phone;

        // Body parameter values
        $amount  = '₹' . number_format((float)$d['amount'], 2);
        $receipt = $d['receipt_number'];
        $txn     = $d['transaction_id'];
        $cause   = ucwords(str_replace('-', ' ', $d['cause'] ?? 'General'));
        $date    = date('d M Y, h:i A', strtotime($d['updated_at'] ?? $d['created_at'] ?? 'now'));

        // Try to generate PDF and upload as WhatsApp media
        $components  = [];
        $pdfFilename = 'DonationReceipt-' . preg_replace('/[^A-Za-z0-9\-]/', '', $receipt) . '.pdf';
        try {
            require_once __DIR__ . '/PdfReceipt.php';
            $pdfContent = PdfReceipt::generate($d);
            $mediaId    = self::uploadWhatsAppMedia($pdfContent, $pdfFilename, $phoneNumId, $accessToken);
            if ($mediaId) {
                $components[] = [
                    'type'       => 'header',
                    'parameters' => [[
                        'type'     => 'document',
                        'document' => ['id' => $mediaId, 'filename' => $pdfFilename],
                    ]],
                ];
            }
        } catch (Throwable $e) {
            error_log('ReceiptService WhatsApp: PDF upload skipped — ' . $e->getMessage());
        }

        $components[] = [
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $d['donor_name']],
                ['type' => 'text', 'text' => $amount],
                ['type' => 'text', 'text' => $receipt],
                ['type' => 'text', 'text' => $txn],
                ['type' => 'text', 'text' => $cause],
                ['type' => 'text', 'text' => $date],
            ],
        ];

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $waPhone,
            'type'              => 'template',
            'template'          => [
                'name'       => $template,
                'language'   => ['code' => 'en_US'],
                'components' => $components,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $url = "https://graph.facebook.com/v19.0/{$phoneNumId}/messages";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("ReceiptService WhatsApp cURL error: {$curlErr}");
            return;
        }
        $resp = json_decode($raw, true);
        if (!empty($resp['messages'][0]['id'])) {
            error_log("ReceiptService: WhatsApp sent to {$waPhone} for receipt {$receipt}");
        } else {
            error_log("ReceiptService: WhatsApp failed for {$waPhone}: {$raw}");
        }
    }

    // Upload PDF to Meta media endpoint; returns media_id string or null on failure
    private static function uploadWhatsAppMedia(string $pdf, string $filename, string $phoneNumId, string $token): ?string
    {
        $boundary = '----WA' . md5(uniqid('', true));
        $body  = "--{$boundary}\r\n"
               . "Content-Disposition: form-data; name=\"messaging_product\"\r\n\r\nwhatsapp\r\n"
               . "--{$boundary}\r\n"
               . "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n"
               . "Content-Type: application/pdf\r\n\r\n"
               . $pdf . "\r\n"
               . "--{$boundary}--\r\n";

        $ch = curl_init("https://graph.facebook.com/v19.0/{$phoneNumId}/media");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: multipart/form-data; boundary=' . $boundary,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        $resp = json_decode($raw, true);
        if (!empty($resp['id'])) {
            return $resp['id'];
        }
        error_log('ReceiptService WhatsApp media upload failed: ' . $raw);
        return null;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SMS  via Fast2SMS (free tier, Indian numbers)
    // Docs: https://docs.fast2sms.com
    // Add FAST2SMS_KEY to your .env file
    // ──────────────────────────────────────────────────────────────────────────
    private static function sendSms(array $d): void
    {
        // Read key from environment / .env
        $envPath = __DIR__ . '/../.env';
        $env     = function_exists('load_env_file') ? load_env_file($envPath) : [];
        $apiKey  = $env['FAST2SMS_KEY'] ?? getenv('FAST2SMS_KEY') ?? '';

        if (empty($apiKey)) {
            error_log('ReceiptService: FAST2SMS_KEY not set — SMS skipped.');
            return;
        }

        // Strip country code / spaces to get 10-digit number
        $phone = preg_replace('/[^0-9]/', '', $d['donor_phone']);
        if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) !== 10) {
            error_log("ReceiptService: invalid phone '{$d['donor_phone']}' — SMS skipped.");
            return;
        }

        $amount  = '₹' . number_format((float)$d['amount'], 2);
        $receipt = $d['receipt_number'];
        $name    = Config::app('name');

        // Fast2SMS Quick SMS (DLT-free, for transactional use)
        $message = "Dear {$d['donor_name']}, your donation of {$amount} to {$name} is confirmed. Receipt No: {$receipt}. Thank you for your generosity. -SDSMBT";

        $payload = json_encode([
            'route'   => 'q',          // Quick SMS route
            'message' => $message,
            'numbers' => $phone,
            'flash'   => 0,
        ]);

        $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'authorization: ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $raw      = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("ReceiptService SMS cURL error: {$curlErr}");
            return;
        }

        $resp = json_decode($raw, true);
        if (($resp['return'] ?? false) === true) {
            error_log("ReceiptService: SMS sent to {$phone} for receipt {$receipt}");
        } else {
            error_log("ReceiptService: SMS failed for {$phone}: " . ($resp['message'][0] ?? $raw));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RFC 2047 subject encoder — required for any non-ASCII characters
    // (₹, —, emoji, accented letters, etc.) in email subjects.
    // ──────────────────────────────────────────────────────────────────────────
    private static function encodeSubject(string $s): string
    {
        if (!preg_match('/[^\x20-\x7E]/', $s)) {
            return $s; // Pure ASCII — no encoding needed
        }
        // Split into 45-byte chunks so each encoded word stays under 75 chars
        $chunks = str_split($s, 45);
        return implode("\r\n ", array_map(
            fn($c) => '=?UTF-8?B?' . base64_encode($c) . '?=',
            $chunks
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Admin notification email (plain, no styling)
    // ──────────────────────────────────────────────────────────────────────────
    private static function buildAdminEmail(array $d, string $amount, string $receipt, string $cause, string $date): string
    {
        $donorName  = htmlspecialchars($d['donor_name']);
        $donorEmail = htmlspecialchars($d['donor_email']);
        $donorPhone = htmlspecialchars($d['donor_phone'] ?: 'Not provided');
        $txn        = htmlspecialchars($d['transaction_id']);
        $gateway    = strtolower($d['payment_gateway'] ?? '');
        $payMode    = htmlspecialchars(($gateway === 'razorpay') ? 'Online: Razorpay' : ($d['payment_method'] ?? 'Online'));

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;padding:24px;">
  <h2 style="color:#01696f;border-bottom:2px solid #01696f;padding-bottom:8px;">💰 New Donation Received</h2>
  <table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:500px;">
    <tr><td style="color:#666;">Amount</td><td style="font-size:24px;font-weight:700;color:#01696f;">{$amount}</td></tr>
    <tr><td style="color:#666;">Receipt No</td><td><strong>{$receipt}</strong></td></tr>
    <tr><td style="color:#666;">Donor</td><td>{$donorName}</td></tr>
    <tr><td style="color:#666;">Email</td><td>{$donorEmail}</td></tr>
    <tr><td style="color:#666;">Phone</td><td>{$donorPhone}</td></tr>
    <tr><td style="color:#666;">Transaction ID</td><td>{$txn}</td></tr>
    <tr><td style="color:#666;">Cause</td><td>{$cause}</td></tr>
    <tr><td style="color:#666;">Payment Mode</td><td>{$payMode}</td></tr>
    <tr><td style="color:#666;">Date</td><td>{$date}</td></tr>
  </table>
  <p style="margin-top:20px;color:#666;font-size:13px;">Log in to the admin dashboard to review and manage this donation.</p>
</body></html>
HTML;
    }
}
