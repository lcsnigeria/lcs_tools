<?php
use lcsTools\Debugging\Logs;
use lcsTools\Mailing\MailingValidations;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

/**
 * Class SymfonyMailer
 *
 * Extends MailingConfigs to provide mailing functionalities using Symfony Mailer.
 *
 * This class is responsible for configuring and sending emails through the Symfony Mailer component,
 * leveraging the settings and utilities provided by the MailingConfigs base class.
 *
 * @package Mailing\SymfonyMailer
 */
class SymfonyMailer extends MailingConfigs 
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
        $this->model      = 'symfony';

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

        $this->initializeMailer('symfony');
    }

    /**
     * Sends a single email.
     *
     * This method builds a Symfony\Component\Mime\Email object, sets:
     *  - From (default = $this->username, “LCS Official”)
     *  - Reply-To (default = “no-reply@lcs.ng”, “LCS Official”)
     *  - To (parsed from $to; format: "email:Name" or just "email")
     *  - Subject
     *  - HTML body
     *  - Plain-text alt body (strip_tags of HTML)
     *  - Custom headers (any “X-…” or additional “From:” or “Reply-To:” if present)
     *  - Attachments (one or many file paths)
     *
     * If any header “From:” or “Reply-To:” is provided in the $headers array, it overrides the defaults.
     *
     * @param string          $to           Recipient address, optionally with name: "email@example.com:Recipient Name".
     * @param string          $subject      Email subject line.
     * @param string          $htmlBody     HTML content of the email.
     * @param string|string[] $headers      (Optional) One or more headers like "X-Custom-Header: Value".
     *                                      May include "From: ..." or "Reply-To: ..." to override defaults.
     * @param string|string[] $attachments  (Optional) File path or array of file paths to attach.
     *
     * @return bool  Returns true if the mailer did not throw any exception.
     *
     * @throws \InvalidArgumentException If recipient email is invalid or an attachment path does not exist.
     */
    public function sendSymfony(string $to, string $subject, string $htmlBody, array|string $headers = '', array|string $attachments = ''): bool
    {
        // 1) Normalize headers and attachments into arrays
        $headersArray    = is_array($headers) ? $headers : (strlen(preg_replace('/\s+/', ' ', trim($headers))) > 0 ? [ $headers ] : []);
        $attachmentsList = is_array($attachments) ? $attachments : (strlen(trim($attachments)) > 0 ? [ $attachments ] : []);

        // 2) Parse recipient (email + optional name)
        list($recipientEmail, $recipientName) = MailingValidations::parseRecipient($to);

        // 3) Build Email object
        $email = (new Email())
            ->to(new Address($recipientEmail, $recipientName))
            ->subject($subject)
            ->html($htmlBody)
            ->text(strip_tags($htmlBody));

        // 4) Determine default “From” and “Reply-To”
        $fromAddress    = [ $this->username => 'LCS Official' ];
        $replyToAddress = [ 'no-reply@lcs.ng' => 'LCS Official' ];

        // 5) Process headersArray: if any “From:” or “Reply-To:”, override defaults; otherwise, collect as X-Headers
        foreach ($headersArray as $rawHeader) {
            $cleanHeader = preg_replace('/\s+/', ' ', trim($rawHeader));
            $lower = strtolower($cleanHeader);
            if (strpos($lower, 'from:') === 0) {
                // Format: "From: Name <email@example.com>" or "From: email@example.com:Name"
                $parsed = MailingValidations::extractHeaderAddress(substr($rawHeader, 5));
                if ($parsed !== false) {
                    $fromAddress = [ $parsed['email'] => $parsed['name'] ];
                }
            } elseif (strpos($lower, 'reply-to:') === 0) {
                $parsed = MailingValidations::extractHeaderAddress(substr($rawHeader, 9));
                if ($parsed !== false) {
                    $replyToAddress = [ $parsed['email'] => $parsed['name'] ];
                }
            } else {
                // Any other header: add as-is (e.g. "X-Custom-Header: Value")
                // Symfony Mime\Email: ->getHeaders()->addTextHeader()
                $parts = explode(':', $rawHeader, 2);
                if (count($parts) === 2) {
                    $key   = trim($parts[0]);
                    $value = trim($parts[1]);
                    $email->getHeaders()->addTextHeader($key, $value);
                }
            }
        }

        // 6) Set From and Reply-To on Email
        foreach ($fromAddress as $addr => $name) {
            $email->from(new Address($addr, $name));
        }
        foreach ($replyToAddress as $addr => $name) {
            $email->replyTo(new Address($addr, $name));
        }

        // 7) Attach files (verify existence first)
        foreach ($attachmentsList as $path) {
            if (! file_exists($path) || ! is_readable($path)) {
                throw new \InvalidArgumentException("Attachment not found or not readable: {$path}");
            }
            $email->attachFromPath($path);
        }

        // 8) Send and catch any transport exceptions
        try {
            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            // Log or rethrow if desired; here we trigger_error and return false
            Logs::reportError('Email send failed: ' . $e->getMessage(), 1);
            return false;
        }
    }
}