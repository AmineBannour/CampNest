<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../vendor/autoload.php';

class Mailer {
    private $mailer;
    private $conn;
    private $logger;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        try {
            $this->mailer = new PHPMailer(true);
            
            // Server settings
            $this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['SMTP_HOST'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $_ENV['SMTP_USERNAME'];
            $this->mailer->Password = $_ENV['SMTP_PASSWORD'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->Port = $_ENV['SMTP_PORT'];
            
            // Default sender
            $this->mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
            $this->mailer->isHTML(true);
            
        } catch (Exception $e) {
            $this->logError('Mailer initialization failed', $e);
            throw new Exception('Email system initialization failed. Please try again later.');
        }
    }
    
    public function sendBookingConfirmation($booking_id) {
        try {
            // Reset all recipients and attachments
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();
            
            // Fetch booking details
            $stmt = $this->conn->prepare("
                SELECT b.*, u.email, u.first_name, c.name as campsite_name
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN campsites c ON b.campsite_id = c.id
                WHERE b.id = ?
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception("Booking not found");
            }
            
            // Fetch booked services
            $stmt = $this->conn->prepare("
                SELECT s.name, s.price, bs.quantity
                FROM booking_services bs
                JOIN services s ON bs.service_id = s.id
                WHERE bs.booking_id = ?
            ");
            $stmt->execute([$booking_id]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->mailer->addAddress($booking['email'], $booking['first_name']);
            $this->mailer->Subject = 'Your CampNest Booking Confirmation';
            
            // Create email body
            $body = $this->getEmailTemplate('booking_confirmation');
            $body = str_replace(
                ['{{name}}', '{{booking_id}}', '{{campsite}}', '{{check_in}}', '{{check_out}}', '{{total}}'],
                [
                    $booking['first_name'],
                    $booking_id,
                    $booking['campsite_name'],
                    date('F j, Y', strtotime($booking['check_in_date'])),
                    date('F j, Y', strtotime($booking['check_out_date'])),
                    number_format($booking['total_price'], 2)
                ],
                $body
            );
            
            // Add services list if any
            if (!empty($services)) {
                $servicesList = '<h3>Additional Services:</h3><ul>';
                foreach ($services as $service) {
                    $servicesList .= sprintf(
                        '<li>%s (x%d) - $%s</li>',
                        htmlspecialchars($service['name']),
                        $service['quantity'],
                        number_format($service['price'] * $service['quantity'], 2)
                    );
                }
                $servicesList .= '</ul>';
                $body = str_replace('{{services}}', $servicesList, $body);
            } else {
                $body = str_replace('{{services}}', '', $body);
            }
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            
            // Log success
            $this->logSuccess('Booking confirmation email sent', $booking_id);
            return true;
            
        } catch (Exception $e) {
            $this->logError('Failed to send booking confirmation email', $e, ['booking_id' => $booking_id]);
            throw new Exception('Failed to send confirmation email. Please contact support.');
        }
    }
    
    public function sendBookingReminder($booking_id) {
        try {
            // Fetch booking details
            $stmt = $this->conn->prepare("
                SELECT b.*, u.email, u.first_name, c.name as campsite_name
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN campsites c ON b.campsite_id = c.id
                WHERE b.id = ?
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->mailer->addAddress($booking['email'], $booking['first_name']);
            $this->mailer->Subject = 'Your Upcoming CampNest Stay';
            
            $body = $this->getEmailTemplate('booking_reminder');
            $body = str_replace(
                ['{{name}}', '{{campsite}}', '{{check_in}}', '{{check_out}}'],
                [
                    $booking['first_name'],
                    $booking['campsite_name'],
                    date('F j, Y', strtotime($booking['check_in_date'])),
                    date('F j, Y', strtotime($booking['check_out_date']))
                ],
                $body
            );
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send booking reminder email: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendReviewRequest($booking_id) {
        try {
            // Fetch booking details
            $stmt = $this->conn->prepare("
                SELECT b.*, u.email, u.first_name, c.name as campsite_name
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN campsites c ON b.campsite_id = c.id
                WHERE b.id = ?
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->mailer->addAddress($booking['email'], $booking['first_name']);
            $this->mailer->Subject = 'Share Your CampNest Experience';
            
            $body = $this->getEmailTemplate('review_request');
            $body = str_replace(
                ['{{name}}', '{{campsite}}', '{{review_link}}'],
                [
                    $booking['first_name'],
                    $booking['campsite_name'],
                    sprintf('index.php?page=review&booking_id=%d', $booking_id)
                ],
                $body
            );
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to send review request email: " . $e->getMessage());
            return false;
        }
    }
    
    private function getEmailTemplate($template_name) {
        $template_path = __DIR__ . "/email_templates/{$template_name}.html";
        if (!file_exists($template_path)) {
            throw new Exception("Email template not found: {$template_name}");
        }
        return file_get_contents($template_path);
    }
    
    private function logError($message, $exception, $context = []) {
        $log_entry = date('Y-m-d H:i:s') . " ERROR: {$message}. " . 
                     "Exception: " . $exception->getMessage() . 
                     ($context ? " Context: " . json_encode($context) : "") . PHP_EOL;
                     
        error_log($log_entry, 3, __DIR__ . '/../logs/mail_errors.log');
    }
    
    private function logSuccess($message, $booking_id) {
        $log_entry = date('Y-m-d H:i:s') . " SUCCESS: {$message}. Booking ID: {$booking_id}" . PHP_EOL;
        error_log($log_entry, 3, __DIR__ . '/../logs/mail_success.log');
    }
} 