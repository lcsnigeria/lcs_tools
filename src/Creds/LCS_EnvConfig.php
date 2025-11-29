<?php
declare(strict_types=1);

namespace LCSNG\Tools\Creds;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Dotenv\Exception\InvalidFileException;

/**
 * LCS_EnvConfig
 *
 * Loads a .env file (optionally encrypted), parses it via vlucas/phpdotenv,
 * validates required variables and exposes safe getters/setters.
 *
 * Security notes:
 * - When using encryption, the class expects the encrypted file content to be
 *   Base64-encoded and to contain the IV and ciphertext separated by a colon
 *   before encoding (i.e. base64("{iv}:{ciphertext}")).
 * - AES-256-CBC is used here for compatibility with many shared-hosting
 *   environments. For stronger authenticated encryption prefer libsodium or
 *   AES-256-GCM. If you switch to a different cipher, update decryptContent().
 * - Keep the encryption key out of the web root (for example in server
 *   configuration or a cloud KMS). Do not store the raw key in a repository.
 *
 * Example (encrypted .env):
 * ```php
 * use LCSNG\Tools\Creds\LCS_EnvConfig;
 *
 * // MASTER_KEY should be provided securely (e.g. via server config)
 * $env = new LCS_EnvConfig(
 *     envPath: __DIR__,
 *     envFile: '.env.secure',
 *     requiredVars: ['DB_HOST','DB_USER','DB_PASS'],
 *     isEncrypted: true,
 *     encryptionKey: $_SERVER['MASTER_KEY'] ?? null
 * );
 *
 * $dbUser = $env->get('DB_USER');
 *
 * ```
 *
 * Example (unencrypted .env):
 * ```php
 * $env = new LCS_EnvConfig(envPath: __DIR__, envFile: '.env', requiredVars: ['APP_ENV']);
 * echo $env->get('APP_ENV', 'production');
 * ```
 *
 * @package LCSNG\Tools\Creds
 */
class LCS_EnvConfig
{
    /** @var Dotenv|null Dotenv instance used to load environment variables */
    protected $dotenv;

    /** @var array List of required environment variable names */
    protected $requiredVars = [];

    /** @var array Cache of fetched env values (in-memory only) */
    protected $envCache = [];

    /** @var bool Whether the .env file is encrypted */
    protected $isEncrypted = false;

    /** @var string|null Encryption key used to decrypt the .env when encrypted */
    protected $encryptionKey = null;

    /**
     * Constructor
     *
     * Loads and (optionally) decrypts the specified .env file then delegates
     * parsing to vlucas/phpdotenv. A temporary runtime file is created and
     * immediately removed after loading — nothing persistent is written.
     *
     * @param string      $envPath       Path to the directory containing the .env file
     * @param string      $envFile       Name of the .env file (e.g. '.env', '.env.prod')
     * @param array       $requiredVars  List of required environment variables
     * @param bool        $isEncrypted   If true, the file will be decrypted before parsing
     * @param string|null $encryptionKey Raw encryption key used by decryptContent()
     *
     * @throws InvalidPathException If the .env file path is invalid or missing
     * @throws InvalidFileException If the .env file is malformed for Dotenv
     * @throws \Exception If decryption fails or required vars are missing
     *
     * @example
     * // Typical usage on shared hosting (key pulled from server config):
     * $cfg = new LCS_EnvConfig(__DIR__, '.env.secure', ['DB_PASS'], true, $_SERVER['MASTER_KEY']);
     */
    public function __construct(
        string $envPath = '',
        string $envFile = '.env',
        array $requiredVars = [],
        bool $isEncrypted = false,
        ?string $encryptionKey = null
    ) {
        $this->requiredVars = $requiredVars;
        $this->isEncrypted  = $isEncrypted;
        $this->encryptionKey = $encryptionKey;

        $envPath = empty($envPath) ? __DIR__ : rtrim($envPath, '/');
        $fullPath = $envPath . '/' . $envFile;

        if (!file_exists($fullPath)) {
            throw new InvalidPathException("Environment file not found: {$fullPath}");
        }

        // Step 1: Load file raw
        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new \Exception("Failed to read environment file: {$fullPath}");
        }

        // Step 2: Decrypt if requested
        if ($this->isEncrypted) {
            if (empty($this->encryptionKey)) {
                throw new \Exception("Encrypted .env requires an encryption key.");
            }

            $content = $this->decryptContent($content);
        }

