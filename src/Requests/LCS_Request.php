<?php
namespace lcsTools\Requests;

/**
 * Handles request, routing, including retrieving URI segments, managing request and session variables, 
 * and handling error reporting.
 */
class LCS_Request
{
    /** @var bool Whether to report errors as exceptions */
    public $throwErrors;

    /** @var bool Whether nonce been verified already */
    private $isNonceVerified = false;

    /**
     * Constructor for initializing error reporting.
     *
     * @param bool $throwErrors Whether to throw exceptions on errors.
     */
    public function __construct(bool $throwErrors = false)
    {
        $this->throwErrors = $throwErrors;
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
            return;
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
            return;
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
     * Retrieves request data and decodes it based on the Content-Type or URL query parameters.
     *
     * This function reads the raw POST data from the request body and processes it
     * based on the Content-Type header. It can handle:
     * - JSON (application/json)
     * - Form-encoded data (application/x-www-form-urlencoded)
     * - File uploads (multipart/form-data)
     * Additionally, it retrieves GET request data from the URL query string.
     *
     * @return array The decoded request data as an associative array, including file metadata for uploads.
     */
    public function get_request_data() {
        // Initialize $requestData with an empty array
        $requestData = [];

        // Retrieve GET data (URL query parameters)
        if (!empty($_GET)) {
            $requestData = $_GET;
        }

        // Determine the Content-Type of the request
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';
        // Handle JSON (application/json)
        if (strpos($contentType, 'application/json') === 0) {
            $input = file_get_contents('php://input');

            if (!empty($input)) {
                $postData = json_decode($input, true);

                // Check if JSON decoding was successful
                if (json_last_error() === JSON_ERROR_NONE) {
                    $requestData = array_merge($requestData, $postData);
                } else {
                    return false; // Invalid JSON
                }
            }
        }
        // Handle multipart/form-data or application/x-www-form-urlencoded
        elseif (!empty($_POST)) {
            $requestData = array_merge($requestData, $_POST);

            // Add files from $_FILES to the request data
            if (!empty($_FILES)) {
                foreach ($_FILES as $key => $file) {
                    if (is_array($file['name'])) {
                        // Handle multiple files for the same input name
                        foreach ($file['name'] as $index => $name) {
                            $requestData[$key][] = [
                                'name' => $name,
                                'type' => $file['type'][$index],
                                'tmp_name' => $file['tmp_name'][$index],
                                'error' => $file['error'][$index],
                                'size' => $file['size'][$index]
                            ];
                        }
                    } else {
                        // Single file upload
                        $requestData[$key] = $file;
                    }
                }
            }
        }
        // Handle other Content-Types or raw input
        else {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                parse_str($input, $postData);
                $requestData = array_merge($requestData, $postData);
            }
        }
        
        // Ensure the securify of this request
        $isNonceRetrieval = isset($requestData['isNonceRetrieval']) && $requestData['isNonceRetrieval'] == true;
        $nonce_name = $requestData['nonce_name'] ?? 'lcs_request_nonce';

        if ($isNonceRetrieval) {
            // Generate a new nonce and return it to the client
            $nonce = $this->create_nonce($nonce_name);
            $this->send_json_success($nonce);
        }
        
        $nonce_verification_required = isset($requestData['secure']) && $requestData['secure'] == true;
        if ($nonce_verification_required && !$this->isNonceVerified) {
            // Validate the nonce
            $retrieved_nonce = $requestData['nonce'] ?? '';
            $verified = $this->verify_nonce($retrieved_nonce, $nonce_name);
            if (!$verified) {
                $this->isNonceVerified = false;
                $this->send_json_error("Unauthorized action.");
            }
            $this->isNonceVerified = true;
        }

        return $requestData;
    }

