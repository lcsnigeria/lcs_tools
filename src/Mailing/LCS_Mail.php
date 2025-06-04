<?php
namespace lcsTools\Mailing;

use Debugging\LCS_Logs\Logs;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Class LCS_Mail
 *
 * A flexible utility class for sending emails using either Symfony Mailer or PHPMailer.
 *
 * Supported features:
 *  - Choice of backend: Symfony Mailer or PHPMailer (switchable at runtime)
 *  - SMTP transport with STARTTLS or SMTPS
 *  - Custom “From” and “Reply-To” headers (with sensible fallbacks)
 *  - Arbitrary additional headers (e.g. X-Custom-Header)
 *  - HTML body with plain-text alternative
 *  - Multiple file attachments
 *  - Recipient name support via "email@example.com:Recipient Name" format
 *
 * Supported models:
 *  - 'php_mailer': Uses PHPMailer for sending emails (recommended for broad compatibility)
 *  - 'symfony': Uses Symfony Mailer component (recommended for modern PHP apps)
 *
 * Usage example:
 * ```php
 * // 1) Instantiate with SMTP credentials and desired backend:
 * $mailer = new LCS_Mail('smtp.example.com', 'user@example.com', 'secret', 587, 'tls', 'php_mailer');
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
 *
 * // 3) Switch backend at runtime if needed:
 * $mailer->switchModel('symfony');
 * $mailer->send(
 *     'recipient@example.com',
 *     'Test via Symfony',
 *     '<p>This uses Symfony Mailer backend.</p>'
 * );
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
    public $port;
    private static $validPorts = [587, 465, 25];

    /** @var string Encrypt method: 'tls' or 'ssl' */
    public $encryption;

    /** @var string */
    public $model = 'php_mailer';
    private static $validModels = ['php_mailer', 'symfony'];

    /** @var Mailer|PHPMailer */
    private $mailer;

    /**
     * Constructor.
     *
     * Initializes Mailer from the provided parameters.
     *
     * @param string $host       SMTP hostname (e.g. "smtp.gmail.com").
     * @param string $username   SMTP username (often your full email address).
     * @param string $password   SMTP password or API token.
     * @param int    $port       SMTP port (587 for STARTTLS, 465 for SMTPS, 25 for plain/TLS).
     * @param string $encryption Either 'tls' (STARTTLS) or 'ssl' (SMTPS). Defaults to 'tls'.
     *
     * @throws \InvalidArgumentException If $port/encryption combination is unsupported.
     */
    public function __construct(string $host, string $username, string $password, int $port = 587, string $encryption = 'tls', string $model = 'php_mailer')
    {
        $this->host       = $host;
        $this->username   = $username;
        $this->password   = $password;
        $this->port       = $port;
        $this->model      = $model;

        // Note: if using SMTPS (port 465), encryption=ssl. If STARTTLS (port 587 or 25), encryption=tls.
        $allowedEncrypt = ['tls', 'ssl'];
        if (!in_array($encryption, $allowedEncrypt, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported encryption "%s". Use "tls" or "ssl".',
                $encryption
            ));
        }

        $this->encryption = $allowedEncrypt;
    }

    /**
     * Initializes the mailer based on the specified model.
     *
     * This method selects and initializes the appropriate mailer implementation
     * depending on the provided model name. Supported models are defined in
     * self::$validModels. If no model is specified, the default model stored in
     * $this->model is used.
     *
     * @param string|null $model The mailer model to initialize ('php_mailer', 'symfony', etc.).
     *
     * @throws \Exception If the provided model is not valid, an error is reported via Logs::reportError()
     *                    with the message "Invalid mailer model specified.".
     *                    If the model is not recognized in the switch statement, an error is reported
     *                    with the message "Unknown mailer model encountered during initialization.".
     *
     * @return void
     */
    public function initializeMailer(?string $model = null) {
        $model = $model ?? $this->model;

        if (!in_array($model, self::$validModels)) {
            Logs::reportError("Invalid mailer model specified.", 2);
        }

        $this->model = $model;
        $this->mailer = null;
        switch ($model) {
            case 'php_mailer':
                $this->initializePHPMailer();
                break;
            
            case 'symfony':
                $this->initializeSymfony();
                break;
            default:
                Logs::reportError("Unknown mailer model encountered during initialization.", 2);
                break;
        }
    }

    
    /**
     * Configures and initializes the PHPMailer instance with the required settings.
     *
     * This method sets up PHPMailer parameters such as SMTP server, authentication,
     * encryption, and other relevant options to prepare the mailer for sending emails.
     * It should be called before attempting to send any emails using PHPMailer.
     *
     * @return void
     */
    private function initializePHPMailer()
    {
        try {
            $this->mailer = new PHPMailer(true);
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->host;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->username;
            $this->mailer->Password = $this->password;

            $encryptData = [
                587 => PHPMailer::ENCRYPTION_STARTTLS,
                465 => PHPMailer::ENCRYPTION_SMTPS,
                25  => PHPMailer::ENCRYPTION_STARTTLS,
            ];
            $this->mailer->SMTPSecure = $encryptData[$this->port] ?? PHPMailer::ENCRYPTION_STARTTLS;

            $this->mailer->Port = $this->port;
            $this->mailer->CharSet = 'UTF-8';
        } catch (Exception $e) {
            Logs::reportError("Mailer initialization failed: {$e->getMessage()}", 2);
        }
    }

    /**
     * Initializes the Symfony mailer components required for sending emails.
     *
     * This method sets up the necessary configuration and dependencies
     * to enable email functionality using the Symfony Mailer component.
     *
     * @return void
     */
    private function initializeSymfony() {
        $this->mailer = null;

        // Build DSN: "smtp://USERNAME:PASSWORD@HOST:PORT?encryption=ssl_or_tls"
        // Compose the DSN string:
        // - prefix "smtp://" always, even if encryption=ssl or tls
        // - embed username/password (URL-encoded)
        $dsn = sprintf(
            'smtp://%s:%s@%s:%d?encryption=%s',
            rawurlencode($this->username),
            rawurlencode($this->password),
            $this->host,
            $this->port,
            $this->encryption
        );

        // Create Transport and Mailer:
        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
    }

    /**
     * Switches the SMTP port and adjusts the encryption method accordingly.
     * 
     * This method updates the mailer instance to use a different SMTP port and
     * automatically configures the appropriate encryption protocol based on the selected port.
     * 
     * Supported ports and their encryption methods:
     * - 587 => ENCRYPTION_STARTTLS
     * - 465 => ENCRYPTION_SMTPS
     * - 25  => ENCRYPTION_STARTTLS
     * 
     * If an unsupported port is provided, an Exception will be thrown.
     * 
     * @param int $port The SMTP port to switch to (e.g., 587, 465, 25).
     * 
     * @throws Exception if an unsupported port is specified.
     * 
     * Usage Example:
     * 
     * ```php
     * $mailer = new LCS_Mail('smtp.example.com', 'user@example.com', 'password');
     * $mailer->switchPort(465); // Switch to port 465 using SMTPS encryption
     * ```
     */
    public function switchPort(int $port) 
    {
        if (in_array($port, self::$validPorts)) {
            $this->port = $port;
            $this->initializeMailer();
        } else {
            Logs::reportError("Unsupported port: {$port}. Please use a valid port (e.g., 587, 465, 25).", 2);
        }
    }

    /**
     * Switches the mailer model and reinitializes the mailer instance.
     *
     * @param string $model The mailer model to switch to ('php_mailer' or 'symfony').
     * @throws \InvalidArgumentException If the provided model is not supported.
     * @return void
     */
    public function switchModel(string $model)
    {
        if (!in_array($model, self::$validModels, true)) {
            Logs::reportError("Invalid mailer model specified: {$model}", 2);
            return;
        }
        $this->model = $model;
        $this->initializeMailer($model);
    }
    
    /**
     * Sends an email using the currently selected mailer model (PHPMailer or Symfony Mailer).
     *
     * This unified method provides a simple interface for sending emails, automatically delegating
     * to either `sendPHPMailer()` or `sendSymfony()` depending on the configured `$model` property.
     * It ensures the mailer is initialized before sending, and supports all features of both backends:
     * 
     * - SMTP transport with STARTTLS or SMTPS
     * - Custom "From" and "Reply-To" headers (with sensible defaults)
     * - Arbitrary additional headers (e.g., "X-Custom-Header: Value")
     * - HTML body with plain-text alternative
     * - Multiple file attachments
     * - Recipient name support via "email@example.com:Recipient Name" format
     * 
     * ### Parameters
     * - **$to**: Recipient address, optionally with name. Format: `"email@example.com"` or `"email@example.com:Recipient Name"`
     * - **$subject**: The subject line of the email.
     * - **$htmlBody**: The HTML content of the email. A plain-text alternative is generated automatically.
     * - **$headers**: (Optional) A string or array of additional headers. May include:
     *     - `"From: Name <email@domain.com>"` or `"From: email@domain.com:Name"` to override sender
     *     - `"Reply-To: ..."` to override reply-to
     *     - Any `"X-..."` or custom headers
     * - **$attachments**: (Optional) A string (single file path) or array of file paths to attach.
     * 
     * ### Return Value
     * Returns `true` if the email was sent (or queued) successfully, or `false` if an error occurred.
     * Errors are reported via `Logs::reportError()`.
     * 
     * ### Usage Examples
     * ```php
     * // Basic usage (default sender/reply-to, no attachments)
     * $mailer = new LCS_Mail('smtp.example.com', 'user@example.com', 'secret', 587, 'tls', 'php_mailer');
     * $mailer->send(
     *     'recipient@example.com:Recipient Name',
     *     'Welcome!',
     *     '<h1>Hello!</h1><p>Welcome to our service.</p>'
     * );
     * 
     * // With custom headers and a single attachment
     * $mailer->send(
     *     'recipient@example.com',
     *     'Invoice Attached',
     *     '<p>Your invoice is attached.</p>',
     *     [
     *         'From: Billing Dept <billing@company.com>',
     *         'Reply-To: support@company.com',
     *         'X-Company-ID: 12345'
     *     ],
     *     '/path/to/invoice.pdf'
     * );
     * 
     * // With multiple attachments
     * $mailer->send(
     *     'recipient@example.com:Jane Doe',
     *     'Documents',
     *     '<p>See attached files.</p>',
     *     [],
     *     [
     *         '/path/to/file1.pdf',
     *         '/path/to/file2.jpg'
     *     ]
     * );
     * 
     * // Switching backend to Symfony Mailer
     * $mailer->switchModel('symfony');
     * $mailer->send(
     *     'recipient@example.com',
     *     'Test via Symfony',
     *     '<p>This uses Symfony Mailer backend.</p>'
     * );
     * ```
     *
     * @param string          $to           Recipient address, optionally with name: "email@example.com:Recipient Name".
     * @param string          $subject      Email subject line.
     * @param string          $htmlBody     HTML content of the email.
     * @param string|array    $headers      (Optional) One or more headers like "X-Custom-Header: Value".
     * @param string|array    $attachments  (Optional) File path or array of file paths to attach.
     *
     * @return bool  Returns true if the mailer did not throw any exception.
     */
    public function send(string $to, string $subject, string $htmlBody, $headers = '', $attachments = ''): bool
    {
        // Ensure mailer is initialized
        if (!$this->mailer) {
            $this->initializeMailer();
        }

        switch ($this->model) {
            case 'php_mailer':
                return $this->sendPHPMailer($to, $subject, $htmlBody, $headers, $attachments);
            case 'symfony':
                return $this->sendSymfony($to, $subject, $htmlBody, $headers, $attachments);
            default:
                Logs::reportError("Unknown mailer model encountered during send().", 2);
                return false;
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
    public function sendPHPMailer($to, $subject, $message, $headers = '', $attachments = '')
    {
        try {
            if ($this->getModel() !== 'php_mailer') {
            $this->initializeMailer('php_mailer');
            }

            list($recipientEmail, $recipientName) = $this->parseRecipient($to);
            $this->setPMSenderAndReplyTo($headers);

            // Set recipient
            $this->mailer->addAddress($recipientEmail, $recipientName);

            // Set email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $message;
            $this->mailer->AltBody = strip_tags($message);

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
    public function sendSymfony(string $to, string $subject, string $htmlBody, $headers = '', $attachments = ''): bool
    {
        // 1) Initialize mailer, Normalize headers and attachments into arrays
        if ($this->getModel() !== 'symfony') {
            $this->initializeMailer('symfony');
        }
        $headersArray    = is_array($headers) ? $headers : (strlen(preg_replace('/\s+/', ' ', trim($headers))) > 0 ? [ $headers ] : []);
        $attachmentsList = is_array($attachments) ? $attachments : (strlen(trim($attachments)) > 0 ? [ $attachments ] : []);

        // 2) Parse recipient (email + optional name)
        list($recipientEmail, $recipientName) = $this->parseRecipient($to);

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

    /**
     * Returns the current mailer model in use.
     *
     * @return string The mailer model ('php_mailer' or 'symfony').
     */
    public function getModel(): string
    {
        return $this->model;
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

    /**
     * Validate email address.
     * 
     * @param string $email
     * @return bool
     */
    public function validateEmail($email) {
        // Remove all illegal characters from email
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        // Validate email address
        if ($email) {
           return trim($email);
        } else {
            return Logs::reportError("Invalid email: $email"); // Invalid email
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
            if (strpos($header, ':') !== false) {
                list($key, $value) = explode(':', $header, 2);
                $this->mailer->addCustomHeader(trim($key), trim($value));
            }
        }
    }

}
