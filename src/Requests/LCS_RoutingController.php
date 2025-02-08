<?php
namespace LCSNG_EXT\Requests;

use LCSNG_EXT\Requests\LCS_Request;

/**
 * Manages routing and template rendering.
 */
class LCS_RoutingController extends LCS_Request
{
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
    public $error_404;

    /** @var array Additional templates (e.g. ['cart' => 'path/to/cart/template']) */
    public $add_template = [];

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
     * Validates that all required template files exist.
     *
     * @throws \Exception If any template file is missing.
     */
    private function validate_template()
    {
        $template_properties = array_filter([
            $this->home, $this->search, $this->post, $this->profile,
            $this->signup, $this->login, $this->error_404
        ]);

        foreach ($this->add_template as $value) {
            if ($value) $template_properties[] = $value;
        }

        foreach ($template_properties as $value) {
            if (!file_exists($value)) {
                $this->throw_error("The template file '$value' does not exist.");
            }
        }
    }

    /**
     * Retrieves the template path for a given key.
     *
     * @param string $template_key The template identifier.
     * @return string|null The template path or null if not found.
     */
    public function get_template_path(string $template_key)
    {
        $this->validate_template();

        $template_paths = array_merge([
            'home' => $this->home,
            'search' => $this->search,
            'post' => $this->post,
            'profile' => $this->profile,
            'signup' => $this->signup,
            'login' => $this->login,
            'error_404' => $this->error_404,
        ], $this->add_template);

        return $template_paths[$template_key] ?? null;
    }

    /**
     * Renders a template if the specified routing rule matches; otherwise, renders the 404 page if enabled.
     *
     * @param string $rule1 First rule to check.
     * @param string $rule2 Second rule to check.
     * @param string $template_key The template key to render.
     * @param bool $render404OnFalse Whether to render the 404 page if the rule does not match. Default is true.
     * @throws \Exception If the template is not defined.
     */
    public function render_template_if_rule_matched(string $rule1, string $rule2, string $template_key, bool $render404OnFalse = true)
    {
        if ($rule1 === $rule2) {
            $template_path = $this->get_template_path($template_key);

            if (!$template_path) {
                $this->throw_error("Template '$template_key' is not defined.");
            }

            $this->render_template($template_path);
            return; // Stop further execution
        }

        if ($render404OnFalse) {
            // Set HTTP 404 response code
            http_response_code(404);

            // Render error template if available, otherwise show default message
            if ($this->error_404 && file_exists($this->error_404)) {
                $this->render_template($this->error_404);
            } else {
                echo "<h1>404 Not Found</h1>";
                exit();
            }
        }
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