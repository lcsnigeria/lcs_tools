<?php
namespace LCSNG\Tools\Mailing;

use LCSNG\Tools\Debugging\Logs;

/**
 * Class MailingValidations
 *
 * Provides validation methods and utilities for mailing-related operations.
 *
 * @package Mailing
 */
class MailingValidations 
{
    /**
     * Parses a recipient string like:
     *   - "email@example.com:John Doe"
     *   - "email@example.com"
     *   - "John Doe <email@example.com>"
     *   - "email@example.com:John Doe"
     *
     * @param string $to  Raw input. If it contains “:”, the part after the first “:” is treated as the name.
     * @return array      [ 0 => valid email, 1 => name-or-empty ]
     *
     * @throws \InvalidArgumentException If the email portion is not a valid address.
     */
    public static function parseRecipient(string $to): array
    {
        $email = '';
        $name  = '';

        $to = trim($to);

        // Case 1: "Name <email@example.com>"
        if (preg_match('/^(.+?)\s*<\s*([^>]+)\s*>$/', $to, $m)) {
            $name  = trim($m[1]);
            $email = trim($m[2]);
        }
        // Case 2: "email@example.com:Name"
        elseif (strpos($to, ':') !== false) {
            $parts = explode(':', $to, 2);
            $email = trim($parts[0]);
            $name  = trim($parts[1]);
        }
        // Case 3: just "email@example.com"
        else {
            $email = $to;
            $name  = '';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid recipient email: {$email}");
        }

        return [$email, $name];
    }

    /**
     * Extracts headers from a string into an associative array.
     * Supports formats like:
     *   - "From: Full Name <username@example.com>"
     *   - "From: Full Name username@example.com"
     *   - "Custom-Header-Name: Value"
     * Returns: [ 'from' => 'Full Name username@example.com', 'custom-header-name' => 'Value', ... ]
     *
     * @param string $headerString
     * @return array
     */
    public static function extractMailHeaders(string $headerString): array
    {
        $headers = [];
        // Split by newlines or semicolons (support multi-line input)
        $lines = preg_split('/[\r\n;]+/', $headerString);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = strtolower(trim($key));
                $value = trim($value);

                // If "<...>" present, extract name and email
                if (preg_match('/^(.+?)\s*<([^>]+)>$/', $value, $m)) {
                    $nm = trim($m[1]);
                    $em = trim($m[2]);
                    $headers[$key] = empty($nm) ? $em : "$nm <$em>";
                } else {
                    // Remove all '<' and '>' characters from the value
                    $headers[$key] = preg_replace('/[<>]/', '', trim($value));
                }
            }
        }
        return $headers;
    }

    /**
     * Extracts the email address from any string.
     *
     * Examples:
     *   "From: Full Name <username@example.com>" => "username@example.com"
     *   "<username@example.com>" => "username@example.com"
     *   "From:username@example.com" => "username@example.com"
     *   "Full Name <username@example.com>" => "username@example.com"
     *   "Full Name username@example.com" => "username@example.com"
     *
     * @param string $input
     * @return string|null Returns the extracted email or null if not found.
     */
    public static function extractAddress(string $input): ?string
    {
        // Try to match <email@example.com>
        if (preg_match('/<\s*([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\s*>/', $input, $m)) {
            return $m[1];
        }
        // Try to match email after colon
        if (preg_match('/:\s*([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/', $input, $m)) {
            return $m[1];
        }
        // Try to match any email in the string
        if (preg_match('/([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/', $input, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extracts the name part from a string containing an email address.
     *
     * Examples:
     *   "From: Full Name <username@example.com>" => "Full Name"
     *   "<username@example.com>" => "username"
     *   "From:username@example.com" => "username"
     *   "Full Name <username@example.com>" => "Full Name"
     *   "Full Name username@example.com" => "Full Name"
     *
     * @param string $input
     * @return string|null Returns the extracted name or null if not found.
     */
    public static function extractAddressName(string $input): ?string
    {
        // Case: "Name <email@example.com>"
        if (preg_match('/^.*?([^\s<:][^<:]*?)?\s*<\s*([a-zA-Z0-9._%+\-]+)@([a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\s*>$/', $input, $m)) {
            $name = trim($m[1] ?? '');
            if ($name !== '') {
                return $name;
            }
            // No name, return username part
            return $m[2];
        }
        // Case: "something:email@example.com"
        if (preg_match('/:\s*([a-zA-Z0-9._%+\-]+)@([a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/', $input, $m)) {
            // Try to get name before colon
            $parts = explode(':', $input, 2);
            $before = trim($parts[0]);
            if ($before !== '') {
                return $before;
            }
            // No name, return username part
            return $m[1];
        }
        // Case: "Name email@example.com"
        if (preg_match('/^(.*?)\s+([a-zA-Z0-9._%+\-]+)@([a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})$/', $input, $m)) {
            $name = trim($m[1]);
            if ($name !== '') {
                return $name;
            }
            return $m[2];
        }
        // Case: just "email@example.com"
        if (preg_match('/([a-zA-Z0-9._%+\-]+)@([a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/', $input, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Validate email address.
     * 
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email) {
        // Remove all illegal characters from email
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        // Validate email address
        if ($email) {
           return trim($email);
        } else {
            return Logs::reportError("Invalid email: $email"); // Invalid email
        }
    }
}