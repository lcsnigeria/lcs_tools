<?php
namespace LCSNG_EXT\Requests;

use LCSNG_EXT\Requests\LCS_Request;

/**
 * Manages routing and template rendering.
 */
class LCS_RoutingController extends LCS_Request
{
    
    const DEFAULT_TEMPLATE_PATHS = [
        'error_404' => __DIR__ . '/DefaultTemplates/404.php'
    ];

    /** @var string Path to the homepage template */
    public $home;

    /** @var string Path to the search results template */
    public $search;

    /** @var string Path to the single post template */
    public $post;

    /** @var string Path to the profile template */
    public $profile;

    /** @var string Path to the signup template */
    public $signup;

    /** @var string Path to the login template */
    public $login;

    /** @var string Path to the 404 template */
    public $error_404 = self::DEFAULT_TEMPLATE_PATHS['error_404'];

    /** @var array Additional templates (e.g. ['cart' => 'path/to/cart/template']) */
    public $add_template = [];

    /** 
     * @var string|null Path to the directory containing all template files. 
     * If set, the system will attempt to automatically detect template files 
     * based on predefined template keys (e.g., 'home', 'search', etc.). 
     */
    public $template_dir = null;

    /** @var string|null The object ID for the request */
    public $object_id = null;

    /**
     * Initializes the routing controller and sets the object ID if provided.
     */
    public function __construct()
    {
        if ($this->object_id) {
            $this->set_request_var('object_id', $this->object_id);
        }
    }

    /**
     * Validates the existence of all required template files.
     *
     * This method checks whether all predefined and additional template files exist.
     * If a template path is not explicitly set, it attempts to locate the corresponding
     * file within the specified `$template_dir`. If a required template is missing,
     * an exception is thrown.
     *
     * @return array An array of all template paths.
     * @throws \Exception If any required template file does not exist.
     */
    private function validate_template(): array
    {
        /**
         * Default template keys that must be validated.
         * These are the core templates required for the application.
         *
         * @var array $default_template_keys
         */
        $default_template_keys = ['home', 'search', 'post', 'profile', 'signup', 'login', 'error_404'];

        /**
         * Merge predefined templates with any additional templates provided by the user.
         *
         * @var array $template_paths
         */
        $template_paths = array_merge([
            'home' => $this->home,
            'search' => $this->search,
            'post' => $this->post,
            'profile' => $this->profile,
            'signup' => $this->signup,
            'login' => $this->login,
            'error_404' => $this->error_404,
        ], $this->add_template);

        // Attempt to automatically detect missing template paths if $template_dir is set
        if ($this->template_dir) {
            $temp_dir = $this->template_dir;
            $temp_dir_files = array_values(array_filter(scandir($temp_dir), function ($file) use ($temp_dir) {
                return is_file("$temp_dir/$file") && preg_match('/\.(php|html|PHP|HTML)$/i', $file);
            }));

            if (!empty($temp_dir_files)) {
                foreach ($template_paths as $key => $value) {
                    if (!$value || empty($value) || in_array($value, array_values(self::DEFAULT_TEMPLATE_PATHS))) {
                        foreach ( $temp_dir_files as $file ) {
                            $file_key = pathinfo($file, PATHINFO_FILENAME);
                            $file_path = "$temp_dir/$file";
                            $template_paths[$file_key] = $file_path;
                            if (in_array($key, $default_template_keys)) {
                                $this->$key = $file_path;
                            } else {
                                $this->add_template[$key] = $file_path;
                            }
                        }
                    }
                }
            }
        }

        // Validate that all required templates exist
        foreach ($template_paths as $key => $value) {
            if ($value && !file_exists($value)) {
                $this->throw_error("The template file '$value' for '$key' does not exist.");
            }
        }

        return $template_paths;
    }

    /**
     * Retrieves the template path for a given key.
     *
     * This method validates the templates and then attempts to find the path
     * for the specified template key. If the key matches one of the predefined
     * or additional templates, it returns the corresponding path. If no match
     * is found, it defaults to the 404 error template.
     *
     * @param string $template_key The template identifier.
     * @return string|null The template path or null if not found.
     */
    public function get_template_path(string $template_key)
    {
        $template_paths = $this->validate_template();

        $matched_key = 'error_404';
        foreach ($template_paths as $key => $path) {
            if ($template_key === $key) {
                $matched_key = $key;
                break;
            }
        }
        return $template_paths[$matched_key] ?? null;
    }


    /**
     * Renders a template based on the provided template key.
     *
     * This method retrieves the template path for the given key and renders the template.
     * If the template key is not defined, it throws an error.
     *
     * @param string $template_key The template identifier.
     * @param bool $render404onError Whether to render the 404 error template on error.
     * @throws \Exception If the template key is not defined.
     */
    public function render_template_by_key(string $template_key, bool $render404onError = false)
    {
        $template_path = $this->get_template_path($template_key);

        if (!$template_path) {
            if ($render404onError) {
                $template_path = $this->error_404;
            } else {
                $this->throw_error("Template path for key '$template_key' is not defined.");
            }
        }

        $this->render_template($template_path);
    }

    /**
     * Renders a template if the given path matches a property.
     *
     * This method checks if a template path exists for the provided path.
     * If the template path is found, it renders the template. Otherwise,
     * it renders the 404 error template if $render404onError is true, or
     * throws an error indicating that the template path is not defined.
     *
     * @param string $path The path to check for a matching template property.
     * @param bool $render404onError Whether to render the 404 error template on error.
     * @throws \Exception If the template path is not defined and $render404onError is false.
     */
    public function render_template_if_path_match_property(string $path, bool $render404onError = false)
    {
        $path = $path === '/' ? 'home' : $path;
        $path = preg_replace('/^\/|\/$/', '', $path);
        $template_path = $this->get_template_path($path);
        if (!$template_path) {
            if ($render404onError) {
                $template_path = $this->error_404;
            } else {
                $this->throw_error("Template path for '$path' is not defined.");
            }
        }
        $this->render_template($template_path);
    }

    /**
     * Renders the specified template file.
     *
     * @param string $template_path The template file path.
     * @throws \Exception If the file does not exist.
     */
    public function render_template(string $template_path)
    {
        if (!file_exists($template_path)) {
            $this->throw_error("The template file '$template_path' does not exist.");
            return false;
        }

        ob_start();
        
        if (pathinfo($template_path, PATHINFO_EXTENSION) === 'html') {
            readfile($template_path);
        } else {
            require_once $template_path;
        }

        echo ob_get_clean();
        exit();
    }

}