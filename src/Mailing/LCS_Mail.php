<?php
namespace LCSNG_EXT\Mailing;

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Class LCS_Mail
 * 
 * A utility class for sending emails using PHPMailer with support for custom headers, 
 * attachments, and predefined configurations.
 */
class LCS_Mail
{
    public $host;
    public $username;
    public $password;

    private $port = 587;
    private $mailer;

    /**
     * Constructor.
     * 
     * Initializes the PHPMailer instance and loads email configuration.
     */
    public function __construct( $HOST, $USERNAME, $PASSWORD )
    {
        $this->mailer = new PHPMailer(true);
        $this->host = $HOST;
        $this->username = $USERNAME;
        $this->password = $PASSWORD;

        $this->initializeMailer();
    }

    /**
     * Initializes the PHPMailer settings.
     */
    private function initializeMailer()
    {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->host;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->username;
            $this->mailer->Password = $this->password;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $this->port;
            $this->mailer->CharSet = 'UTF-8';
        } catch (Exception $e) {
            trigger_error("Mailer initialization failed: {$e->getMessage()}");
        }
    }

    /**
     * Send an email using PHPMailer.
     * 
     * This method uses the PHPMailer library to send an email. It supports specifying recipients, subjects, 
     * message content, custom headers, and attachments. Default values for sender and reply-to addresses 
     * are used if not explicitly provided in the headers.
     * 
     * @param string $to The recipient's email address. Optionally, a name can be included in the format: "email:Name".
     * @param string $subject The subject of the email.
     * @param string $message The HTML content of the email.
     * @param string|array $headers Optional. A single string or an array of additional headers, such as "From", "Reply-To", etc.
     * @param string|array $attachments Optional. A single file path as a string or an array of file paths to be attached to the email.
     * @return bool Returns true if the email was successfully sent, false otherwise.
     * 
     * @throws Exception if PHPMailer encounters an error while sending the email.
     * 
     * Usage Examples:
     * 
     * ```php
     * // Simple usage
     * $mailer = new LCS_Mail();
     * $mailer->send('recipient@example.com', 'Test Subject', '<p>This is a test email.</p>');
     * 
     * // Usage with name in recipient and custom headers
     * $mailer->send('recipient@example.com:Recipient Name', 'Test Subject', '<p>This is a test email.</p>', [
     *     'From: Your Name <your_email@example.com>',
     *     'Reply-To: reply@example.com'
     * ]);
     * 
     * // Usage with an attachment
     * $mailer->send('recipient@example.com', 'Test Subject', '<p>This is a test email.</p>', [], '/path/to/file.pdf');
     * 
     * // Usage with multiple attachments
     * $mailer->send('recipient@example.com', 'Test Subject', '<p>This is a test email.</p>', [], [
     *     '/path/to/file1.pdf',
     *     '/path/to/file2.jpg'
     * ]);
     * ```
     */
    public function send($to, $subject, $message, $headers = '', $attachments = '')
    {
        try {
            list($recipientEmail, $recipientName) = $this->parseRecipient($to);
            $this->setSenderAndReplyTo($headers);

            // Set recipient
            $this->mailer->addAddress($recipientEmail, $recipientName);

            // Set email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $message;
            $this->mailer->AltBody = strip_tags($message);

            // Add attachments
            $this->addAttachments($attachments);

            // Add custom headers
            $this->addCustomHeaders($headers);

            // Send email
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            trigger_error("Email could not be sent to $to. Error: {$this->mailer->ErrorInfo}");
            return false;
        }
    }

    /**
     * Parses the recipient information.
     * 
     * @param string $to The recipient string in "email:Name" format.
     * @return array An array with email and name.
     */
    private function parseRecipient($to)
    {
        if (strpos($to, ':') !== false) {
            $parts = explode(':', $to, 2);
            $email = trim($parts[0]);
            $name = trim($parts[1]);
        } else {
            $email = trim($to);
            $name = '';
        }

        if (!$this->isEmailValid($email)) {
            throw new Exception("Invalid recipient email: {$email}");
        }

        return [$email, $name];
    }

    /**
     * Sets the sender and reply-to addresses from headers or defaults.
     * 
     * @param string|array $headers The headers to parse.
     */
    private function setSenderAndReplyTo(&$headers)
    {
        $defaultSender = ['email' => $this->username, 'name' => 'LCS Official'];
        $defaultReplyTo = ['email' => 'no-reply@lcs.ng', 'name' => 'LCS Official'];

        $sender = $defaultSender;
        $replyTo = $defaultReplyTo;

        $headers = is_array($headers) ? $headers : [$headers];

        foreach ($headers as $key => $header) {
            $lowerHeader = strtolower($header);
            if (strpos($lowerHeader, 'from:') !== false) {
                $sender = $this->extractHeaderAddress($header);
                unset($headers[$key]);
            } elseif (strpos($lowerHeader, 'reply-to:') !== false) {
                $replyTo = $this->extractHeaderAddress($header);
                unset($headers[$key]);
            }
        }

        $this->mailer->setFrom($sender['email'], $sender['name']);
        $this->mailer->addReplyTo($replyTo['email'], $replyTo['name']);
    }

    /**
     * Extracts email and name from a header.
     * 
     * @param string $header The header string.
     * @return array Associative array with 'email' and 'name'.
     */
    private function extractHeaderAddress($header)
    {
        if (preg_match('/([^:]+):(.+)/', $header, $matches)) {
            return $this->extractMailerAddress($matches[2]) ?: ['email' => '', 'name' => ''];
        }
        return ['email' => '', 'name' => ''];
    }

    /**
     * Extract name and email address from a string.
     *
     * @param string $input The input string in the format 'Name <email@example.com>', 'Name:email@example.com', or 'email@example.com'.
     * @return array|false Associative array with 'name' and 'email' on success, false on failure.
     */
    private function extractMailerAddress($input) {

        $result = ['name' => '', 'email' => ''];

        // Regular expression to match the name and email address in either format
        if (preg_match('/(?:(.+?)\s*[:<]\s*)?([^>]+)>?/', $input, $matches)) {
            // Check if we have matches
            if (!empty($matches)) {
                foreach ( $matches as $match ) {
                    if ($this->isEmailValid($match)) {
                        $result['email'] = $this->sanitizeEmail(trim($match));
                    } else {
                        $result['name'] = trim($match);
                    }
                }
            }
        }
        return ($result['email'] !== '') ? $result : false;
    }

    /**
     * Validate email address.
     * 
     * @param string $email
     * @return bool
     */
    public function isEmailValid($email) {
        // Remove all illegal characters from email
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        // Validate email address
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true; // Valid email
        } else {
            return false; // Invalid email
        }
    }

    /**
     * Sanitizes and validates an email input.
     *
     * @param mixed $input The input to sanitize.
     * @return string The sanitized email or an empty string if invalid.
     */
    public function sanitizeEmail($input) {
        $sanitized_input = filter_var(trim($input), FILTER_SANITIZE_EMAIL); // Sanitize and trim
        return filter_var($sanitized_input, FILTER_VALIDATE_EMAIL) ? $sanitized_input : ''; // Validate email
    }

    /**
     * Adds attachments to the email.
     * 
     * @param string|array $attachments The file paths to attach.
     */
    private function addAttachments($attachments)
    {
        $attachments = is_array($attachments) ? $attachments : [$attachments];
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $this->mailer->addAttachment($attachment);
            }
        }
    }

    /**
     * Adds custom headers to the email.
     * 
     * @param string|array $headers The headers to add.
     */
    private function addCustomHeaders($headers)
    {
        $headers = is_array($headers) ? $headers : [$headers];
        foreach ($headers as $header) {
            if (strpos($header, ':') !== false) {
                list($key, $value) = explode(':', $header, 2);
                $this->mailer->addCustomHeader(trim($key), trim($value));
            }
        }
    }
}