    /**
     * Generate a cryptographically secure, reusable nonce for a specific action.
     *
     * @param string $action  The action the nonce is tied to (e.g., 'delete_post').
     * @param int    $ttl     Time-to-live in seconds (default: 3600 = 1 hour).
     * @param int    $length  Length in bytes before hex encoding (default: 32).
     * @return string         The nonce string.
     */
    public function create_nonce(string $action, int $ttl = 3600, int $length = 32): string {
        $this->start_session();

        if (!isset($_SESSION['nonces'])) {
            $_SESSION['nonces'] = [];
        }

        // Check if an unexpired nonce for this action exists and reuse it
        foreach ($_SESSION['nonces'] as $nonce => $data) {
            if ($data['action'] === $action && $data['expires'] > time()) {
                return $nonce;
            }
        }

        // Generate a secure, random nonce
        $nonce = bin2hex(random_bytes($length));
        $expires = time() + $ttl;

        $_SESSION['nonces'][$nonce] = [
            'action'  => $action,
            'expires' => $expires,
        ];

        return $nonce;
    }

    /**
     * Verify a nonce for a specific action.
     *
     * @param string $nonce       The nonce to validate.
     * @param string $action      The expected action tied to the nonce.
     * @param bool   $single_use  Whether the nonce should be invalidated after verification.
     * @return bool               True if valid, false otherwise.
     */
    public function verify_nonce(string $nonce, string $action, bool $single_use = true): bool {
        $this->start_session();

        if (!isset($_SESSION['nonces'][$nonce])) {
            return false; // Nonce doesn't exist
        }

        $data = $_SESSION['nonces'][$nonce];

        // Check if the action matches
        if ($data['action'] !== $action) {
            return false;
        }

        // Check for expiry
        if ($data['expires'] < time()) {
            unset($_SESSION['nonces'][$nonce]);
            return false;
        }

        // If single-use, invalidate it after this check
        if ($single_use) {
            unset($_SESSION['nonces'][$nonce]);
        }

        return true;
    }

    /**
     * Performs a fair reset of nonces for a given action, ensuring controlled resets.
     *
     * - If the action has no recorded reset data, it initializes the tracking.
     * - Allows up to 3 resets before imposing a restriction.
     * - If the last reset was 24 hours ago, it resets the trials and timestamp.
     *
     * @param string $action The nonce action identifier.
     */
    public function fair_reset_nonce($action) {
        $this->start_session();

        if (!isset($_SESSION['NONCES_RESET_DATA'][$action])) {
            $_SESSION['NONCES_RESET_DATA'][$action] = [
                'timestamp' => time(),
                'trials' => 1
            ];
            unset($_SESSION['nonces'][$action]); // Fair reset nonce
            $this->create_nonce($action); // Generate a new nonce
            return;
        }

        $timestamp = $_SESSION['NONCES_RESET_DATA'][$action]['timestamp'] ?? 0;
        $trials = (int)($_SESSION['NONCES_RESET_DATA'][$action]['trials'] ?? 0);
        $trials++;

        if ($trials <= 3) {
            unset($_SESSION['nonces'][$action]); // Fair reset nonce
            $this->create_nonce($action); // Generate a new nonce
            $_SESSION['NONCES_RESET_DATA'][$action]['trials'] = $trials;
            return;
        }

        // Reset trials and timestamp if the last reset was 24 hours ago
        if (time() - $timestamp >= 86400) { // 86400 seconds = 24 hours
            $_SESSION['NONCES_RESET_DATA'][$action] = [
                'timestamp' => time(),
                'trials' => 1
            ];
            unset($_SESSION['nonces'][$action]); // Fair reset nonce
        }
    }

    /**
     * Checks if the current request is a valid AJAX request.
     *
     * Validates the following:
     * - The request must include the `X-Requested-With: XMLHttpRequest` header (if present).
     * - Accepts specific Content-Type headers: `multipart/form-data`, `application/json`, and `application/x-www-form-urlencoded`.
     * - Ensures the request method is either POST or GET.
     *
     * @return bool Returns true if the request is a valid AJAX request; otherwise, false.
     */
    public function is_ajax_request(): bool {
        // Check if the request method is allowed
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN', ['POST', 'GET'], true)) {
            return false;
        }

