<?php
namespace LCSNG\Tools\Creds;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Dotenv\Exception\InvalidFileException;

/**
 * LCS_EnvConfig
 *
 * A class to manage loading and accessing environment variables from a .env file.
 * Features:
 * - Loads environment variables from a specified .env file
 * - Validates required environment variables
 * - Provides safe access to environment variables with defaults
 * - Supports multiple environments (e.g., dev, prod)
 * - Secure handling of sensitive configuration data
 *
 * @author  lcsnigeria
 * @license MIT
 */
class LCS_EnvConfig
{
    /**
     * @var Dotenv|null Dotenv instance
     */
    protected $dotenv;

    /**
     * @var array Required environment variables
     */
    protected $requiredVars = [];

    /**
     * @var array Cached environment variables
     */
    protected $envCache = [];

    /**
     * Constructor
     *
     * @param string $envPath  Path to the directory containing the .env file
     * @param string $envFile  Name of the .env file (e.g., '.env', '.env.prod')
     * @param array  $requiredVars List of required environment variables
     * @throws InvalidPathException If the .env file path is invalid
     * @throws InvalidFileException If the .env file is malformed
     * @throws Exception If required variables are missing
     */
    public function __construct(string $envPath = '', string $envFile = '.env', array $requiredVars = [])
    {
        $this->requiredVars = $requiredVars;
        $envPath = empty($envPath) ? __DIR__ : rtrim($envPath, '/');

        try {
            // Initialize Dotenv
            $this->dotenv = Dotenv::createImmutable($envPath, $envFile);
            $this->dotenv->load();

            // Validate required variables
            $this->validateRequiredVars();
        } catch (InvalidPathException $e) {
            throw new InvalidPathException("Environment file path invalid: {$e->getMessage()}");
        } catch (InvalidFileException $e) {
            throw new InvalidFileException("Environment file malformed: {$e->getMessage()}");
        }
    }

    /**
     * Validate that all required environment variables are set
     *
     * @throws Exception If any required variable is missing
     */
    protected function validateRequiredVars(): void
    {
        $missing = [];
        foreach ($this->requiredVars as $var) {
            if (!$this->has($var)) {
                $missing[] = $var;
            }
        }

        if (!empty($missing)) {
            throw new \Exception("Missing required environment variables: " . implode(', ', $missing));
        }
    }

    /**
     * Get an environment variable value
     *
     * @param string $key     The environment variable name
     * @param mixed  $default Default value if the variable is not set
     * @return mixed The variable value or default
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
     */
    public function has(string $key): bool
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        return isset($value) && $value !== '';
    }

    /**
     * Get all environment variables
     *
     * @return array All environment variables
     */
    public function all(): array
    {
        return array_merge($_ENV, $this->envCache);
    }

    /**
     * Set an environment variable (in-memory only)
     *
     * @param string $key   The environment variable name
     * @param mixed  $value The value to set
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->envCache[$key] = $value;
        $_ENV[$key] = $value;
    }
}