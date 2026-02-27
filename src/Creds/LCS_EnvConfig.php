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
 * validates required variables, and exposes safe getters/setters.
 *
 * Security notes:
 * - When using encryption, the class expects the encrypted file content to be
 *   Base64-encoded, containing the IV and ciphertext separated by a colon
 *   before encoding: base64("{iv_bytes}:{ciphertext_bytes}").
 * - AES-256-CBC is used for broad compatibility. It does NOT provide
 *   authenticated encryption. Prefer AES-256-GCM or libsodium for stronger
 *   guarantees. If you switch ciphers, update decryptContent().
 * - Keep the encryption key out of the web root (e.g. server config or KMS).
 *   Never store the raw key in a repository.
 * - No temporary files are written to disk — parsing happens fully in memory.
 *
 * @package LCSNG\Tools\Creds
 */
class LCS_EnvConfig
{
    /** @var array List of required environment variable names */
    protected array $requiredVars = [];

    /** @var array In-memory cache of fetched/overridden env values */
    protected array $envCache = [];

    /** @var bool Whether the .env file is encrypted */
    protected bool $isEncrypted = false;

    /** @var string|null Encryption key used to decrypt the .env */
    protected ?string $encryptionKey = null;

    /**
     * Constructor
     *
     * Reads and (optionally) decrypts the specified .env file, then parses
     * it entirely in memory — no temp files are created on disk.
     *
     * @param string      $envPath       Directory containing the .env file
     * @param string      $envFile       Filename (e.g. '.env', '.env.prod', '.env.secure')
     * @param array       $requiredVars  Variable names that must be present after loading
     * @param bool        $isEncrypted   Set true if the file is AES-256-CBC encrypted
     * @param string|null $encryptionKey Raw key for decryption (required when $isEncrypted=true)
     *
     * @throws InvalidPathException  If the .env file does not exist or cannot be read
     * @throws \RuntimeException     If decryption fails or required vars are missing
     */
    public function __construct(
        string $envPath = '',
        string $envFile = '.env',
        array $requiredVars = [],
        bool $isEncrypted = false,
        ?string $encryptionKey = null
    ) {
        $this->requiredVars  = $requiredVars;
        $this->isEncrypted   = $isEncrypted;
        $this->encryptionKey = $encryptionKey;

        $envPath  = empty($envPath) ? __DIR__ : rtrim($envPath, '/\\');
        $fullPath = $envPath . DIRECTORY_SEPARATOR . $envFile;

        // --- Step 1: Read raw file ---
        if (!is_file($fullPath) || !is_readable($fullPath)) {
            throw new InvalidPathException(
                "Environment file not found or not readable: {$fullPath}"
            );
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new InvalidPathException("Failed to read environment file: {$fullPath}");
        }

        // --- Step 2: Decrypt if requested ---
        if ($this->isEncrypted) {
            if (empty($this->encryptionKey)) {
                throw new \RuntimeException("Encrypted .env requires a non-empty encryption key.");
            }
            $content = $this->decryptContent($content);
        }

        // --- Step 3: Parse in memory (no temp files!) ---
        $this->parseEnvString($content);

        // --- Step 4: Validate required variables ---
        $this->validateRequiredVars();
    }

    /**
     * Parse a raw .env string into $_ENV and $_SERVER using phpdotenv.
     *
     * Uses Dotenv::createArrayBacked() to parse without touching the
     * filesystem at all, then merges results into $_ENV / $_SERVER so
     * that the rest of the class (and the application) can read them
     * through the standard superglobals.
     *
     * @param string $content Raw .env file content (plain text)
     * @throws \RuntimeException If parsing fails
     */
    protected function parseEnvString(string $content): void
    {
        try {
            /*
             * createArrayBacked() accepts a key=>value array as the "repository".
             * By passing an empty array, Dotenv parses the string and returns
             * resolved variables — never touching $_ENV or disk on its own.
             * We then populate $_ENV/$_SERVER ourselves.
             */
            $parsed = Dotenv::parse($content);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to parse .env content: " . $e->getMessage(),
                0,
                $e
            );
        }

        foreach ($parsed as $key => $value) {
            // Respect existing values already set in the environment
            // (mirrors Dotenv::createImmutable behaviour)
            if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * Decrypt content encrypted with AES-256-CBC.
     *
     * Expected input format:
     *   base64( rawIvBytes . ':' . rawCiphertextBytes )
     *
     * To create a compatible encrypted file:
     * ```php
     * $iv         = random_bytes(16);
     * $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
     * $blob       = base64_encode($iv . ':' . $ciphertext);
     * file_put_contents('.env.secure', $blob);
     * ```
     *
     * @param  string $encrypted Base64-encoded "iv:ciphertext" blob
     * @return string Decrypted plaintext
     * @throws \RuntimeException On bad format or decryption failure
     */
    protected function decryptContent(string $encrypted): string
    {
        $decoded = base64_decode(trim($encrypted), true);
        if ($decoded === false) {
            throw new \RuntimeException("Encrypted .env content is not valid base64.");
        }

        $separatorPos = strpos($decoded, ':');
        if ($separatorPos === false) {
            throw new \RuntimeException(
                "Encrypted .env payload format invalid. Expected iv:ciphertext before base64 encoding."
            );
        }

        $iv         = substr($decoded, 0, $separatorPos);
        $ciphertext = substr($decoded, $separatorPos + 1);

        // AES-256-CBC requires exactly 16-byte IV
        if (strlen($iv) !== 16) {
            throw new \RuntimeException(
                sprintf(
                    "Invalid IV length: expected 16 bytes for AES-256-CBC, got %d.",
                    strlen($iv)
                )
            );
        }

        if ($ciphertext === '' || $ciphertext === false) {
            throw new \RuntimeException("Encrypted .env ciphertext is empty.");
        }

        $plain = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plain === false) {
            throw new \RuntimeException(
                "Decryption failed. Verify your key and that the file was encrypted with AES-256-CBC."
            );
        }

        return $plain;
    }

    /**
     * Validate that all required environment variables are present and non-empty.
     *
     * @throws \RuntimeException listing all missing variable names
     */
    protected function validateRequiredVars(): void
    {
        $missing = array_filter(
            $this->requiredVars,
            fn(string $var) => !$this->has($var)
        );

        if (!empty($missing)) {
            throw new \RuntimeException(
                "Missing required environment variable(s): " . implode(', ', $missing)
            );
        }
    }

    /**
     * Get an environment variable, with an optional default.
     *
     * Results are cached in memory so repeated calls are cheap.
     *
     * @param string $key     Variable name
     * @param mixed  $default Returned when the variable is absent or empty
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->envCache)) {
            return $this->envCache[$key];
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' && $default !== null && $default !== '') {
                $value = $default;
            }
        }

        return $this->envCache[$key] = $value;
    }

    /**
     * Check whether an environment variable is set and non-empty.
     *
     * @param string $key Variable name
     * @return bool
     */
    public function has(string $key): bool
    {
        // Check in-memory overrides first, then superglobals
        if (array_key_exists($key, $this->envCache)) {
            return $this->envCache[$key] !== null && $this->envCache[$key] !== '';
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        return $value !== null && $value !== '';
    }

    /**
     * Return all environment variables (superglobals merged with runtime overrides).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($_ENV, $this->envCache);
    }

    /**
     * Override or inject an environment variable at runtime (in-memory only).
     *
     * Does not persist to disk. Useful for testing or dynamic configuration.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set(string $key, mixed $value): void
    {
        $this->envCache[$key] = $value;
        $_ENV[$key]           = $value;
        $_SERVER[$key]        = $value;
    }
}