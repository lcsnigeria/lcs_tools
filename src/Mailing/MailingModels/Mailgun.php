<?php
use LCSNG\Tools\Debugging\Logs;
use LCSNG\Tools\Mailing\MailingValidations;

class Mailgun extends MailingConfigs
{
    /**
     * Mailgun constructor.
     *
     * Initializes the Mailgun mailer with the provided configuration.
     *
     * @param string|null $domain   The Mailgun domain to use for sending emails.
     * @param string|null $apiKey   The Mailgun API key.
     * @param string|null $endpoint The Mailgun API endpoint URL.
     *
     * @throws \InvalidArgumentException If required parameters are missing.
     */
    public function __construct(?string $domain = null, ?string $apiKey = null, ?string $endpoint = null)
    {
        $this->domain   = $domain ?? $this->domain;
        $this->apiKey   = $apiKey ?? $this->apiKey;
        $this->endpoint = $endpoint ?? 'https://api.mailgun.net';
        $this->model    = 'mailgun';

        $this->initializeMailer('mailgun');
    }

    /**
     * Sends an email using the Mailgun service.
     *
     * @param string       $to          The recipient's email address.
     * @param string       $subject     The subject of the email.
     * @param string       $htmlBody    The HTML content of the email body.
     * @param array|string $headers     Optional. Additional email headers as an associative array or a raw string.
     * @param array|string $attachments Optional. File attachments as an array of file paths or a single file path string.
     *
     * @return bool Returns true if the email was sent successfully, false otherwise.
     *
     * @throws \Exception If there is an error during the sending process.
     *
     * This method integrates with the Mailgun API to send emails with optional headers and attachments.
     * It supports both single and multiple recipients, as well as flexible header and attachment formats.
     */
    public function sendMailgun(string $to, string $subject, string $htmlBody, array|string $headers = '', array|string $attachments = ''): bool
    {
        // Parse recipient
        list($recipientEmail, $recipientName) = MailingValidations::parseRecipient($to);
        $toHeader = $recipientName ? "{$recipientName} <{$recipientEmail}>" : $recipientEmail;

        // Prepare message data
        $messageData = [
            'from'    => 'LCS Official <official@lcs.ng>',
            'to'      => $toHeader,
            'subject' => $subject,
            'html'    => $htmlBody,
            'text'    => strip_tags($htmlBody),
        ];

        // Handle headers
        $headersArray = is_array($headers) ? $headers : (strlen(trim($headers)) > 0 ? [$headers] : []);
        foreach ($headersArray as $header) {
            $header = trim($header);
            if (stripos($header, 'Reply-To:') === 0) {
                $messageData['h:Reply-To'] = MailingValidations::extractMailHeaders($header)['Reply-To'];
            } elseif (stripos($header, 'From:') === 0) {
                $messageData['from'] = MailingValidations::extractMailHeaders($header)['From'];
            } elseif (strpos($header, ':') !== false) {
                list($key, $value) = explode(':', $header, 2);
                $messageData['h:' . trim($key)] = trim($value);
            }
        }

        // Send simple email if no attachments
        if (empty($attachments)) {
            try {
            $response = $this->mailer->messages()->send($this->domain, $messageData);
            if ($response && $response->getId()) {
                return true;
            } else {
                Logs::reportError("Mailgun send failed: No message ID returned.", 2);
                return false;
            }
            } catch (\Exception $e) {
            Logs::reportError("Mailgun send failed: " . $e->getMessage(), 2);
            return false;
            }
        }

        // Prepare attachments
        $attachmentsList = is_array($attachments) ? $attachments : (strlen(trim($attachments)) > 0 ? [$attachments] : []);
        $curlFiles = [];
        foreach ($attachmentsList as $path) {
            if (file_exists($path) && is_readable($path)) {
                $curlFiles[] = curl_file_create($path);
            }
        }

        // Build POST fields
        $postFields = $messageData;
        if (!empty($curlFiles)) {
            foreach ($curlFiles as $file) {
                $postFields['attachment'][] = $file;
            }
        }

        // Send via cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $this->apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        $result = curl_exec($ch);
        $error  = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 200) {
            return true;
        } else {
            Logs::reportError("Mailgun send failed: HTTP $status, $error, Response: $result", 2);
            return false;
        }
    }
}