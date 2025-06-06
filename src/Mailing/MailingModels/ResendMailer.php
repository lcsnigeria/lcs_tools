<?php
namespace lcsTools\Mailing;

use lcsTools\Debugging\Logs;
use lcsTools\Mailing\MailingValidations;
use InvalidArgumentException;

use MailingConfigs;

/**
 * Class ResendMailer
 *
 * Implements the Resend mailing model for sending emails via the Resend API.
 * Compatible with the LCS_Mail interface.
 */
class ResendMailer extends MailingConfigs
{
    /**
     * Resend constructor.
     *
     * @param string|null $apiKey   The Resend API key.
     * @param string|null $endpoint The Resend API endpoint (default: "https://api.resend.com/emails").
     */
    public function __construct(?string $apiKey = null, ?string $endpoint = null)
    {
        $this->apiKey   = $apiKey ?? $this->apiKey;
        $this->endpoint = $endpoint ?? 'https://api.resend.com/emails';
        $this->model    = 'resend';

        $this->initializeMailer('resend');
    }

    /**
     * Sends an email using the Resend API.
     *
     * @param string                $to           Recipient address, optionally with name in "email@example.com:Recipient Name" format.
     * @param string                $subject      Email subject line.
     * @param string                $htmlBody     HTML content of the email.
     * @param string|string[]       $headers      (Optional) Additional raw headers (e.g., "Reply-To: foo@bar.com").
     * @param string|string[]       $attachments  (Optional) File path or array of file paths to attach.
     *
     * @return bool True on success, false on failure.
     * @throws InvalidArgumentException If recipient parsing fails or directory/file checks fail.
     */
    public function sendResend(
        string $to,
        string $subject,
        string $htmlBody,
        array|string $headers = [],
        array|string $attachments = []
    ): bool {
        try {
            // Parse and validate recipient
            [$recipientEmail, $recipientName] = MailingValidations::parseRecipient($to);
            if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("sendResend(): Invalid recipient email '{$recipientEmail}'.");
            }

            $toHeader = $recipientName
                ? sprintf('%s <%s>', $recipientName, $recipientEmail)
                : $recipientEmail;

            // Base payload
            $payload = [
                'from'    => 'LCS Official <official@lcs.ng>',
                'to'      => [$toHeader],
                'subject' => $subject,
                'html'    => $htmlBody,
                'text'    => strip_tags($htmlBody),
            ];

            // Process custom headers
            $headersList = is_array($headers)
                ? array_map('trim', array_filter($headers, fn($h) => trim((string)$h) !== ''))
                : (trim((string)$headers) !== '' ? [trim($headers)] : []);
            foreach ($headersList as $header) {
                if (stripos($header, 'Reply-To:') === 0) {
                    $parsed = MailingValidations::extractMailHeaders($header);
                    if (!empty($parsed['Reply-To'])) {
                        $payload['reply_to'] = $parsed['Reply-To'];
                    }
                } elseif (stripos($header, 'From:') === 0) {
                    $parsed = MailingValidations::extractMailHeaders($header);
                    if (!empty($parsed['From'])) {
                        $payload['from'] = $parsed['From'];
                    }
                }
            }

            // Process attachments (Resend requires Base64 content)
            $attachmentsList = is_array($attachments)
                ? array_map('trim', array_filter($attachments, fn($a) => trim((string)$a) !== ''))
                : (trim((string)$attachments) !== '' ? [trim($attachments)] : []);

            if (!empty($attachmentsList)) {
                $payload['attachments'] = [];
                foreach ($attachmentsList as $filePath) {
                    if (!is_readable($filePath) || !is_file($filePath)) {
                        Logs::reportError("sendResend(): Attachment '{$filePath}' is not readable or does not exist.", 2);
                        continue;
                    }

                    $fileContents = file_get_contents($filePath);
                    if ($fileContents === false) {
                        Logs::reportError("sendResend(): Failed to read '{$filePath}'.", 2);
                        continue;
                    }

                    $payload['attachments'][] = [
                        'filename' => basename($filePath),
                        'content'  => base64_encode($fileContents),
                    ];
                }

                if (empty($payload['attachments'])) {
                    // If none of the attachments could be read, remove the key altogether
                    unset($payload['attachments']);
                }
            }

            // Encode payload as JSON
            $jsonPayload = json_encode($payload);
            if ($jsonPayload === false) {
                $jsonError = json_last_error_msg();
                Logs::reportError("sendResend(): JSON encoding failed - {$jsonError}", 2);
                return false;
            }

            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $this->endpoint,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_TIMEOUT        => 10,
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status >= 200 && $status < 300) {
                return true;
            }

            // Log detailed error
            $errorMessage = sprintf(
                "sendResend(): HTTP %d. cURL error: '%s'. Response: '%s'.",
                $status,
                $curlError,
                (string)$response
            );
            Logs::reportError($errorMessage, 2);
            return false;
        } catch (\Exception $e) {
            throw $e;
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}