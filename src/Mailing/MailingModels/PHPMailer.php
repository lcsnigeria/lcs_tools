<?php
use lcsTools\Debugging\Logs;
use lcsTools\Mailing\MailingValidations;

/**
 * Class PHPMailer
 *
 * Extends MailingConfigs to provide email sending functionality using PHPMailer.
 * This class is responsible for configuring and sending emails based on the application's mailing settings.
 *
 * @package Mailing
 */
class PHPMailer extends MailingConfigs
{
    /**
     * LCS_Mail constructor.
     *
     * Initializes the mailer with the specified SMTP configuration.
     *
     * @param string|null $host        The SMTP server hostname (e.g., "smtp.gmail.com").
     * @param string|null $username    The SMTP username, typically your full email address.
     * @param string|null $password    The SMTP password or API token for authentication.
     * @param int         $port        The SMTP server port. Common values:
     *                                 - 587: STARTTLS (recommended)
     *                                 - 465: SMTPS (SSL)
     *                                 - 25: Plain/TLS
     *                                 Defaults to 587.
     * @param string      $encryption  The encryption method to use for the connection.
     *                                 Accepts 'tls' (STARTTLS) or 'ssl' (SMTPS). Defaults to 'tls'.
     *
     * @throws \InvalidArgumentException If the provided $port and $encryption combination is unsupported.
     */
    public function __construct(?string $host = null, ?string $username = null, ?string $password = null, ?int $port = null, ?string $encryption = null)
    {
        $this->host       = $host ?? $this->host;
        $this->username   = $username ?? $this->username;
        $this->password   = $password ?? $this->password;
        $this->port       = $port ?? $this->port;
        $this->model      = 'phpmailer';

        // Note: if using SMTPS (port 465), encryption=ssl. If STARTTLS (port 587 or 25), encryption=tls.
        if (!empty($encryption)) {
            $allowedEncrypt = ['tls', 'ssl'];
            if (!in_array($encryption, $allowedEncrypt, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Unsupported encryption "%s". Use "tls" or "ssl".',
                    $encryption
                ));
            }
            $this->encryption = $encryption;
        }

        $this->initializeMailer('phpmailer');
    }

    /**
     * Send an email using PHPMailer.
     * 
     * This method uses the PHPMailer library to send an email. It supports specifying recipients, subjects, 
     * message content, custom headers, and attachments. Default values for sender and reply-to addresses 
     * are used if not explicitly provided in the headers.
     * 
     * @param string|array $to The recipient's email address. Optionally, a name can be included in the format: "email:Name".
     * @param string $subject The subject of the email.
     * @param string $htmlBody The HTML content of the email.
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
    public function sendPHPMailer(string|array $to, string $subject, string $htmlBody, array|string $headers = '', array|string $attachments = ''):bool
    {
        try {
            $toArray = !is_array($to) ? [$to] : $to;
            foreach ( $toArray as $t ) {
                list($recipientEmail, $recipientName) = MailingValidations::parseRecipient($t);
                // Set recipient
                $this->mailer->addAddress($recipientEmail, $recipientName);
            }
            
            $this->setPMSenderAndReplyTo($headers);

            // Set email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            // Add attachments
            $this->addPMAttachments($attachments);

            // Add custom headers
            $this->addPMCustomHeaders($headers);

            // Send email
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            Logs::reportError("Email could not be sent to $to. Error: {$this->mailer->ErrorInfo}", 1);
            return false;
        }
    }

    /**
     * Sets the sender and reply-to addresses from headers or defaults.
     * 
     * @param string|array $headers The headers to parse.
     */
    private function setPMSenderAndReplyTo(&$headers)
    {
        $defaultSender = ['email' => $this->username, 'name' => 'LCS Official'];
        $defaultReplyTo = ['email' => 'no-reply@lcs.ng', 'name' => 'LCS Official'];

        $sender = $defaultSender;
        $replyTo = $defaultReplyTo;

        $headers = is_array($headers) ? $headers : [$headers];

        foreach ($headers as $key => $header) {
            $mailerHeaderData = MailingValidations::extractMailHeaders($header);
            if (array_key_first($mailerHeaderData) === 'from') {
                $sender['email'] = MailingValidations::extractAddress(array_values($mailerHeaderData)[0]);
                $sender['name'] = MailingValidations::extractAddressName(array_values($mailerHeaderData)[0]);
                unset($headers[$key]);
            } elseif (array_key_first($mailerHeaderData) === 'reply-to') {
                $replyTo['email'] = MailingValidations::extractAddress(array_values($mailerHeaderData)[0]);
                $replyTo['name'] = MailingValidations::extractAddressName(array_values($mailerHeaderData)[0]);
                unset($headers[$key]);
            }
        }

        $this->mailer->setFrom($sender['email'], $sender['name']);
        $this->mailer->addReplyTo($replyTo['email'], $replyTo['name']);
    }

    /**
     * Adds attachments to the email.
     * 
     * @param string|array $attachments The file paths to attach.
     */
    private function addPMAttachments($attachments)
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
    private function addPMCustomHeaders($headers)
    {
        $headers = is_array($headers) ? $headers : [$headers];
        foreach ($headers as $header) {
            $mailerHeaderData = MailingValidations::extractMailHeaders($header);
            $this->mailer->addCustomHeader(
                trim(array_key_first($mailerHeaderData)), 
                trim(array_values($mailerHeaderData)[0])
            );
        }
    }
}