<?php
namespace lcsTools\Mailing;

use lcsTools\Debugging\Logs;

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
     * Returns: [ 'From' => 'Full Name username@example.com', 'Custom-Header-Name' => 'Value', ... ]
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
                $key = trim($key);
                $value = trim($value);

                // For "From" header, normalize "Full Name <email>" or "Full Name email"
                if (strtolower($key) === 'from') {
                    // If "<...>" present, extract name and email
                    if (preg_match('/^(.+?)\s*<([^>]+)>$/', $value, $m)) {
                        $headers[$key] = trim($m[1]) . ' ' . trim($m[2]);
                    } else {
                        $headers[$key] = $value;
                    }
                } else {
                    $headers[$key] = $value;
                }
            }
        }
        return $headers;
    }

    /**
     * Given a header fragment (e.g. "John Doe <john@example.com>" or "john@example.com:John Doe"), 
     * extracts [ 'email' => 'john@example.com', 'name' => 'John Doe' ] or returns false on failure.
     *
     * @param string $fragment
     * @return array|false
     */
    public static function extractHeaderAddress(string $fragment)
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