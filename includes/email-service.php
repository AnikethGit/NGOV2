<?php
/**
 * Email Notification Service - Fixed Version
 * Send transactional emails with error handling
 */

require_once 'config.php';
require_once 'database.php';

class EmailService {
    private $db;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        try {
            $this->db = Database::getInstance();
        } catch (Exception $e) {
            error_log('EmailService DB Error: ' . $e->getMessage());
            $this->db = null;
        }
        
        $this->from_email = Config::app('email');
        $this->from_name = Config::app('name');
    }
    
    /**
     * Queue email for sending
     */
    private function queueEmail($to, $toName, $subject, $body, $templateName = null, $priority = 'normal') {
        if (!$this->db) {
            error_log('EmailService: Database not available for queuing');
            return false;
        }
        
        try {
            $data = [
                'recipient_email' => $to,
                'recipient_name' => $toName,
                'subject' => $subject,
                'body' => $body,
                'template_name' => $templateName,
                'priority' => $priority,
                'status' => 'pending'
            ];
            
            return $this->db->insert('email_notifications', $data);
        } catch (Exception $e) {
            error_log('EmailService Queue Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email immediately using PHP mail()
     */
    private function sendNow($to, $subject, $body) {
        try {
            $headers = "From: {$this->from_name} <{$this->from_email}>\r\n";
            $headers .= "Reply-To: {$this->from_email}\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            $sent = mail($to, $subject, $body, $headers);
            
            if (!$sent) {
                error_log("Email failed to send to: {$to}");
            }
            
            return $sent;
        } catch (Exception $e) {
            error_log('Email send error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send donation confirmation email
     */
    public function sendDonationConfirmation($donation) {
        try {
            $subject = "Thank You for Your Donation - ₹" . number_format($donation['amount'], 2);
            
            $body = $this->getDonationEmailTemplate($donation);
            
            // Try to send to donor
            $sentToDonor = $this->sendNow($donation['donor_email'], $subject, $body);
            
            // Queue admin notification
            $adminSubject = 'New Donation Received - ₹' . number_format($donation['amount'], 2);
            $adminBody = $this->getAdminDonationTemplate($donation);
            
            $this->queueEmail(
                Config::app('email'),
                'Admin',
                $adminSubject,
                $adminBody,
                'admin_donation_notification',
                'high'
            );
            
            // If direct send failed, queue it
            if (!$sentToDonor) {
                $this->queueEmail(
                    $donation['donor_email'],
                    $donation['donor_name'],
                    $subject,
                    $body,
                    'donation_confirmation',
                    'high'
                );
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Donation email error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Donation email template
     */
    private function getDonationEmailTemplate($donation) {
        $appName = Config::app('name');
        $appUrl = Config::app('url');
        $causeName = ucfirst(str_replace('-', ' ', $donation['cause']));
        $date = isset($donation['created_at']) ? date('d M Y, h:i A', strtotime($donation['created_at'])) : date('d M Y, h:i A');
        $paymentMethod = $donation['payment_method'] ?? 'Online Payment';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; }
                .header { background: #007bff; color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px 20px; }
                .amount { font-size: 36px; font-weight: bold; color: #28a745; text-align: center; margin: 30px 0; }
                .details { background: #f9f9f9; padding: 20px; border-left: 4px solid #007bff; margin: 20px 0; }
                .details p { margin: 10px 0; }
                .details strong { color: #333; }
                .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 14px; border-top: 1px solid #ddd; }
                .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🙏 Thank You for Your Donation!</h1>
                </div>
                
                <div class='content'>
                    <p>Dear {$donation['donor_name']},</p>
                    
                    <p>We are deeply grateful for your generous contribution to {$appName}. Your support helps us continue our mission to serve communities in need.</p>
                    
                    <div class='amount'>₹" . number_format($donation['amount'], 2) . "</div>
                    
                    <div class='details'>
                        <h3 style='margin-top: 0;'>Donation Details</h3>
                        <p><strong>Transaction ID:</strong> {$donation['transaction_id']}</p>
                        <p><strong>Cause:</strong> {$causeName}</p>
                        <p><strong>Date:</strong> {$date}</p>
                        <p><strong>Payment Method:</strong> {$paymentMethod}</p>
                    </div>
                    
                    <p>Your donation receipt will be available in your dashboard shortly. You can also download your 80G tax exemption certificate from there.</p>
                    
                    <center>
                        <a href='{$appUrl}/donor-dashboard.html' class='button'>View Dashboard</a>
                    </center>
                    
                    <p>Your contribution directly impacts lives and creates lasting change in communities. We will keep you updated on the impact of your donation.</p>
                    
                    <p>With gratitude,<br>
                    <strong>{$appName}</strong></p>
                </div>
                
                <div class='footer'>
                    <p><strong>{$appName}</strong><br>
                    Email: {$this->from_email}<br>
                    Phone: " . Config::app('phone') . "</p>
                    <p style='font-size: 12px; color: #999;'>This is an automated email. Please do not reply directly to this message.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Admin notification template
     */
    private function getAdminDonationTemplate($donation) {
        $causeName = ucfirst(str_replace('-', ' ', $donation['cause']));
        $date = date('d M Y, h:i A');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                h2 { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
                .info { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; }
                .info p { margin: 8px 0; }
                .amount { font-size: 28px; font-weight: bold; color: #28a745; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>💰 New Donation Received</h2>
                
                <div class='info'>
                    <p class='amount'>₹" . number_format($donation['amount'], 2) . "</p>
                    <p><strong>Transaction ID:</strong> {$donation['transaction_id']}</p>
                    <p><strong>Donor:</strong> {$donation['donor_name']}</p>
                    <p><strong>Email:</strong> {$donation['donor_email']}</p>
                    <p><strong>Phone:</strong> " . ($donation['donor_phone'] ?: 'Not provided') . "</p>
                    <p><strong>Cause:</strong> {$causeName}</p>
                    <p><strong>Date:</strong> {$date}</p>
                </div>
                
                <p>Login to admin dashboard to view more details and manage this donation.</p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Send contact form notification
     */
    public function sendContactNotification($contact) {
        try {
            $subject = "New Contact Message: " . ($contact['subject'] ?? 'No Subject');
            
            $body = "
            <h2>New Contact Form Submission</h2>
            <p><strong>Name:</strong> {$contact['name']}</p>
            <p><strong>Email:</strong> {$contact['email']}</p>
            <p><strong>Phone:</strong> " . ($contact['phone'] ?? 'Not provided') . "</p>
            <p><strong>Subject:</strong> " . ($contact['subject'] ?? 'Not provided') . "</p>
            <p><strong>Message:</strong></p>
            <p>" . nl2br(htmlspecialchars($contact['message'])) . "</p>
            <p><strong>Date:</strong> " . date('d M Y, h:i A') . "</p>
            ";
            
            $this->queueEmail(
                Config::app('email'),
                'Admin',
                $subject,
                $body,
                'contact_notification',
                'high'
            );
            
            return true;
        } catch (Exception $e) {
            error_log('Contact email error: ' . $e->getMessage());
            return false;
        }
    }
}
?>
