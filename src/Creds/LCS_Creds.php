<?php
declare(strict_types=1);

namespace LCSNG\Tools\Creds;

use OTPHP\TOTP;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;

/**
 * Class LCS_Creds
 *
 * Manages secure credential storage and automatic key rotation.
 * Uses environment variables to store keys and ensures that sensitive 
 * data is refreshed periodically to maintain security.
 */
final class LCS_Creds {
    /**
     * Generates a cryptographically secure key with structured details.
     *
     * @param int $length The number of bytes for the secure key (default: 32).
     * @return string A securely generated key with appended structured details.
     * @throws \Exception If secure random bytes cannot be generated.
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
     * @param int|string|null ...$args The length and/or character type ('numbers', 'letters', 'random_bytes').
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
     * Generate QR code image as a Data URI.
     *
     * Supports:
     * - URLs
     * - Emails
     * - Text
     * - OTP URIs
     * - Payment payloads
     * - Custom application data
     *
     * @param string $data The data to encode.
     *
     * @return string QR code SVG Data URI.
     *
     * @example
     * $qr = LCS_Creds::generateQRCodeDataUri(
     *     'https://lcs.ng'
     * );
     *
     * echo '<img src="' . $qr . '">';
     */
    public static function generateQRCodeDataUri(
        string $data
    ): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel'        => EccLevel::H,
            'scale'           => 5,
        ]);

        $svg = (new QRCode($options))
            ->render($data);

        return 'data:image/svg+xml;base64,'
            . base64_encode($svg);
    }

    /**
     * Generate RFC-compliant TOTP secret.
     *
     * @param string $issuer The application/service name.
     *
     * @return string The generated TOTP secret.
     *
     * @example
     * $secret = LCS_Creds::generateTOTPSecret('LCS');
     */
    public static function generateTOTPSecret(
        string $issuer
    ): string
    {
        $totp = TOTP::create();

        $totp->setIssuer($issuer);

        return $totp->getSecret();
    }

    /**
     * Generate TOTP QR code as a Data URI.
     *
     * Compatible with:
     * - Google Authenticator
     * - Microsoft Authenticator
     * - Authy
     * - 1Password
     * - Bitwarden
     *
     * @param string $issuer The application/service name.
     * @param string $email  The user's email address.
     * @param string $secret The user's TOTP secret.
     *
     * @return string SVG QR code Data URI.
     *
     * @example
     * $qr = LCS_Creds::generateTOTPQRCode(
     *     'LCS',
     *     'user@email.com',
     *     $secret
     * );
     *
     * echo '<img src="' . $qr . '" alt="2FA QR Code">';
     */
    public static function generateTOTPQRCode(
        string $issuer,
        string $email,
        string $secret
    ): string
    {
        $totp = TOTP::create($secret);

        $totp->setLabel($email);
        $totp->setIssuer($issuer);

        $qrOptions = new QROptions([
            /**
             * SVG output:
             * - no Imagick dependency
             * - lightweight
             * - scalable
             * - production-friendly
             */
            'outputInterface' => QRMarkupSVG::class,

            /**
             * Higher ECC for better scan reliability.
             */
            'eccLevel' => EccLevel::H,

            /**
             * QR dimensions.
             */
            'scale' => 5,
        ]);

        return (new QRCode($qrOptions))
            ->render($totp->getProvisioningUri());
    }

    /**
     * Verify a Time-based One-Time Password (TOTP) code.
     *
     * Validates a user-submitted 6-digit TOTP code against the supplied
     * Base32-encoded secret using OTPHP. A configurable leeway is allowed
     * to accommodate minor differences between the user's device clock
     * and the server clock.
     *
     * The leeway is specified in seconds, not TOTP periods. For example,
     * a leeway of 10 allows approximately 10 seconds of clock drift.
     *
     * @param string $secret  The user's Base32-encoded TOTP secret.
     * @param string $code    The 6-digit TOTP code submitted by the user.
     * @param int    $leeway  Allowed clock drift in seconds. Defaults to 10.
     *
     * @return bool True if the TOTP code is valid, otherwise false.
     *
     * @throws \InvalidArgumentException If the secret, code, or leeway is invalid.
     * @throws \RuntimeException If OTPHP fails while verifying the code.
     *
     * @example
     * $isValid = LCS_Creds::verifyTOTPCode(
     *     $secret,
     *     $_POST['totp_code']
     * );
     *
     * if ($isValid) {
     *     echo 'TOTP verification successful.';
     * }
     */
    public static function verifyTOTPCode(
        string $secret,
        string $code,
        int $leeway = 10
    ): bool {
        if ($secret === '') {
            throw new \InvalidArgumentException(
                'TOTP secret is required.'
            );
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            throw new \InvalidArgumentException(
                'TOTP code must be exactly 6 digits.'
            );
        }

        if ($leeway < 0) {
            throw new \InvalidArgumentException(
                'TOTP leeway cannot be negative.'
            );
        }

        try {
            /*
            * Recreate the TOTP instance from the stored secret.
            *
            * createFromSecret() ensures that the same secret used during
            * enrollment is used to calculate and verify the OTP.
            */
            $totp = TOTP::createFromSecret($secret);

            /*
            * Verify the submitted code.
            *
            * The third argument is the allowed clock drift in seconds.
            * It is NOT the number of TOTP windows.
            */
            return $totp->verify(
                $code,
                null,
                $leeway
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Failed to verify TOTP code: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Set a configuration value in an INI file.
     *
     * Supports root-level keys, case-insensitive matching of existing keys,
     * and colon-qualified keys (e.g., "section:key") to target or create a specific section.
     *
     * Note: Because this method uses `parse_ini_file()` to read and rewrite the file,
     * any existing inline or standalone comments (`;` or `#`) in the file will be stripped
     * when changes are saved.
     *
     * @param string $configsFilePath The path to the INI configuration file.
     * @param string $key The configuration key to set (use "section:key" for a specific section).
     * @param mixed $value The value to assign to the configuration key (string, int, float, bool, or null).
     *
     * @throws \InvalidArgumentException If the configuration file does not exist or key format is invalid.
     * @throws \RuntimeException If the configuration file cannot be parsed or written.
     *
     * @example
     * ```php
     * // Update or add a root-level key (or first matching key across sections)
     * LCS_Creds::setFileConfig('/path/to/.configs', 'maintenance_mode', true);
     *
     * // Set a key inside a specific [section] (creates the section if it doesn't exist)
     * LCS_Creds::setFileConfig('/path/to/.configs', 'database:host', '127.0.0.1');
     * LCS_Creds::setFileConfig('/path/to/.configs', 'database:port', 3306);
     * ```
     */
    public static function setFileConfig(string $configsFilePath, string $key, mixed $value): void
    {
        if (!is_file($configsFilePath) || !is_readable($configsFilePath)) {
            throw new \InvalidArgumentException("Configuration file not found or not readable: {$configsFilePath}");
        }

        $configs = parse_ini_file($configsFilePath, true, INI_SCANNER_TYPED);
        if ($configs === false) {
            throw new \RuntimeException("Failed to parse configuration file: {$configsFilePath}");
        }

        // A colon-qualified key targets a key in a specific section.
        if (str_contains($key, ':')) {
            [$sectionName, $configKey] = explode(':', $key, 2);
            $sectionName = trim($sectionName);
            $configKey = trim($configKey);

            if ($sectionName === '' || $configKey === '') {
                throw new \InvalidArgumentException("Invalid section:key format provided: {$key}");
            }

            $section = null;
            foreach ($configs as $existingSection => $sectionValues) {
                if (is_array($sectionValues) && strcasecmp((string) $existingSection, $sectionName) === 0) {
                    $section = $existingSection;
                    break;
                }
            }

            if ($section === null) {
                $section = $sectionName;
                $configs[$section] = [];
            }

            $existingKey = null;
            foreach ($configs[$section] as $configuredKey => $configuredValue) {
                if (strcasecmp((string) $configuredKey, $configKey) === 0) {
                    $existingKey = $configuredKey;
                    break;
                }
            }

            $configs[$section][$existingKey ?? $configKey] = $value;
        } else {
            // Update the first matching key, regardless of its casing or section.
            $updated = false;
            foreach ($configs as $section => &$values) {
                if (is_array($values)) {
                    foreach ($values as $configuredKey => $configuredValue) {
                        if (strcasecmp((string) $configuredKey, $key) === 0) {
                            $values[$configuredKey] = $value;
                            $updated = true;
                            break 2;
                        }
                    }
                } elseif (strcasecmp((string) $section, $key) === 0) {
                    $values = $value;
                    $updated = true;
                    break;
                }
            }
            unset($values);

            if (!$updated) {
                $configs[$key] = $value;
            }
        }

        // Separate root keys and sections so root keys are always written at the top
        $rootContent = '';
        $sectionContent = '';

        foreach ($configs as $section => $values) {
            if (is_array($values)) {
                $sectionContent .= "\n[{$section}]\n";
                foreach ($values as $subKey => $subValue) {
                    $formatted = self::formatIniValue($subValue);
                    $sectionContent .= "{$subKey} = {$formatted}\n";
                }
            } else {
                $formatted = self::formatIniValue($values);
                $rootContent .= "{$section} = {$formatted}\n";
            }
        }

        $newContent = ltrim($rootContent . $sectionContent, "\n");

        // Write the updated content back to the file with an exclusive lock
        if (file_put_contents($configsFilePath, $newContent, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write updated configuration to file: {$configsFilePath}");
        }
    }

    /**
     * Get a configuration value from an INI file.
     *
     * Supports case-insensitive lookup of root-level keys, first-occurrence lookup
     * across sections, or targeted lookup using "section:key" syntax.
     *
     * @param string $configsFilePath The path to the INI configuration file.
     * @param string $key The configuration key to retrieve (use "section:key" for a specific section).
     * @param mixed $default Fallback value returned if the key does not exist.
     *
     * @return mixed The typed value of the configuration key, or $default if not found.
     *
     * @throws \InvalidArgumentException If the configuration file does not exist.
     * @throws \RuntimeException If the configuration file cannot be parsed.
     *
     * @example
     * ```php
     * // Retrieve a root key (or first matching key across sections)
     * \$isMaintenance = LCS_Creds::getFileConfig('/path/to/.configs', 'maintenance_mode', false);
     *
     * // Retrieve a key from a specific [section]
     * \$dbHost = LCS_Creds::getFileConfig('/path/to/.configs', 'database:host', 'localhost');
     * ```
     */
    public static function getFileConfig(string $configsFilePath, string $key, mixed $default = null): mixed
    {
        if (!is_file($configsFilePath) || !is_readable($configsFilePath)) {
            throw new \InvalidArgumentException("Configuration file not found or not readable: {$configsFilePath}");
        }

        $configs = parse_ini_file($configsFilePath, true, INI_SCANNER_TYPED);
        if ($configs === false) {
            throw new \RuntimeException("Failed to parse configuration file: {$configsFilePath}");
        }

        if (str_contains($key, ':')) {
            [$sectionName, $configKey] = explode(':', $key, 2);
            $sectionName = trim($sectionName);
            $configKey = trim($configKey);

            foreach ($configs as $section => $values) {
                if (is_array($values) && strcasecmp((string) $section, $sectionName) === 0) {
                    foreach ($values as $configuredKey => $value) {
                        if (strcasecmp((string) $configuredKey, $configKey) === 0) {
                            return $value;
                        }
                    }
                    return $default;
                }
            }

            return $default;
        }

        // Search in file order so an unqualified key returns its first occurrence.
        foreach ($configs as $section => $values) {
            if (is_array($values)) {
                foreach ($values as $configuredKey => $value) {
                    if (strcasecmp((string) $configuredKey, $key) === 0) {
                        return $value;
                    }
                }
            } elseif (strcasecmp((string) $section, $key) === 0) {
                return $values;
            }
        }

        return $default;
    }

    /**
     * Format a PHP value safely for INI file serialization.
     *
     * @param mixed $value
     * @return string
     */
    private static function formatIniValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Escape backslashes and double quotes inside string values
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);
        return "\"{$escaped}\"";
    }


}