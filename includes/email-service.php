<?php
/**
 * EmailService
 * Legacy wrapper kept for backward compatibility.
 * New receipt/notification delivery now goes through ReceiptService.
 *
 * sendDonationConfirmation() is retained but delegates to ReceiptService
 * so any existing call sites continue to work.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/receipt-service.php';

class EmailService
{
    private $db;
    private $from_email;
    private $from_name;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance();
        } catch (Exception $e) {
            error_log('EmailService DB Error: ' . $e->getMessage());
            $this->db = null;
        }
        $this->from_email = Config::app('email');
        $this->from_name  = Config::app('name');
    }

    /**
     * Send donation confirmation — now delegates to ReceiptService.
     *
     * $donation must contain at minimum:
     *   id, transaction_id, amount, cause, payment_mode
     *   donor_name, donor_email, donor_phone (or user_id to look up)
     */
    public function sendDonationConfirmation(array $donation): bool
    {
        try {
            // Resolve user record if only user_id is available
            $user = [
                'name'       => $donation['donor_name']    ?? '',
                'email'      => $donation['donor_email']   ?? '',
                'phone'      => $donation['donor_phone']   ?? '',
                'pan_number' => $donation['donor_pan']     ?? '',
                'address'    => $donation['donor_address'] ?? '',
            ];

            if (empty($user['email']) && !empty($donation['user_id']) && $this->db) {
                $row = $this->db->fetch(
                    "SELECT name, email, phone, pan_number, address FROM users WHERE id=? LIMIT 1",
                    [$donation['user_id']]
                );
                if ($row) {
                    $user = $row;
                }
            }

            ReceiptService::dispatch($donation, $user);
            return true;
        } catch (Throwable $e) {
            error_log('EmailService::sendDonationConfirmation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue email for sending (used by contact form, etc.)
     */
    public function queueEmail(string $to, string $toName, string $subject, string $body, ?string $templateName = null, string $priority = 'normal'): bool
    {
        if (!$this->db) return false;
        try {
            return (bool) $this->db->insert('email_notifications', [
                'recipient_email' => $to,
                'recipient_name'  => $toName,
                'subject'         => $subject,
                'body'            => $body,
                'template_name'   => $templateName,
                'priority'        => $priority,
                'status'          => 'pending',
            ]);
        } catch (Exception $e) {
            error_log('EmailService::queueEmail error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send contact form notification to admin
     */
    public function sendContactNotification(array $contact): bool
    {
        try {
            $subject = 'New Contact Message: ' . ($contact['subject'] ?? 'No Subject');
            $body = '
            <h2>New Contact Form Submission</h2>
            <p><strong>Name:</strong> '      . htmlspecialchars($contact['name']    ?? '') . '</p>
            <p><strong>Email:</strong> '     . htmlspecialchars($contact['email']   ?? '') . '</p>
            <p><strong>Phone:</strong> '     . htmlspecialchars($contact['phone']   ?? 'Not provided') . '</p>
            <p><strong>Subject:</strong> '   . htmlspecialchars($contact['subject'] ?? 'Not provided') . '</p>
            <p><strong>Message:</strong></p>
            <p>' . nl2br(htmlspecialchars($contact['message'] ?? '')) . '</p>
            <p><strong>Date:</strong> ' . date('d M Y, h:i A') . '</p>';

            return $this->queueEmail(
                Config::app('email'), 'Admin', $subject, $body,
                'contact_notification', 'high'
            );
        } catch (Exception $e) {
            error_log('EmailService::sendContactNotification error: ' . $e->getMessage());
            return false;
        }
    }
}