        // Check for the `X-Requested-With` header (optional for FormData)
        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
        ) {
            return false;
        }

        // Validate the Content-Type header
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $contentType = strtolower($_SERVER['CONTENT_TYPE']);
            if (
                stripos($contentType, 'multipart/form-data') !== false || // File uploads via FormData
                stripos($contentType, 'application/json') !== false ||   // JSON payloads
                stripos($contentType, 'application/x-www-form-urlencoded') !== false // Form POSTs
            ) {
                return true;
            }
        }

        // Allow fallback for POST requests without a strict Content-Type check
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return true;
        }

        return false; // Not a valid AJAX request
    }

    /**
     * Secures an AJAX request by enforcing origin validation and allowed request methods.
     *
     * This method ensures that only requests originating from the same server are processed.
     * It also sets appropriate CORS headers and validates the request method.
     *
     * @param bool $allowGlobalOrigin Whether to allow requests from any origin. Defaults to false.
     * 
     * @return void Outputs a JSON response and terminates the script in case of failure.
     */
    public function secure_ajax_request($allowGlobalOrigin = false) {
        // Get the origin of the request
        $origin = $_SERVER['HTTP_ORIGIN'] ?? "";
        $parsedOrigin = parse_url($origin, PHP_URL_HOST);
        $serverHost = parse_url("https://" . $_SERVER['HTTP_HOST'], PHP_URL_HOST);
        $clientIp = $this->get_client_ip_address();

        // Ensure the request is an AJAX request
        if (!$this->is_ajax_request()) {
            header('Content-Type: application/json');
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Unauthorized access from ' . htmlspecialchars($clientIp)]);
            exit;
        }

        // Determine if the request should be allowed based on origin validation
        $isAllowedOrigin = !empty($origin) && $parsedOrigin &&
            ($parsedOrigin === $serverHost || strpos($parsedOrigin, $serverHost) !== false);

        if ($allowGlobalOrigin || $isAllowedOrigin) {
            // Set appropriate CORS headers
            $this->set_header('allow_origin', $allowGlobalOrigin ? '*' : $origin);
            $this->set_header('allow_credentials', 'true');
            $this->set_header('allow_headers', 'Origin, X-Requested-With, Content-Type, Accept');
            $this->set_header('allow_methods', 'POST, GET');

            // Validate the request method
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'Unknown Request Method';
            if (!in_array($requestMethod, ['POST', 'GET'], true)) {
                trigger_error("AJAX error: Request method '$requestMethod' not allowed.", E_USER_ERROR);
                $this->send_json_error('Unauthorized access.', 405);
            }

        } else {
            // Reject requests with an invalid or unauthorized origin
            header('Content-Type: application/json');
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Bad or unauthorized request from ' . htmlspecialchars($clientIp)]);
            exit;
        }
    }

    /**
     * Validate an AJAX request by checking its nonce for security.
     *
     * @param string $nonce_field The field name where the nonce is sent (default: 'nonce').
     * @param string $action The action name for the nonce validation (default: 'lcs_ajax_nonce').
     */
    public function verify_ajax_referer($nonce_field = 'nonce', $action = 'lcs_ajax_nonce') {
        // Retrieve request data
        $request_data = $this->get_request_data();

        // Check if request data is retrieved successfully
        if (!$request_data) {
            $this->send_json_error('Failed to retrieve request data.');
        }

        // Validate the nonce field and its value
        if (!isset($request_data[$nonce_field]) || !$this->verify_nonce($request_data[$nonce_field], $action)) {
            $this->send_json_error('Unauthorized action.');
        }
    }

    /**
    * Sends a JSON-encoded response with a specified HTTP status code.
    * 
    * This function ensures headers are properly set for JSON content and
    * handles any JSON encoding errors. It terminates the script after sending
    * the response.
    *
    * @param mixed $data The data to send in the JSON response.
    * @param int $status_code The HTTP status code for the response (default is 200).
    * @param int $json_options Optional JSON encoding options (default is 0).
    */
    public function send_json_response($data, $status_code = 200, $json_options = 0) {
        // Ensure no headers have already been sent
        if (headers_sent()) {
            error_log("Headers already sent. Cannot send JSON response.");
            // Respond with a fallback error
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
            exit;
        }

        // Set headers for JSON response
        header('Content-Type: application/json');
        http_response_code($status_code);

        // Encode data as JSON
        $json_data = json_encode($data, $json_options);
        if ($json_data === false) {
            // Handle JSON encoding errors
            error_log("JSON encoding error: " . json_last_error_msg());
            // Respond with a JSON encoding error message
            $json_data = json_encode(['success' => false, 'error' => 'JSON encoding error']);
            http_response_code(500); // Internal Server Error for JSON encoding issues
        }

        // Output JSON response and terminate script
        echo $json_data;
        exit;
    }

    /**
     * Sends a JSON success response.
     * 
     * Automatically sets the `success` key to true and includes any additional data.
     *
     * @param mixed $data Optional data to send with the success response (default is null).
     * @param int $status_code The HTTP status code for the response (default is 200).
     * @param int $json_options Optional JSON encoding options (default is 0).
     */
    public function send_json_success($data = null, $status_code = 200, $json_options = 0) {
        $response = [
            'success' => true,
            'data' => $data
        ];

        $this->send_json_response($response, $status_code, $json_options);
    }

    /**
     * Sends a JSON error response.
     * 
     * Automatically sets the `success` key to false and includes the provided error message.
     * 
     * @param string $error_message A message describing the error.
     * @param int $status_code The HTTP status code for the response (default is 400).
     * @param int $json_options Optional JSON encoding options (default is 0).
     */
    public function send_json_error($error_message = 'An error occurred', $status_code = 400, $json_options = 0) {
        $response = [
            'success' => false,
            'data' => $error_message
        ];

        $this->send_json_response($response, $status_code, $json_options);
    }

    /**
     * Retrieves the IP address of the client.
     *
     * This function attempts to get the client's IP address from various server variables,
     * accounting for situations where the client is behind a proxy or load balancer.
     *
     * @return string The client's IP address.
     */
    public function get_client_ip_address(): string {
        $ipaddress = '';

        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED']) && !empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && !empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR']) && !empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED']) && !empty($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        // If multiple IPs are returned, take the first one
        if (strpos($ipaddress, ',') !== false) {
            $ipaddress = explode(',', $ipaddress)[0];
        }

        // Validate the IP address format
        if (!filter_var($ipaddress, FILTER_VALIDATE_IP)) {
            $ipaddress = 'INVALID IP';
        }

        return $ipaddress;
    }

    /**
     * Retrieves and parses the User-Agent string of the client.
     *
     * This function attempts to get the client's User-Agent string and provides
     * basic information about the client, such as the browser, platform, and device type.
     *
     * @return array An associative array containing the User-Agent string, browser, platform, and device type.
     */
    public function get_user_agent(): array {
        // Retrieve the User-Agent string from the server
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? trim($_SERVER['HTTP_USER_AGENT']) : 'UNKNOWN';

        // Browser detection
        $browser = 'Unknown Browser';
        if ($this->contains('MSIE', $user_agent) || $this->contains('Trident/', $user_agent)) {
            $browser = 'Internet Explorer';
        } elseif ($this->contains('Edge', $user_agent)) {
            $browser = 'Microsoft Edge';
        } elseif ($this->contains('Firefox', $user_agent)) {
            $browser = 'Mozilla Firefox';
        } elseif ($this->contains('Chrome', $user_agent) && !$this->contains('Edge', $user_agent)) {
            $browser = 'Google Chrome';
        } elseif ($this->contains('Safari', $user_agent) && !$this->contains('Chrome', $user_agent)) {
            $browser = 'Apple Safari';
        } elseif ($this->contains('Opera', $user_agent) || $this->contains('OPR', $user_agent)) {
            $browser = 'Opera';
        }

        // Platform detection
        $platform = 'Unknown Platform';
        if ($this->contains('Windows', $user_agent)) {
            $platform = 'Windows';
        } elseif ($this->contains('Macintosh', $user_agent) || $this->contains('Mac OS X', $user_agent)) {
            $platform = 'Mac OS';
        } elseif ($this->contains('Linux', $user_agent)) {
            $platform = 'Linux';
        } elseif ($this->contains('Android', $user_agent)) {
            $platform = 'Android';
        } elseif ($this->contains('iPhone', $user_agent) || $this->contains('iPad', $user_agent)) {
            $platform = 'iOS';
        }

        // Device type detection (basic)
        $device_type = 'Desktop';
        if ($this->contains('Mobi', $user_agent)) {
            $device_type = 'Mobile';
        } elseif ($this->contains('Tablet', $user_agent) || $this->contains('iPad', $user_agent)) {
            $device_type = 'Tablet';
        }

        return [
            'user_agent' => $user_agent,
            'browser' => $browser,
            'platform' => $platform,
            'device_type' => $device_type
        ];
    }

    /**
     * Sets a header for the response.
     *
     * This function allows setting various types of headers for the response, including CORS headers,
     * content headers, cache-control headers, authentication & security headers, and more.
     *
     * @param string $header The header type to set.
     * @param mixed $value The value to set for the header.
     * @param bool $replace Whether to replace an existing header of the same type (default is true).
     * @param int $http_response_code The HTTP response code to set the header for (default is 200).
     */
    public function set_header(string $header, mixed $value, bool $replace = true, int $http_response_code = 200) {
        $allowedHeaders = [
            // CORS Headers
            'allow_origin'         => 'Access-Control-Allow-Origin',
            'allow_credentials'    => 'Access-Control-Allow-Credentials',
            'allow_headers'        => 'Access-Control-Allow-Headers',
            'allow_methods'        => 'Access-Control-Allow-Methods',
            'ac_max_age'           => 'Access-Control-Max-Age',
            'ac_expose_headers'    => 'Access-Control-Expose-Headers',
    
            // Content Headers
            'content_type'         => 'Content-Type',
            'content_length'       => 'Content-Length',
            'content_disposition'  => 'Content-Disposition',
            'content_encoding'     => 'Content-Encoding',
            'content_language'     => 'Content-Language',
            'content_location'     => 'Content-Location',
    
            // Cache-Control Headers
            'cache_control'        => 'Cache-Control',
            'expires'              => 'Expires',
            'pragma'               => 'Pragma',
            'last_modified'        => 'Last-Modified',
            'etag'                 => 'ETag',
    
            // Authentication & Security Headers
            'authorization'        => 'Authorization',
            'www_authenticate'     => 'WWW-Authenticate',
            'strict_transport'     => 'Strict-Transport-Security',
            'content_security'     => 'Content-Security-Policy',
            'x_frame_options'      => 'X-Frame-Options',
            'x_xss_protection'     => 'X-XSS-Protection',
            'x_content_type'       => 'X-Content-Type-Options',
            'referrer_policy'      => 'Referrer-Policy',
    
            // Redirect & Location Headers
            'location'             => 'Location',
            'refresh'              => 'Refresh',
    
            // Server & Network Headers
            'server'               => 'Server',
            'connection'           => 'Connection',
            'transfer_encoding'    => 'Transfer-Encoding',
            'vary'                 => 'Vary',
        ];
    
        if (!array_key_exists($header, $allowedHeaders)) {
            $this->throw_error("Invalid header type: $header");
        }
    
        header($allowedHeaders[$header] . ': ' . $value, $replace, $http_response_code);
    }
    
    /**
     * Retrieves the full URI of the current request.
     *
     * @param bool $stripQueryArgs Whether to remove query parameters from the URI.
     * @param bool $isolateAjaxEffects Whether to prioritize HTTP referer during AJAX requests to reflect the real source page. Default is true.
     * @return string The request URI.
     * 
     * @example
     * // Example usage:
     * $this->get_uri(); // "/products/item?id=123"
     * $this->get_uri(true); // "/products/item"
     */
    public function get_uri(bool $stripQueryArgs = false, bool $isolateAjaxEffects = true)
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if ($isolateAjaxEffects && $this->is_ajax_request() && isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            $parsed = parse_url($referer);
            $uri = $parsed['path'] ?? $uri;
            if (!$stripQueryArgs && isset($parsed['query'])) {
                $uri .= '?' . $parsed['query'];
            }
        }

        if ($stripQueryArgs) {
            $uri = explode('?', $uri)[0];
        }

        return $uri;
    }

    /**
     * Retrieves a specific segment of the URI path.
     *
     * Note: Query parameters are included unless $stripQueryArgs is set to true.
     *
     * @param int|string $position The segment index (0-based) or special keywords 'start' or 'end'.
     * @param string|null $uri Optional. The URI to extract the segment from. If null, uses the current request URI.
     * @param bool $stripQueryArgs Optional. Whether to strip query parameters before extracting segments.
     * @return string|false The segment value, or false if not found.
     *
     * @throws \Exception If an invalid position value is provided.
     *
     * @example
     * // Example URL: "/products/item/view?id=123"
     * $this->get_uri_path_name(1); // "item"
     * $this->get_uri_path_name('start'); // "products"
     * $this->get_uri_path_name('end'); // "view?id=123"
     * $this->get_uri_path_name(2, null, true); // "view"
     * $this->get_uri_path_name(3, "/products/item/view?id=123", true); // false
     */
    public function get_uri_path_name(int|string $position = 0, ?string $uri = null, bool $stripQueryArgs = false)
    {
        $uri = empty($uri) ? $this->get_uri($stripQueryArgs) : $uri;

        $allowed_string_position = ['start', 'end'];
        if (!is_numeric($position) && !in_array($position, $allowed_string_position, true)) {
            throw new \Exception(
                "Invalid position value. Must be numeric or one of: " . implode(', ', $allowed_string_position)
            );
        }

        $pathSegments = explode('/', trim($uri, '/'));

        if ($position === 'start') {
            $position = 0;
        } elseif ($position === 'end') {
            $position = count($pathSegments) - 1;
        } else {
            $position = (int) $position;
        }

        return $pathSegments[$position] ?? false;
    }

    /**
     * Retrieves the current URL of the request.
     *
     * This function constructs the full URL based on server variables,
     * including protocol, host, and request URI. It can optionally exclude
     * the protocol and account for AJAX behavior.
     *
     * When handling AJAX requests, the request URI typically points to the
     * AJAX endpoint itself rather than the original page. If $isolateAjaxEffects
     * is true, this function will prioritize the HTTP referer to reflect the
     * actual page that initiated the AJAX call.
     *
     * @param bool $includeProtocol Whether to include the protocol (http/https) in the returned URL.
     * @param bool $isolateAjaxEffects Whether to prioritize HTTP referer during AJAX requests to reflect the real source page. Default is true.
     * @return string The current URL.
     */
    public function get_url(bool $includeProtocol = false, bool $isolateAjaxEffects = true): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $url = $protocol . $host . $uri;

        if ($isolateAjaxEffects && $this->is_ajax_request() && isset($_SERVER['HTTP_REFERER'])) {
            $url = $_SERVER['HTTP_REFERER'];
        }

        if (!$includeProtocol) {
            $url = preg_replace('#^https?://#i', '', $url); // case-insensitive just in case
        }

        return trim($url);
    }

    /**
     * Generates a home URL with an optional additional path.
     *
     * @param string|null $additional_path An optional path to append to the home URL. Defaults to null.
     * @return string The complete home URL, including the additional path if provided.
     */
    public function get_home_url($additional_path = null) {
        // Set the base home URL
        $base_url = '/';

        // Normalize the additional path
        if ($additional_path !== null) {
            // Remove leading slashes and trailing slashes
            $additional_path = trim($additional_path, '/');
        } else {
            return $base_url;
        }

        // Return the complete home URL
        return $base_url . $additional_path;
    }

    /**
     * Retrieves the HTTP referer URL, or falls back to the current request URL if not available.
     *
     * This method returns the value of the HTTP_REFERER server variable if set and not empty.
     * If the referer is not available, it falls back to the current request URL.
     *
     * @param bool $includeProtocol Whether to include the protocol (http/https) in the returned URL.
     * @param bool $isolateAjaxEffects Whether to prioritize HTTP referer during AJAX requests to reflect the real source page.
     * @return string The referer URL or the current request URL as a fallback.
     */
    public function get_referer(bool $includeProtocol = false, bool $isolateAjaxEffects = true)
    {
        // Check if the HTTP_REFERER server variable is set and not empty
        if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
            return $_SERVER['HTTP_REFERER'];
        }

        // Fallback to the current URL if the referer is not set or is empty
        return $this->get_url($includeProtocol, $isolateAjaxEffects);
    }

    /**
     * Extracts the query string from a given URL or $this->get_url(true).
     *
     * This function takes a URL and returns the query string portion,
     * preserving any array notation used in the parameters.
     *
     * @param string|null $url The URL from which to extract the query string. Defaults to the current URL.
     * @return string|null The query string without the base URL, or null if no query string exists.
     */
    public function get_url_query_arg(?string $url = null): ?string
    {
        if ($url === null) {
            $url = $this->get_url(true);
        }

        $parsedUrl = parse_url($url);

        return isset($parsedUrl['query']) && !empty($parsedUrl['query']) ? $parsedUrl['query'] : null;
    }

    /**
     * Retrieves the site's domain name.
     *
     * This function returns the current domain, with an option to include
     * the protocol (http or https). It excludes any URI paths or query strings.
     *
     * @param bool $includeProtocol Whether to include the protocol (http/https) in the returned domain. Default is false.
     * @return string The domain name, optionally prefixed with the protocol.
     */
    public function get_domain(bool $includeProtocol = false): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $domain = $includeProtocol ? ($protocol . $host) : $host;

        return trim($domain);
    }

    /**
     * Retrieves the site's host name.
     *
     * This function uses the get_domain method to return the host name,
     * optionally including the protocol (http/https).
     *
     * @param bool $includeProtocol Whether to include the protocol (http/https) in the returned host. Default is false.
     * @return string The host name, optionally prefixed with the protocol.
     */
    public function get_host(bool $includeProtocol = false): string
    {
        return $this->get_domain($includeProtocol);
    }

        /**
     * Retrieves the current HTTP request method.
     *
     * This method returns the HTTP request method (e.g., 'GET', 'POST', etc.) as provided by the server.
     * If the request method is not set, it returns NULL.
     *
     * @return string|null The HTTP request method, or NULL if unavailable.
     */
    public function get_request_method(): string|null 
    {
        return $_SERVER['REQUEST_METHOD'] ?? NULL;
    }

    /**
     * Determines the type of the current HTTP request.
     *
     * This method checks if the request is an AJAX request and returns 'AJAX' if true.
     * Otherwise, it returns the actual HTTP request method (e.g., 'GET', 'POST').
     *
     * @return string The type of request: 'AJAX', or the HTTP request method.
     */
    public function get_request_type() 
    {
        // Check if the request is an AJAX request
        if ($this->is_ajax_request()) {
            return 'AJAX';
        }
        return $this->get_request_method();
    }

    /**
     * Checks if a given URL is accessible by sending an HTTP HEAD request.
     * 
     * @param string $url - The URL to be checked for accessibility.
     * @return bool - Returns true if the URL is accessible, otherwise false.
     */
    public function is_url_accessible($url) {
        // Automatically return false for URLs containing 'example.com'
        if (str_contains($url, 'example.com')) {
            return false;
        }

        // Retrieve HTTP headers for the given URL
        $headers = @get_headers($url);

        // If headers are not retrieved, return false
        if ($headers === false) {
            return false;
        }

        // Get the status code from the first header (e.g., "HTTP/1.1 200 OK")
        $status_code = substr($headers[0], 9, 3);

        // Check if the status code is 200 (OK), 301 (Moved Permanently), or 302 (Found)
        if (in_array($status_code, ['200', '301', '302'])) {
            return true;
        }

        return false;
    }

    /**
     * Validate that a given URL belongs to the current server.
     * 
     * @param string $url The URL to validate.
     * @return bool True if the URL belongs to the server, false otherwise.
     */
    public function is_server_url($url) {
        // Get the host of the current server
        $server_host = $_SERVER['HTTP_HOST'];
        
        // Parse the given URL to extract the host
        $parsed_url = parse_url($url, PHP_URL_HOST);

        if (empty($parsed_url)) {
            return preg_match('/^localhost\//', $url);
        }

        // If the host of the URL matches the server's host, it's a valid server URL
        return ($parsed_url && $parsed_url === $server_host);
    }

    /**
     * Checks if a given string contains a URL protocol (http, https, ftp, etc.).
     *
     * @param string $string The string to check.
     * @return bool True if a protocol is present, false otherwise.
     */
    public function contains_url_protocol(string $string): bool
    {
        $parsedURL = parse_url($string);
        return isset($parsedURL['scheme']) && !empty($parsedURL['scheme']);
    }

    /**
     * Checks if a given string contains a valid host (domain or IP address).
     *
     * This function attempts to extract the host from a URL or domain string and validates
     * whether it is a valid domain name or IP address.
     *
     * @param string $string The string to check.
     * @return bool True if a valid host is found, false otherwise.
     */
    public function contains_url_host(string $string): bool
    {
        // Prepend protocol if missing to help parse_url
        if (!preg_match('#^https?://#i', $string)) {
            $string = 'http://' . $string;
        }
        $host = parse_url($string, PHP_URL_HOST);

        // If no host is found, check if it's a localhost or domain-like pattern
        if (!$host) {
            // If the URL starts with "localhost" or a domain-like pattern, consider it having a host
            return preg_match('/^(localhost|[\w-]+\.[\w-]+)/', $string);
        }

        // Check if host is a valid domain or IP address
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Checks if a given string contains a valid top-level domain (TLD).
     *
     * First attempts to match against a list of known TLDs.
     * If not found, falls back to checking if the TLD is exactly 2 characters (ccTLD).
     *
     * @param string $string The string to check (e.g., a domain or URL).
     * @return bool True if a valid TLD is found, false otherwise.
     */
    public function contains_url_tld(string $string): bool
    {
        // List of common TLDs (expanded but not exhaustive)
        $tlds = [
            // Original TLDs
            'com', 'net', 'org', 'edu', 'gov', 'mil', 'int', 'io', 'co', 'us', 'uk', 'de', 'jp', 'fr', 'au', 'ca',
            'cn', 'ru', 'ch', 'it', 'nl', 'se', 'no', 'es', 'biz', 'info', 'me', 'tv', 'xyz', 'site', 'online', 'ng',
            // Additional generic TLDs
            'app', 'blog', 'club', 'dev', 'shop', 'store', 'tech', 'work', 'art', 'design', 'guru', 'live', 'news',
            'pro', 'space', 'world', 'email', 'solutions', 'cloud', 'digital', 'media', 'travel', 'fun', 'team',
            // Additional country-code TLDs
            'br', 'in', 'mx', 'za', 'sg', 'kr', 'nz', 'ie', 'dk', 'fi', 'be', 'at', 'pl', 'tr', 'ar', 'cl', 'co',
            'id', 'my', 'ph', 'sa', 'ae', 'th', 'vn', 'eg', 'ke', 'ma', 'pt', 'gr', 'hu', 'cz', 'ro',
            // Sponsored and niche TLDs
            'mobi', 'tel', 'name', 'asia', 'jobs', 'museum', 'aero', 'coop', 'cat', 'post', 'xxx',
            // Brand and community TLDs
            'google', 'apple', 'aws', 'microsoft', 'icu', 'top', 'win', 'vip', 'link', 'page'
        ];

        // Extract the host from the string if it's a URL
        if (!preg_match('#^https?://#i', $string)) {
            $string = 'http://' . $string;
        }
        $host = parse_url($string)['host'] ?? null;
        if (empty($host)) {
            return false;
        }

        // Extract the last domain segment (TLD) after the last dot
        if (preg_match('/\.([a-z0-9\-]{2,})$/i', $host, $matches)) {
            $tld = strtolower($matches[1]);

            // Check if it exists in the known TLDs list
            if (in_array($tld, $tlds, true)) {
                return true;
            }

            // Fallback: check if it's a valid ccTLD (exactly 2 letters)
            if (preg_match('/^[a-z]{2}$/i', $tld)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper method to check if a string contains a specific substring.
     *
     * @param string $needle
     * @param string $haystack
     * @return bool
     */
    protected function contains(string $needle, string $haystack): bool
    {
        return strpos($haystack, $needle) !== false;
    }

    /**
     * Throws an error if error reporting is enabled.
     *
     * @param string $message The error message.
     * @throws \Exception If error reporting is enabled.
     */
    public function throw_error(string $message)
    {
        if ($this->throwErrors) {
            throw new \Exception($message);
        }
    }
}