<?php
namespace lcsTools\Tools;

/**
 * Class TemplateTools
 *
 * Provides methods for generating and outputting HTML meta tags, Open Graph (OG) tags,
 * Twitter Card metadata, JSON-LD structured data, and other metadata elements for a web page.
 *
 * @package lcsTools\Tools
 */
class LCS_TemplateTools
{
    /**
     * Generates and outputs HTML meta tags, Open Graph (OG) tags, Twitter Card metadata,
     * JSON-LD structured data, and other metadata elements for a web page.
     *
     * @param array $data {
     *     Array of metadata information to define for the web page.
     *
     *     @type string 'title'         The title of the page (required).
     *     @type string 'description'  The meta description of the page (required).
     *     @type string 'category'     The type of the page (e.g., 'website', 'article', 'blog', 'product'). Defaults to 'website'.
     *     @type string 'url'          The canonical URL of the page (optional).
     *     @type string 'image'        The URL of the featured image for OG and Twitter metadata (optional).
     *     @type string 'favicon'      The URL of the favicon (optional).
     *     @type bool   'set_canonical' Whether to generate a canonical link tag. Defaults to true.
     *     @type bool   'set_og'        Whether to generate Open Graph metadata. Defaults to true.
     *     @type bool   'set_twitter_og' Whether to generate Twitter Card metadata. Defaults to true.
     *     @type bool   'set_json_ld'   Whether to generate JSON-LD structured data. Defaults to true.
     *     @type array  'json_ld'       Additional JSON-LD data to merge with default values (optional).
     *     @type array  'og'            Additional Open Graph metadata to merge with default values (optional).
     *     @type array  'twitter_og'    Additional Twitter Card metadata to merge with default values (optional).
     * }
     *
     * @return void Outputs the generated HTML meta tags directly.
     *
     * @throws InvalidArgumentException If required fields ('title' or 'description') are missing.
     *
     * Usage:
     * $data = [
     *     'title' => 'Example Page Title',
     *     'description' => 'An example of a page description.',
     *     'category' => 'article',
     *     'url' => 'https://example.com/page',
     *     'image' => 'https://example.com/image.jpg',
     *     'favicon' => 'https://example.com/favicon.ico',
     *     'set_canonical' => true,
     *     'set_og' => true,
     *     'set_twitter_og' => true,
     *     'set_json_ld' => true,
     *     'json_ld' => [
     *         'author' => [
     *             '@type' => 'Person',
     *             'name' => 'John Doe'
     *         ]
     *     ],
     *     'og' => [
     *         'og:locale' => 'en_US'
     *     ],
     *     'twitter_og' => [
     *         'twitter:site' => '@example'
     *     ]
     * ];
     * TemplateTools::defineMetaTags($data);
     */
    public static function defineMetaTags(array $data) {
        // Validate input data
        if (empty($data)) {
            echo 'Document data cannot be empty.';
            exit;
        }

        if (empty($data['title'])) {
            echo 'Document title cannot be empty.';
            exit;
        }

        if (empty($data['description'])) {
            echo 'Document description cannot be empty.';
            exit;
        }

        // Extract required data
        $title = $data['title'];
        $description = $data['description'];
        $category = $data['category'] ?? 'website'; // Default category is 'website'.
        $url = $data['url'] ?? null;
        $image = $data['image'] ?? null;
        $favicon = $data['favicon'] ?? null;

        // Control flags with defaults set to true
        $set_canonical = $data['set_canonical'] ?? true;
        $set_og = $data['set_og'] ?? true;
        $set_twitter_og = $data['set_twitter_og'] ?? true;
        $set_json_ld = $data['set_json_ld'] ?? true;

        // Build JSON-LD structure
        $json_ld = [
            '@context' => 'https://schema.org',
            '@type' => ucfirst($category),
            'id' => $url ?: '/',
            'name' => $title,
            'description' => $description
        ];
        if (!empty($data['json_ld']) && is_array($data['json_ld'])) {
            $json_ld = array_merge($json_ld, $data['json_ld']);
        }

        // Build Open Graph (OG) structure
        $og = [
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => $category,
            'og:url' => $url,
            'og:image' => $image
        ];
        if (!empty($data['og']) && is_array($data['og'])) {
            $og = array_merge($og, $data['og']);
        }

        // Build Twitter Card structure
        $twitter = [
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $image
        ];
        if (!empty($data['twitter_og']) && is_array($data['twitter_og'])) {
            $twitter = array_merge($twitter, $data['twitter_og']);
        }

        // Initialize alert messages and output
        $alertMessage = '';
        $output = '';

        // Set the title and description
        $output .= '<title>' . htmlspecialchars($title) . '</title>';
        $output .= '<meta name="description" content="' . htmlspecialchars($description) . '">';

        // Set favicon if provided
        if ($favicon) {
            $output .= '<link rel="icon" href="' . htmlspecialchars($favicon) . '" type="image/x-icon">';
        }

        // Set canonical tag if enabled and URL is provided
        if ($set_canonical) {
            if (!$url) {
                $alertMessage .= 'Canonical tag cannot be set without a URL. ';
            } else {
                $output .= '<link rel="canonical" href="' . htmlspecialchars($url) . '">';
            }
        }

        // Add JSON-LD if enabled
        if ($set_json_ld) {
            $output .= '<script type="application/ld+json">' . json_encode($json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
        }

        // Add Open Graph (OG) meta tags if enabled
        if ($set_og) {
            foreach ($og as $property => $content) {
                if (!empty($content)) {
                    $output .= '<meta property="' . htmlspecialchars($property) . '" content="' . htmlspecialchars($content) . '">';
                }
            }
        }

        // Add Twitter Card meta tags if enabled
        if ($set_twitter_og) {
            foreach ($twitter as $name => $content) {
                if (!empty($content)) {
                    $output .= '<meta name="' . htmlspecialchars($name) . '" content="' . htmlspecialchars($content) . '">';
                }
            }
        }

        // Log alert messages in the browser console if any
        if (!empty($alertMessage)) {
            $output .= '<script>console.error("' . htmlspecialchars($alertMessage) . '");</script>';
        }

        // Output the generated HTML
        echo $output;
    }

    /**
     * Generates an XML sitemap for a given base URL and an array of pages.
     *
     * This function creates an XML sitemap containing the base URL (homepage) 
     * and additional pages if provided. The sitemap is formatted according to 
     * the standard sitemap.org schema, allowing search engines to crawl the site.
     *
     * @param string $base_url The base URL of the website (e.g., 'https://www.example.com/').
     * @param array $pages An optional array of page paths to include in the sitemap. 
     *                     If empty, only the homepage will be included.
     * @return string|false The XML string representing the sitemap, or false if validation fails.
     * @throws Exception if the base URL is invalid or if any page in the array is not a string.
     */
    public static function generateSitemap($base_url, $pages = []) {
        // Validate the base URL
        if (!filter_var($base_url, FILTER_VALIDATE_URL)) {
            throw new \Exception("Invalid base URL: $base_url");
            return false;
        }

        // Validate that all pages are strings
        if (!empty($pages)) {
            foreach ($pages as $page) {
                if (!is_string($page)) {
                    throw new \Exception("Invalid page value: All pages must be strings.");
                    return false;
                }
            }
        }

        // Initialize the XML output with the required header and root element
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Add the homepage
        $sitemap .= '<url>';
        $sitemap .= '<loc>' . htmlspecialchars($base_url) . '</loc>';
        $sitemap .= '<changefreq>daily</changefreq>';
        $sitemap .= '<priority>1.0</priority>';
        $sitemap .= '</url>';

        // Add each additional page if provided
        if (!empty($pages)) {
            foreach ($pages as $page) {
                $sitemap .= '<url>';
                $sitemap .= '<loc>' . htmlspecialchars($base_url . $page) . '</loc>';
                $sitemap .= '<changefreq>weekly</changefreq>';
                $sitemap .= '<priority>0.8</priority>';
                $sitemap .= '</url>';
            }
        }

        // Close the root element
        $sitemap .= '</urlset>';

        return $sitemap;
    }

    /**
     * Generates the HTML block for file management, including file listing and editor.
     *
     * @param string $fileDir The directory path to list and manage files.
     * @return string The HTML output for the file management block.
     */
    public static function fileManagementBlock($fileDir): string {
        // Initialize the output
        $output = '';

        // Display the public assets list
        $output .= '<div class="lcsFileManagement">';
        $output .= '<div class="lcsFileListingWrapper">';
        $output .= '<div class="lcsFileListing">';
        $output .= lcs_list_dirs_data($fileDir);
        $output .= '</div>';
        $output .= '</div>';

        // Display the file editor
        $output .= '<div class="lcsFileEditorWrapper">';
        $output .= '<div class="lcsFileEditor">';

        // File editor header
        $output .= '<div class="_editor_header">';
        // Language mode selector
        $output .= '<select id="_editor_language_selector" aria-label="Select a Language" onchange="lcsSwitchCodeLanguage(this.value)">';
        $output .= '    <option value="php">PHP</option>';
        $output .= '    <option value="javascript">JavaScript</option>';
        $output .= '    <option value="html">HTML</option>';
        $output .= '    <option value="css">CSS</option>';
        $output .= '</select>';
        $output .= '</div>';

        // File editor body
        $output .= '<div class="_editor_body">';
        $output .= '<textarea id="lcsFile_editor" class="_file_editor" placeholder="Select a file from the list to edit"></textarea>';
        $output .= '</div>';

        // File editor footer
        $output .= '<div class="_editor_footer">';
        $output .= '<div class="_code_action_buttons">';
        $output .= '<button type="button" class="_save_file_content">Save</button>';
        $output .= '</div>';
        $output .= '</div>';

        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>'; // Close the file management block

        return $output;
    }


}