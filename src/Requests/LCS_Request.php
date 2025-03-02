<?php
namespace LCSNG_EXT\Requests;

use LCSNG_EXT\Creds\LCS_Creds;

/**
 * Handles request, routing, including retrieving URI segments, managing request and session variables, 
 * and handling error reporting.
 */
class LCS_Request
{
    /** @var bool Whether to report errors as exceptions */
    public $reportErrors;

    /** @var string Holding the nonce secret key */
    private $nonce_secret_key;

    /** @var bool Whether nonce been verified already */
    private $isNonceVerified = false;

    /**
     * Constructor for initializing error reporting.
     *
     * @param bool $reportErrors Whether to throw exceptions on errors.
     */
    public function __construct(bool $reportErrors = false)
    {
        $this->reportErrors = $reportErrors;

        $creds = new LCS_Creds();
        $this->nonce_secret_key = $creds->get_nonce_secret_key();
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
            if (!$this->verify_nonce($retrieved_nonce, $nonce_name)) {
                $this->isNonceVerified = false;
                $this->send_json_error("Unauthorized action.");
            }

            $this->isNonceVerified = true;
        }

        return $requestData;
    }

    /**
    * Generate a secure nonce for a specific action.
    *
    * @param string $action The action/name the nonce is tied to.
    * @param int $expiration The expiration time in seconds, default is 3600 seconds (1 hour).
    * @param int $length Length of the nonce, default is 32 bytes.
    * @return string The generated nonce.
    */
    public function create_nonce($action, $expiration = 3600, $length = 32) {
        // Ensure the session is started
        $this->start_session();

        // Check if a nonce for this action already exists and is valid
        if (isset($_SESSION['nonces'][$action])) {
            $stored_nonce = $_SESSION['nonces'][$action]['nonce'];
            $timestamp = $_SESSION['nonces'][$action]['timestamp'];
            
            // Check if the nonce is still valid
            if ((time() - $timestamp) < $expiration) {
                return bin2hex($stored_nonce); // Return existing nonce if valid
            }
        }
        
        // Generate a new random binary string
        $nonce = random_bytes($length);
        
        // Hash the nonce with the action and a secret key
        $hashed_nonce = hash_hmac('sha256', $nonce . $action, $this->nonce_secret_key);
        
        // Store the nonce and timestamp in the session
        $_SESSION['nonces'][$action] = [
            'nonce' => $nonce, // Store the original nonce
            'hashed_nonce' => $hashed_nonce, // Store the hashed nonce for validation
            'timestamp' => time()
        ];

        return bin2hex($nonce);
    }

    /**
     * Verify a nonce for a specific action.
     *
     * @param string $nonce The nonce to verify.
     * @param string $action The action the nonce is tied to.
     * @param int $expiration The expiration time in seconds, default is 3600 seconds (1 hour).
     * @return bool True if the nonce is valid, false otherwise.
     */
    public function verify_nonce($nonce, $action, $expiration = 3600): bool {
        // Ensure the session is started
        $this->start_session();

        // Check if the nonce for the action is set in the session
        if (isset($_SESSION['nonces'][$action])) {
            $stored_hashed_nonce = $_SESSION['nonces'][$action]['hashed_nonce'];
            $timestamp = $_SESSION['nonces'][$action]['timestamp'];

            // Hash the received nonce with the action and the same secret key
            $hashed_nonce = hash_hmac('sha256', hex2bin($nonce) . $action, $this->nonce_secret_key);

            // Validate the nonce and check for expiration
            if (hash_equals($stored_hashed_nonce, $hashed_nonce) && (time() - $timestamp) < $expiration) {
                // Invalidate the nonce after use
                unset($_SESSION['nonces'][$action]);
                return true;
            }
        }

        // Nonce is invalid or expired
        return false;
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
    private function fair_reset_nonces($action) {
        $this->start_session();

        if (!isset($_SESSION['NONCES_RESET_DATA'][$action])) {
            $_SESSION['NONCES_RESET_DATA'][$action] = [
                'timestamp' => time(),
                'trials' => 1
            ];
            unset($_SESSION['nonces'][$action]); // Fair reset nonce
            return;
        }

        $timestamp = $_SESSION['NONCES_RESET_DATA'][$action]['timestamp'] ?? 0;
        $trials = (int)($_SESSION['NONCES_RESET_DATA'][$action]['trials'] ?? 0);
        $trials++;

        if ($trials <= 3) {
            unset($_SESSION['nonces'][$action]); // Fair reset nonce
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

        // Function to check for a specific value within the User-Agent string
        function contains($needle, $haystack) {
            return strpos($haystack, $needle) !== false;
        }

        // Browser detection
        $browser = 'Unknown Browser';
        if (contains('MSIE', $user_agent) || contains('Trident/', $user_agent)) {
            $browser = 'Internet Explorer';
        } elseif (contains('Edge', $user_agent)) {
            $browser = 'Microsoft Edge';
        } elseif (contains('Firefox', $user_agent)) {
            $browser = 'Mozilla Firefox';
        } elseif (contains('Chrome', $user_agent) && !contains('Edge', $user_agent)) {
            $browser = 'Google Chrome';
        } elseif (contains('Safari', $user_agent) && !contains('Chrome', $user_agent)) {
            $browser = 'Apple Safari';
        } elseif (contains('Opera', $user_agent) || contains('OPR', $user_agent)) {
            $browser = 'Opera';
        }

        // Platform detection
        $platform = 'Unknown Platform';
        if (contains('Windows', $user_agent)) {
            $platform = 'Windows';
        } elseif (contains('Macintosh', $user_agent) || contains('Mac OS X', $user_agent)) {
            $platform = 'Mac OS';
        } elseif (contains('Linux', $user_agent)) {
            $platform = 'Linux';
        } elseif (contains('Android', $user_agent)) {
            $platform = 'Android';
        } elseif (contains('iPhone', $user_agent) || contains('iPad', $user_agent)) {
            $platform = 'iOS';
        }

        // Device type detection (basic)
        $device_type = 'Desktop';
        if (contains('Mobi', $user_agent)) {
            $device_type = 'Mobile';
        } elseif (contains('Tablet', $user_agent) || contains('iPad', $user_agent)) {
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