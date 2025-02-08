<?php
namespace LCSNG_EXT\Requests;

/**
 * Handles request, routing, including retrieving URI segments, managing request and session variables, 
 * and handling error reporting.
 */
class LCS_Request
{
    /** @var bool Whether to report errors as exceptions */
    public $reportErrors;

    /**
     * Constructor for initializing error reporting.
     *
     * @param bool $reportErrors Whether to throw exceptions on errors.
     */
    public function __construct(bool $reportErrors = false)
    {
        $this->reportErrors = $reportErrors;
    }

    /**
     * Retrieves the full URI of the current request.
     *
     * @param bool $stripQueryArgs Whether to remove query parameters from the URI.
     * @return string The request URI.
     */
    public function get_uri(bool $stripQueryArgs = false)
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if ($stripQueryArgs) {
            $uri = explode('?', $uri)[0];
        }

        return $uri;
    }

    /**
     * Retrieves a specific segment of the URI path.
     *
     * @param int|string $position The segment index or 'start'/'end'.
     * @return string|false The segment value or false if not found.
     * @throws \Exception If an invalid position is provided.
     */
    public function get_uri_path_name(int|string $position = 0)
    {
        $uri = $this->get_uri();

        $allowed_string_position = ['start', 'end'];
        if (!is_numeric($position) && !in_array($position, $allowed_string_position)) {
            throw new \Exception("Invalid position value. Must be numeric or one of: " . implode(', ', $allowed_string_position));
        }

        // Trim leading domain and slashes
        $uri = preg_replace('#^https?://[^/]+#', '', $uri);
        $pathSegments = explode('/', trim($uri, '/'));

        if ($position === 'start') $position = 0;
        elseif ($position === 'end') $position = count($pathSegments) - 1;
        else $position = (int) $position;

        return $pathSegments[$position] ?? false;
    }

    /**
     * Sets a request variable.
     *
     * @param string $key The request variable name.
     * @param mixed $value The request variable value.
     */
    public function set_request_var(string $key, $value)
    {
        $_REQUEST[$key] = $value;
    }

    /**
     * Retrieves a request variable.
     *
     * @param string $key The request variable name.
     * @return mixed|null The value of the request variable or null if not set.
     */
    public function get_request_var(string $key)
    {
        return $_REQUEST[$key] ?? null;
    }

    /**
     * Unsets a request variable.
     *
     * @param string $key The request variable name.
     * @throws \Exception If the variable is not set and error reporting is enabled.
     */
    public function unset_request_var(string $key)
    {
        if (!isset($_REQUEST[$key])) {
            $this->throw_error("Request variable '$key' is not set.");
        }
        unset($_REQUEST[$key]);
    }

    /**
     * Sets a session variable.
     *
     * @param string $key The session variable name.
     * @param mixed $value The value to store.
     */
    public function set_session_var(string $key, $value)
    {
        $this->start_session();
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieves a session variable.
     *
     * @param string $key The session variable name.
     * @return mixed|null The session value or null if not set.
     */
    public function get_session_var(string $key)
    {
        $this->start_session();
        return $_SESSION[$key] ?? null;
    }

    /**
     * Unsets a session variable.
     *
     * @param string $key The session variable name.
     * @throws \Exception If the variable is not set and error reporting is enabled.
     */
    public function unset_session_var(string $key)
    {
        $this->start_session();
        if (!isset($_SESSION[$key])) {
            $this->throw_error("Session variable '$key' is not set.");
        }
        unset($_SESSION[$key]);
    }

    /**
     * Starts a session if not already active.
     *
     * @return bool True if session started or already active.
     */
    public function start_session()
    {
        if (session_status() === PHP_SESSION_NONE) {
            return session_start();
        }
        return true;
    }

    /**
     * Stops the session.
     */
    public function stop_session()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Throws an error if error reporting is enabled.
     *
     * @param string $message The error message.
     * @throws \Exception If error reporting is enabled.
     */
    public function throw_error(string $message)
    {
        if ($this->reportErrors) {
            throw new \Exception($message);
        }
    }
}