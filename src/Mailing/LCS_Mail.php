<?php
namespace LCSNG\Tools\Mailing;

use LCSNG\Tools\Debugging\Logs;
use LCSNG\Tools\Utils\LCS_DirOps;

$mailingDependencies = [
    'MailingConfigs.php',
];
foreach ($mailingDependencies as $dep) {
    require_once $dep;
}

// Require all PHP files in "/MailingModels" using require_once
LCS_DirOps::requireAll(__DIR__ . '/MailingModels');

use MailingConfigs;
use PHPMailer;
use SymfonyMailer;
use Mailgun;

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
 *  - 'phpmailer': Uses PHPMailer for sending emails (recommended for broad compatibility)
 *  - 'symfony': Uses Symfony Mailer component (recommended for modern PHP apps)
 *
 * Usage example:
 * ```php
 * // 1) Instantiate with SMTP credentials and desired backend:
 * $mailer = new LCS_Mail('smtp.example.com', 'user@example.com', 'secret', 587, 'tls', 'phpmailer');
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
 * $mailer->setModel('symfony');
 * $mailer->send(
 *     'recipient@example.com',
 *     'Test via Symfony',
 *     '<p>This uses Symfony Mailer backend.</p>'
 * );
 * ```
 */
class LCS_Mail extends MailingConfigs 
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
     * @param string      $model       The mailer model/driver to use (e.g., 'phpmailer').
     *                                 Defaults to 'phpmailer'.
     *
     * @throws \InvalidArgumentException If the provided $port and $encryption combination is unsupported.
     */
    public function __construct(?string $host = null, ?string $username = null, ?string $password = null, int $port = 587, string $encryption = 'tls', string $model = 'phpmailer')
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

        $this->encryption = $encryption;
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
     * $mailer->setPort(465); // set to port 465 using SMTPS encryption
     * ```
     */
    public function setPort(int $port) 
    {
        if (in_array($port, self::$validPorts)) {
            $this->port = $port;
            $this->initializeMailer();
        } else {
            Logs::reportError("Unsupported port: {$port}. Please use a valid port (e.g., 587, 465, 25).", 2);
        }
    }

    /**
     * Switches the mailer model.
     *
     * @param string $model The mailer model to switch to ('phpmailer', 'symfony', etc).
     * @throws \InvalidArgumentException If the provided model is not supported.
     * @return void
     */
    public function setModel(string $model)
    {
        if (!in_array($model, self::$validModels, true)) {
            Logs::reportError("Invalid mailer model specified: {$model}", 2);
            return;
        }
        $this->model = $model;
    }

    /**
     * Sets the Mailing domain for sending emails.
     *
     * @param string $domain The Mailing domain.
     * @return void
     */
    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * Sets the Mailing API endpoint URL.
     *
     * @param string $endpoint The Mailing API endpoint URL.
     * @return void
     */
    public function setEndpoint(string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    /**
     * Sets the Mailing API key.
     *
     * @param string $apiKey The Mailing API key.
     * @return void
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
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
     * $mailer = new LCS_Mail('smtp.example.com', 'user@example.com', 'secret', 587, 'tls', 'phpmailer');
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
     * $mailer->setModel('symfony');
     * $mailer->send(
     *     'recipient@example.com',
     *     'Test via Symfony',
     *     '<p>This uses Symfony Mailer backend.</p>'
     * );
     * ```
     *
     * @param string|array          $to           Recipient address, optionally with name: "email@example.com:Recipient Name".
     * @param string          $subject      Email subject line.
     * @param string          $htmlBody     HTML content of the email.
     * @param string|array    $headers      (Optional) One or more headers like "X-Custom-Header: Value".
     * @param string|array    $attachments  (Optional) File path or array of file paths to attach.
     *
     * @return bool  Returns true if the mailer did not throw any exception.
     */
    public function send(string|array $to, string $subject, string $htmlBody, array|string $headers = '', array|string $attachments = ''): bool
    {
        // Ensure mailer is initialized
        if (!$this->mailer) {
            $this->initializeMailer();
        }

        switch ($this->model) {
            case 'phpmailer':
                $pm = new PHPMailer($this->host, $this->username, $this->password, $this->port, $this->encryption);
                return $pm->sendPHPMailer($to, $subject, $htmlBody, $headers, $attachments);
            case 'symfony':
                $sm = new SymfonyMailer($this->host, $this->username, $this->password, $this->port, $this->encryption);
                return $sm->sendSymfony($to, $subject, $htmlBody, $headers, $attachments);
            case 'mailgun':
                $mg = new Mailgun($this->domain, $this->apiKey, $this->endpoint);
                return $mg->sendMailgun($to, $subject, $htmlBody, $headers, $attachments);
            case 'resend':
                $mg = new ResendMailer($this->apiKey, $this->endpoint);
                return $mg->sendResend($to, $subject, $htmlBody, $headers, $attachments);
            default:
                Logs::reportError("Unknown mailer model encountered during send().", 2);
                return false;
        }
    }

}