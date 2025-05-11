<?php
namespace LCSNG_EXT\DataValidation;

/**
 * Class LCS_InputSanitizer
 *
 * Provides methods for sanitizing user input and preparing data for database storage.
 *
 * @package LCSNG_EXT\DataValidation
 */
class LCS_InputSanitizer
{
    /**
     * Sanitize general user input (string or array of strings).
     *
     * @param mixed $input The input value to sanitize.
     * @return mixed The sanitized input.
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }

        if (!is_string($input)) {
            return $input; // Return as is if it's not a string.
        }

        return htmlspecialchars(trim(stripslashes($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize data for database input.
     *
     * @param mixed $data The data to sanitize (can be string, numeric, array, or object).
     * @return mixed The sanitized data, or null if unsupported type.
     */
    public static function sanitizeDatabaseInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeDatabaseInput'], $data);
        }

        if (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = self::sanitizeDatabaseInput($value);
            }
            return $data;
        }

        if (is_numeric($data)) {
            return $data; // Numeric values are generally safe.
        }

        if (is_string($data)) {
            return addslashes($data); // Escaping special characters for database storage.
        }

        return null; // Unsupported types return null for safe handling.
    }

    /**
     * Sanitize an email address.
     *
     * @param string $email The email address to sanitize.
     * @return string|null The sanitized email address, or null if invalid.
     */
    public static function sanitizeEmail($email)
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Sanitize a general string.
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    public static function sanitizeString($string)
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize a URL.
     *
     * @param string $url The URL to sanitize.
     * @return string|null The sanitized URL, or null if invalid.
     */
    public static function sanitizeURL($url)
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * Sanitize HTML content.
     *
     * @param string $html The HTML content to sanitize.
     * @return string The sanitized HTML.
     */
    public static function sanitizeHTML($html)
    {
        return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
