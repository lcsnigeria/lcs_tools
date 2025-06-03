<?php
namespace lcsTools\Mailing;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

/**
 * Class LCS_Mail 
 *
 * A utility class for sending emails using Symfony Mailer. Supports:
 *  - SMTP transport with STARTTLS or SMTPS
 *  - Custom “From” and “Reply-To” headers (fallbacks provided)
 *  - Arbitrary additional headers (e.g. X-Custom-Header)
 *  - HTML body with plain-text alternative
 *  - Multiple attachments
 *
 * Usage example:
 * ```php
 * // 1) Instantiate with SMTP credentials:
 * $mailer = new LCS_Mail('smtp.example.com', 'user@example.com', 'secret', 587);
 *
 * // 2) Send a simple message:
 * $sent = $mailer->send(
 *     'recipient@example.com:Recipient Name',
 *     'Test Email 🚀',
 *     '<p>Hello <b>world</b>!</p>',
 *     [
 *         'From: Acme App <no-reply@acme.local>',
 *         'Reply-To: support@acme.local',
 *         'X-Priority: 1 (Highest)',
 *     ],
 *     ['/path/to/attach1.pdf', '/path/to/attach2.png']
 * );
 *
 * if ($sent) {
 *     echo "Email was queued/sent successfully.\n";
 * } else {
 *     echo "Failed to send email.\n";
 * }
 * ```
 */
class LCS_Mail
{
    /** @var string SMTP host (e.g. "smtp.example.com") */
    private $host;

    /** @var string SMTP username (login) */
    private $username;

    /** @var string SMTP password */
    private $password;

    /** @var int SMTP port (e.g. 587, 465, 25) */
    private $port;

    /** @var string Encrypt method: 'tls' or 'ssl' */
    private $encryption;

    /** @var Mailer */
    private $symfonyMailer;

    /**
     * Constructor.
     *
     * Initializes Symfony Mailer with an SMTP DSN built from the provided parameters.
     *
     * @param string $host       SMTP hostname (e.g. "smtp.gmail.com").
     * @param string $username   SMTP username (often your full email address).
     * @param string $password   SMTP password or API token.
     * @param int    $port       SMTP port (587 for STARTTLS, 465 for SMTPS, 25 for plain/TLS).
     * @param string $encryption Either 'tls' (STARTTLS) or 'ssl' (SMTPS). Defaults to 'tls'.
     *
     * @throws \InvalidArgumentException If $port/encryption combination is unsupported.
     */
    public function __construct(string $host, string $username, string $password, int $port = 587, string $encryption = 'tls')
    {
        $this->host       = $host;
        $this->username   = $username;
        $this->password   = $password;
        $this->port       = $port;
        $this->encryption = $encryption;

        // Build DSN: "smtp://USERNAME:PASSWORD@HOST:PORT?encryption=ssl_or_tls"
        // Note: if using SMTPS (port 465), encryption=ssl. If STARTTLS (port 587 or 25), encryption=tls.
        $allowedEncrypt = ['tls', 'ssl'];
        if (!in_array($encryption, $allowedEncrypt, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported encryption "%s". Use "tls" or "ssl".',
                $encryption
            ));
        }

        // Compose the DSN string:
        // - prefix "smtp://" always, even if encryption=ssl or tls
        // - embed username/password (URL-encoded)
        $dsn = sprintf(
            'smtp://%s:%s@%s:%d?encryption=%s',
            rawurlencode($username),
            rawurlencode($password),
            $host,
            $port,
            $encryption
        );

        // Create Transport and Mailer:
        $transport = Transport::fromDsn($dsn);
        $this->symfonyMailer = new Mailer($transport);
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
    public function send(string $to, string $subject, string $htmlBody, $headers = '', $attachments = ''): bool
    {
        // 1) Normalize headers and attachments into arrays
        $headersArray    = is_array($headers) ? $headers : (strlen(trim($headers)) > 0 ? [ $headers ] : []);
        $attachmentsList = is_array($attachments) ? $attachments : (strlen(trim($attachments)) > 0 ? [ $attachments ] : []);

        // 2) Parse recipient (email + optional name)
        list($recipientEmail, $recipientName) = $this->parseRecipient($to);

        // 3) Build Email object
        $email = (new Email())
            ->to($recipientEmail, $recipientName)
            ->subject($subject)
            ->html($htmlBody)
            ->text(strip_tags($htmlBody));

        // 4) Determine default “From” and “Reply-To”
        $fromAddress    = [ $this->username => 'LCS Official' ];
        $replyToAddress = [ 'no-reply@lcs.ng' => 'LCS Official' ];

        // 5) Process headersArray: if any “From:” or “Reply-To:”, override defaults; otherwise, collect as X-Headers
        foreach ($headersArray as $rawHeader) {
            $lower = strtolower($rawHeader);
            if (strpos($lower, 'from:') === 0) {
                // Format: "From: Name <email@example.com>" or "From: email@example.com:Name"
                $parsed = $this->extractHeaderAddress(substr($rawHeader, 5));
                if ($parsed !== false) {
                    $fromAddress = [ $parsed['email'] => $parsed['name'] ];
                }
            } elseif (strpos($lower, 'reply-to:') === 0) {
                $parsed = $this->extractHeaderAddress(substr($rawHeader, 9));
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
            $email->from($addr, $name);
        }
        foreach ($replyToAddress as $addr => $name) {
            $email->replyTo($addr, $name);
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
            $this->symfonyMailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            // Log or rethrow if desired; here we trigger_error and return false
            trigger_error('Email send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Parses a recipient string like "email@example.com:John Doe" or just "email@example.com".
     *
     * @param string $to  Raw input. If it contains “:”, the part after the first “:” is treated as the name.
     * @return array      [ 0 => valid email, 1 => name-or-empty ]
     *
     * @throws \InvalidArgumentException If the email portion is not a valid address.
     */
    private function parseRecipient(string $to): array
    {
        $email = '';
        $name  = '';

        if (strpos($to, ':') !== false) {
            $parts = explode(':', $to, 2);
            $email = trim($parts[0]);
            $name  = trim($parts[1]);
        } else {
            $email = trim($to);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid recipient email: {$email}");
        }

        return [ $email, $name ];
    }

    /**
     * Given a header fragment (e.g. "John Doe <john@example.com>" or "john@example.com:John Doe"), 
     * extracts [ 'email' => 'john@example.com', 'name' => 'John Doe' ] or returns false on failure.
     *
     * @param string $fragment
     * @return array|false
     */
    private function extractHeaderAddress(string $fragment)
    {
        $fragment = trim($fragment);

        // Case A: "Name <email@example.com>"
        if (preg_match('/^(.+?)\s*<\s*([^>]+)\s*>$/', $fragment, $m)) {
            $name  = trim($m[1]);
            $email = trim($m[2]);
        }
        // Case B: "email@example.com:Name"
        elseif (strpos($fragment, ':') !== false) {
            list($emailPart, $namePart) = explode(':', $fragment, 2);
            $email = trim($emailPart);
            $name  = trim($namePart);
        }
        // Case C: Only an email
        else {
            $email = $fragment;
            $name  = '';
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return [
            'email' => $email,
            'name'  => $name,
        ];
    }
}
