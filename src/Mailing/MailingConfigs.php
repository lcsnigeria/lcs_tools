<?php
use lcsTools\Debugging\Logs;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Use the Mailgun class from mailgun/mailgun-php v4.2
use Mailgun\Mailgun;

/**
 * Class MailingConfigs
 *
 * Provides configuration management for mailing functionalities within the application.
 * This class is responsible for storing, retrieving, and validating various mailing-related
 * settings such as SMTP server details, sender information, and email templates.
 *
 * Usage:
 * - Instantiate this class to access or modify mailing configurations.
 * - Use provided methods to ensure consistent and secure handling of email settings.
 *
 * @package Mailing
 * @author  lcsng|JCFuniverse
 * @since   1.0.0
 */
class MailingConfigs 
{
    /** @var string SMTP host (e.g. "smtp.example.com") */
    protected $host;

    /** @var string SMTP username (login) */
    protected $username;

    /** @var string SMTP password */
    protected $password;

    /** @var int SMTP port (e.g. 587, 465, 25) */
    protected $port;
    protected static $validPorts = [587, 465, 25];

    /** @var string Encrypt method: 'tls' or 'ssl' */
    protected $encryption;

    /** @var string */
    protected $model = 'phpmailer';
    protected static $validModels = ['phpmailer', 'symfony', 'mailgun', 'resend'];

    /** @var string $apiKey Mailing API key used for authentication. */
    protected $apiKey;

    /** @var string $domain Mailing domain to be used for sending emails. */
    protected $domain;

    /** @var string $endpoint Mailing API endpoint URL. */
    protected $endpoint;

    protected $isApiBased = false;

    /** @var Mailer|PHPMailer|Mailgun|Resend */
    protected $mailer;

    /**
     * Initializes the mailer based on the specified model.
     *
     * This method selects and initializes the appropriate mailer implementation
     * depending on the provided model name. Supported models are defined in
     * self::$validModels. If no model is specified, the default model stored in
     * $this->model is used.
     *
     * @param string|null $model The mailer model to initialize ('phpmailer', 'symfony', etc.).
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

        if (!in_array($model, ['phpmailer', 'symfony'])) {
            $this->isApiBased = true;
        } else {
            $this->isApiBased = false;
        }

        // If model is not $this->isApiBased and yet host, username or password is null, throw error : reportError(..,2)
        if (!$this->isApiBased && (empty($this->host) || empty($this->username) || empty($this->password))) {
            Logs::reportError("SMTP host, username, and password must be set for non-API Based models.", 2);
        }

        $this->model = $model;
        $this->mailer = null;
        switch ($model) {
            case 'phpmailer':
                $this->initializePHPMailer();
                break;
            
            case 'symfony':
                $this->initializeSymfony();
                break;
            case 'mailgun':
                if (!$this->apiKey || !$this->domain) {
                    Logs::reportError("Mailgun API key and domain must be set for Mailgun model.", 2);
                    break;
                }
                // Instantiate the client.
                $this->mailer = Mailgun::create($this->apiKey, $this->endpoint);
                break;
            case 'resend':
                if (!$this->apiKey) {
                    Logs::reportError("Resend API key must be set for Resend model.", 2);
                    break;
                }
                // Instantiate the client.
                $this->mailer = Resend::client($this->apiKey);
                break;
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
     * Returns the current mailer model in use.
     *
     * @return string The mailer model ('phpmailer' or 'symfony').
     */
    protected function getModel(): string
    {
        return $this->model;
    }
}