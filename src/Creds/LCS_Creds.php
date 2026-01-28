<?php
namespace LCSNG\Tools\Creds;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

/**
 * Class LCS_Creds
 *
 * Manages secure credential storage and automatic key rotation.
 * Uses environment variables to store keys and ensures that sensitive 
 * data is refreshed periodically to maintain security.
 */
class LCS_Creds {
    /**
     * Generates a cryptographically secure key with structured details.
     *
     * @param int $length The number of bytes for the secure key (default: 32).
     * @return string A securely generated key with appended structured details.
     * @throws Exception If secure random bytes cannot be generated.
     */
    public static function generateKey(int $length = 32): string 
    {
        // Generate a secure random key
        $secureKey = bin2hex(random_bytes($length));

        // Append structured details (e.g., timestamp, unique ID, hash)
        $timestamp = time(); // Current UNIX timestamp
        $uniqueId = uniqid(); // Unique identifier based on microtime
        $checksum = substr(hash('sha256', $secureKey . $timestamp . $uniqueId), 0, 8); // Short hash

        return "key_{$secureKey}_{$timestamp}_{$uniqueId}_{$checksum}";
    }

    /**
     * Generates a random password.
     *
     * @param int $length The length of the password.
     * @param bool $special_chars Whether to include special characters.
     * @param bool $extra_special_chars Whether to include extra special characters.
     * @return string The generated password.
     */
    public static function generatePassword($length = 12, $special_chars = true, $extra_special_chars = false) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }
        if ($extra_special_chars) {
            $chars .= '-_ []{}<>~`+=,.;:/?|';
        }

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }


    /**
     * Evaluate the strength of a given password.
     *
     * @param string $password The password to evaluate.
     *
     * @return string|bool The strength of the password ('short', 'weak', 'medium', 'strong') 
     *                     or false if the password is empty or does not meet any defined criteria.
     */
    public static function passwordStrength($password) {
        $hasDigits = preg_match('/\d/', $password);
        $hasUppercase = preg_match('/[A-Z]/', $password);
        $hasLowercase = preg_match('/[a-z]/', $password);
        $hasSpecialChars = preg_match('/[\W_]/', $password);

        if (strlen($password) > 0 && strlen($password) <= 5) {
            return 'short';
        } elseif (
            strlen($password) >= 6 && 
            (!$hasDigits || !$hasSpecialChars) && 
            (!$hasUppercase || !$hasLowercase)
        ) {
            return 'weak';
        } elseif (
            strlen($password) >= 6 && 
            !$hasDigits && !$hasSpecialChars && 
            (!$hasUppercase || $hasUppercase)
        ) {
            return 'weak';
        } elseif (
            (strlen($password) >= 6 && strlen($password) < 8) &&
            $hasDigits &&
            $hasUppercase &&
            $hasLowercase &&
            (!$hasSpecialChars || $hasSpecialChars)
        ) {
            return 'medium';
        } elseif (
            strlen($password) >= 6 &&
            (!$hasDigits || !$hasSpecialChars) &&
            $hasUppercase &&
            $hasLowercase
        ) {
            return 'medium';
        } elseif (
            strlen($password) >= 8 &&
            $hasDigits &&
            $hasUppercase &&
            $hasLowercase &&
            $hasSpecialChars
        ) {
            return 'strong';
        } elseif ($password === '' || strlen($password) <= 0) {
            return false; // Password does not meet any defined criteria
        }
    }


    /**
     * Hashes a given password using a secure algorithm.
     *
     * @param string $password The password to hash.
     * @return string The hashed password.
     */
    public static function hashPassword($password) {
        // Hash the password using the default algorithm (currently bcrypt)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Return the hashed password
        return $hashed_password;
    }

    /**
     * Verifies a given password against a hashed password.
     *
     * @param string $password The password to verify.
     * @param string $hashed_password The hashed password to verify against.
     * @return bool True if the password matches the hashed password, false otherwise.
     */
    public static function verifyPassword($password, $hashed_password) {
        // Verify the password against the hashed password
        $password_check_result = password_verify($password, $hashed_password);

        // Return the result of the password verification (true or false)
        return $password_check_result;
    }

    /**
     * Generate a random code of specified length and character type.
     *
     * @param int|string|null $arg1    The length of the code or character type ('numbers', 'letters', 'random_bytes'). Default is 32.
     * @param int|string|null $arg2    The type of characters to include in the code or length if $arg1 is character type. Default is 'random_bytes'.
     * @return string|bool The generated code or false if an invalid character type is provided.
     */
    public static function generateCode(...$args) {
        // Default values
        $default_length = 32; // Using 32 bytes (256 bits) for a secure secret key
        $default_character_type = 'random_bytes';

        // Initialize variables
        $length = null;
        $character_type = null;

        // Process arguments
        foreach ($args as $arg) {
            if (is_numeric($arg)) {
                $length = intval($arg);
            } elseif (is_string($arg) && in_array($arg, ['numbers', 'letters', 'random_bytes'])) {
                $character_type = $arg;
            }
        }

        // Set length and character type based on provided or default values
        if ($character_type === null) {
            $character_type = $default_character_type;
        }
        
        if ($length === null) {
            $length = $default_length;
        }

        // Define character sets
        $numbers = '0123456789';
        $letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // Determine character set based on provided character type
        switch ($character_type) {
            case 'numbers':
                $characters = $numbers;
                break;
            case 'letters':
                $characters = $letters;
                break;
            case 'random_bytes':
                // Generate random bytes and convert to hexadecimal
                $code = bin2hex(random_bytes($length));
                return $code; // Return full generated code
            default:
                // Invalid character type provided
                return false;
        }

        // Generate the code
        $code_length = $length;
        $code = '';
        $max_index = strlen($characters) - 1;
        for ($i = 0; $i < $code_length; $i++) {
            $code .= $characters[rand(0, $max_index)];
        }

        return $code;
    }

    /**
     * Generate QR code image as a data URI (endroid/qr-code v6).
     *
     * @param string $data - The data to encode in the QR code.
     * @return string - The QR code image as a data URI.
     *
     * Usage:
     * <img src="<?= $qrImage ?>" alt="2FA QR Code">
     */
    public static function generateQRCodeDataUri(string $data): string
    {
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 200,
            margin: 10
        );

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return $result->getDataUri();
    }


}