        // Step 3: Create a temporary decrypted version in memory (runtime file)
        $tempPath = $envPath . '/.env.runtime.tmp';
        file_put_contents($tempPath, $content);

        // Step 4: Load via Dotenv and validate
        try {
            $this->dotenv = Dotenv::createImmutable($envPath, '.env.runtime.tmp');
            $this->dotenv->load();

            $this->validateRequiredVars();
        } catch (InvalidPathException|InvalidFileException $e) {
            throw $e;
        } finally {
            // Step 5: Auto-clean (security!) — remove the temporary file
            @unlink($tempPath);
        }
    }

    /**
     * Decrypt content encrypted with AES-256-CBC
     *
     * Expected input format: base64("{iv}:{ciphertext}") where {iv} is the
     * raw IV bytes (not hex) and {ciphertext} is the raw encrypted bytes.
     *
     * NOTE: This implementation uses AES-256-CBC for broad compatibility. It
     * does NOT provide authenticated encryption. If the integrity of the
     * encrypted payload matters, prefer AES-256-GCM or libsodium's crypto
     * constructions.
     *
     * @param string $encrypted Base64 encoded string containing "iv:ciphertext"
     * @return string Decrypted plaintext content
     * @throws \Exception If the payload is not valid base64 or decryption fails
     *
     * @example
     * // Suppose you created the encrypted blob on another server like:
     * // $blob = base64_encode($iv . ':' . $ciphertext);
     * // store $blob in .env.secure file. Then use:
     * // $plain = $this->decryptContent(file_get_contents('.env.secure'));
     */
    protected function decryptContent(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            throw new \Exception("Encrypted .env content is not valid base64.");
        }

        $parts = explode(':', $decoded, 2);
        if (count($parts) !== 2) {
            throw new \Exception("Encrypted .env payload format invalid, expected iv:ciphertext before base64.");
        }

        [$iv, $ciphertext] = $parts;

        // Optional: Validate IV length for AES-256-CBC (16 bytes)
        if (strlen($iv) !== 16) {
            // Not fatal — but likely indicates an incompatible payload
            throw new \Exception("Invalid IV length for AES-256-CBC: expected 16 bytes.");
        }

        $plain = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plain === false) {
            throw new \Exception("Failed to decrypt .env file. Check your key and payload format.");
        }

        return $plain;
    }

    /**
     * Validate that all required environment variables are set
     *
     * @throws \Exception If any required variable is missing
     */
    protected function validateRequiredVars(): void
    {
        $missing = [];
        foreach ($this->requiredVars as $var) {
            if (!$this->has($var)) $missing[] = $var;
        }
        if (!empty($missing)) {
            throw new \Exception("Missing required env vars: " . implode(', ', $missing));
        }
    }

    /**
     * Get an environment variable value
     *
     * This method caches results in-memory so subsequent calls are fast.
     *
     * @param string $key The environment variable name
     * @param mixed  $default Default value if the variable is not set
     * @return mixed The variable value or default
     *
     * @example
     * $env = new LCS_EnvConfig(__DIR__, '.env');
     * $db = $env->get('DB_NAME', 'app_db');
     */
    public function get(string $key, $default = null)
    {
        if (array_key_exists($key, $this->envCache)) {
            return $this->envCache[$key];
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;

        // Sanitize and cache the value
        if (is_string($value)) {
            $value = trim($value);
            // Prevent empty string if default is expected
            if ($value === '' && $default !== '') {
                $value = $default;
            }
        }

        $this->envCache[$key] = $value;
        return $value;
    }

    /**
     * Check if an environment variable is set
     *
     * @param string $key The environment variable name
     * @return bool True if the variable is set and not empty
     *
     * @example
     * if ($env->has('FEATURE_FLAG')) { ... }
     */
    public function has(string $key): bool
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        return isset($value) && $value !== '';
    }

    /**
     * Get all loaded environment variables (merged with cached values)
     *
     * @return array All environment variables that were parsed and cached
     */
    public function all(): array
    {
        return array_merge($_ENV, $this->envCache);
    }

    /**
     * Set an environment variable (in-memory only)
     *
     * Use when you need to override or inject values at runtime. This change
     * does not persist to disk and will be lost after the process ends.
     *
     * @param string $key The environment variable name
     * @param mixed  $value The value to set
     * @return void
     *
     * @example
     * $env->set('CACHE_TTL', 3600);
     */
    public function set(string $key, $value): void
    {
        $this->envCache[$key] = $value;
        $_ENV[$key] = $value;
    }
}