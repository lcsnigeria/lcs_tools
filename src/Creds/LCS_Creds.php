<?php
namespace LCSNG_EXT\Creds;

/**
 * Class LCS_Creds
 *
 * Manages secure credential storage and automatic key rotation.
 * Uses environment variables to store keys and ensures that sensitive 
 * data is refreshed periodically to maintain security.
 */
class LCS_Creds {
    /**
     * Key refresh interval in seconds (24 hours).
     */
    const KEY_REFRESH_INTERVAL = 24 * 60 * 60;

    /**
     * Path to the environment (.env) file where keys are stored.
     */
    const CREDS_ENV_FILE = __DIR__ . "/.env";

    /**
     * Instance of Dotenv for managing environment variables.
     *
     * @var \Dotenv\Dotenv
     */
    private $CredsEnv;

    /**
     * Constructor.
     *
     * Initializes the environment and ensures secure keys are available.
     */
    public function __construct()
    {
        /**
         * Load environment variables from the .env file.
         */
        $this->CredsEnv = \Dotenv\Dotenv::createImmutable(__DIR__);
        $this->CredsEnv->load();

        // Ensure keys are initialized and refreshed if necessary
        $this->initializeKeys();
    }

    /**
     * Ensures that keys are initialized and refreshed if necessary.
     *
     * This method is called on class instantiation to check whether the stored
     * keys are valid or need to be refreshed based on the configured interval.
     */
    private function initializeKeys() 
    {
        if ($this->is_last_key_refresh_time_expired() || empty($this->get_nonce_secret_key())) {
            $this->refresh_keys();
        }
    }

    /**
     * Retrieves the current nonce secret key.
     *
     * @return string The stored nonce secret key or an empty string if not set.
     */
    public function get_nonce_secret_key() 
    {
        return $_ENV['NONCE_SECRET_KEY'] ?? '';
    }

    /**
     * Retrieves the last key refresh timestamp from the session.
     *
     * @return int The UNIX timestamp of the last refresh or 0 if not available.
     */
    private function get_last_key_refresh_time() 
    {
        $this->start_session();
        return $_SESSION['LCS_CREDS_LAST_REFRESH_TOKEN'] ?? 0;
    }

    /**
     * Checks if the last key refresh time has expired.
     *
     * @return bool True if the keys need to be refreshed, false otherwise.
     */
    private function is_last_key_refresh_time_expired() 
    {
        return (time() - $this->get_last_key_refresh_time()) >= self::KEY_REFRESH_INTERVAL;
    }

    /**
     * Generates new secure keys and updates the .env file.
     *
     * This method ensures that all critical security keys are rotated and stored
     * securely in the environment file. It also updates the last refresh timestamp.
     */
    private function refresh_keys() 
    {
        $keys = [];

        // Generate a fresh nonce secret key
        $keys['NONCE_SECRET_KEY'] = $this->generate_key();

        // Convert the keys array to a properly formatted .env content string
        $keysEnvContent = '';
        foreach ($keys as $key => $value) {
            $keysEnvContent .= "{$key}={$value}\n";
        }

        // Overwrite the existing .env file with the new keys
        file_put_contents(self::CREDS_ENV_FILE, $keysEnvContent);

        // Update session with the new refresh timestamp
        $this->start_session();
        $_SESSION['LCS_CREDS_LAST_REFRESH_TOKEN'] = time();
    }

    /**
     * Generates a cryptographically secure key with structured details.
     *
     * @param int $length The number of bytes for the secure key (default: 32).
     * @return string A securely generated key with appended structured details.
     * @throws Exception If secure random bytes cannot be generated.
     */
    public function generate_key(int $length = 32): string 
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
     * Starts a session if not already active.
     *
     * @return bool True if session started or already active.
     */
    private function start_session()
    {
        if (session_status() === PHP_SESSION_NONE) {
            return session_start();
        }
        return true;
    }

    /**
     * Generates a random password.
     *
     * @param int $length The length of the password.
     * @param bool $special_chars Whether to include special characters.
     * @param bool $extra_special_chars Whether to include extra special characters.
     * @return string The generated password.
     */
    public function generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
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
    public function password_strength($password) {
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
    public function hash_password($password) {
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
    public function verify_password($password, $hashed_password) {
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
    public function generate_code(...$args) {
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

}