<?php
namespace LCSNG\Tools\Utils;

use LCSNG\Tools\FileManagement\LCS_FileManager;

/**
 * Class LCS_TemplateOps
 *
 * Provides static methods for generating HTML meta tags, Open Graph tags,
 * Twitter Card metadata, JSON-LD structured data, XML sitemaps, and file
 * management UI blocks.
 *
 * Designed for general use across any PHP project. No platform-specific
 * constants or defaults are assumed. All site-level values are either passed
 * directly by the caller or configured once via {@see LCS_TemplateOps::configure()}.
 *
 * @package LCSNG\Tools\Utils
 */
class LCS_TemplateOps
{
    // =========================================================================
    //  CONFIGURATION
    // =========================================================================

    /**
     * Site-level defaults set once via configure().
     * All keys are optional — only set what your project uses.
     *
     * @var array<string, string|null>
     */
    private static array $config = [
        'site_url'       => null,   // e.g. 'https://example.com'
        'site_name'      => null,   // e.g. 'My App'
        'site_desc'      => null,   // Fallback description when none is passed
        'favicon_url'    => null,   // e.g. '/favicon.ico'
        'og_locale'      => 'en_US',
        'twitter_handle' => null,   // e.g. '@myapp'
    ];

    /**
     * Configure site-level defaults once — typically in your bootstrap or config file.
     *
     * All keys are optional. Any key not set here can still be overridden per-call.
     *
     * @param array $config {
     *     @type string|null 'site_url'       Canonical base URL (scheme + host, no trailing slash).
     *     @type string|null 'site_name'      Site name used in og:site_name.
     *     @type string|null 'site_desc'      Fallback description when the caller passes none.
     *     @type string|null 'favicon_url'    Default favicon URL.
     *     @type string      'og_locale'      Default OG locale. Default: 'en_US'.
     *     @type string|null 'twitter_handle' Twitter/X @handle for twitter:site.
     * }
     *
     * @example
     * ```php
     * // In your app bootstrap / config.php:
     * LCS_TemplateOps::configure([
     *     'site_url'       => 'https://example.com',
     *     'site_name'      => 'My App',
     *     'site_desc'      => 'The best app for doing things.',
     *     'favicon_url'    => '/assets/favicon.ico',
     *     'og_locale'      => 'en_GB',
     *     'twitter_handle' => '@myapp',
     * ]);
     * ```
     */
    public static function configure(array $config): void
    {
        foreach (array_keys(self::$config) as $key) {
            if (array_key_exists($key, $config)) {
                self::$config[$key] = $config[$key];
            }
        }
    }

    // =========================================================================
    //  META TAGS
    // =========================================================================

    /**
     * Generates and outputs the full HTML `<head>` metadata block:
     * title, description, canonical, robots, favicon, JSON-LD, Open Graph,
     * and Twitter Card tags.
     *
     * ── Required ─────────────────────────────────────────────────────────────
     * @param string 'title'       Page title. Throws if empty — no safe fallback exists.
     *
     * @param string 'description' Meta description. Falls back gracefully through:
     *                             1. Provided value
     *                             2. 'site_desc' set via configure()
     *                             3. Empty string (omits the meta tag rather than crashing)
     *
     * ── Optional ─────────────────────────────────────────────────────────────
     * @param string 'category'    Schema.org / OG type. Default: 'website'.
     *                             Common values: 'website', 'article', 'product', 'profile'.
     * @param string 'url'         Canonical URL. Auto-resolved from 'site_url' configured
     *                             base + REQUEST_URI (query string stripped) when omitted.
     * @param string 'image'       Absolute URL for OG / Twitter card image.
     * @param string 'favicon'     Favicon URL. Falls back to 'favicon_url' from configure().
     * @param string 'locale'      OG locale. Falls back to 'og_locale' from configure(),
     *                             then 'en_US'.
     * @param bool   'noindex'     Emit <meta name="robots" content="noindex,nofollow">.
     *                             Use for login pages, admin areas, etc. Default: false.
     *
     * ── Toggle flags (all default true) ──────────────────────────────────────
     * @param bool 'set_canonical'   Emit canonical link tag.
     * @param bool 'set_og'          Emit Open Graph meta tags.
     * @param bool 'set_twitter_og'  Emit Twitter Card meta tags.
     * @param bool 'set_json_ld'     Emit JSON-LD structured data.
     *
     * ── Overrides / extensions ────────────────────────────────────────────────
     * @param array 'json_ld'     Merged into (and overrides) the auto-built JSON-LD object.
     * @param array 'og'          Merged into (and overrides) the auto-built OG map.
     * @param array 'twitter_og'  Merged into (and overrides) the auto-built Twitter map.
     *
     * @throws \InvalidArgumentException When title is empty.
     *
     * @example — Basic page
     * ```php
     * use LCSNG\Tools\Utils\LCS_TemplateOps;
     *
     * LCS_TemplateOps::defineMetaTags([
     *     'title'       => 'Home — My App',
     *     'description' => 'Welcome to My App.',
     * ]);
     * ```
     *
     * @example — Product / article page with structured data
     * ```php
     * LCS_TemplateOps::defineMetaTags([
     *     'title'       => 'Product Name — My App',
     *     'description' => $product['meta_description'] ?: $product['summary'],
     *     'category'    => 'product',
     *     'image'       => $product['cover_image_url'],
     *     'json_ld'     => [
     *         '@type'  => 'Product',
     *         'brand'  => ['@type' => 'Brand', 'name' => $product['brand']],
     *         'offers' => [
     *             '@type'         => 'Offer',
     *             'price'         => $product['price'],
     *             'priceCurrency' => $product['currency'],
     *             'availability'  => 'https://schema.org/InStock',
     *         ],
     *     ],
     * ]);
     * ```
     *
     * @example — Private / login page (no indexing, no social tags)
     * ```php
     * LCS_TemplateOps::defineMetaTags([
     *     'title'          => 'Sign In — My App',
     *     'description'    => 'Authorised access only.',
     *     'noindex'        => true,
     *     'set_og'         => false,
     *     'set_twitter_og' => false,
     *     'set_json_ld'    => false,
     * ]);
     * ```
     */
    public static function defineMetaTags(array $data): void
    {
        // ── Validate title ────────────────────────────────────────────────────
        if (empty($data['title'])) {
            throw new \InvalidArgumentException(
                static::class . '::defineMetaTags — "title" is required and cannot be empty.'
            );
        }

        // ── Description — graceful fallback, never throws ─────────────────────
        $description = trim($data['description'] ?? '');
        if ($description === '') {
            $description = self::$config['site_desc'] ?? '';
        }

        // ── Extract values ────────────────────────────────────────────────────
        $title    = trim($data['title']);
        $category = $data['category'] ?? 'website';
        $locale   = $data['locale']   ?? self::$config['og_locale'] ?? 'en_US';
        $image    = $data['image']    ?? null;
        $favicon  = $data['favicon']  ?? self::$config['favicon_url'] ?? null;
        $noindex  = !empty($data['noindex']);
        $url      = $data['url']      ?? self::resolveCurrentUrl();

        // ── Toggle flags ──────────────────────────────────────────────────────
        $setCanonical = $data['set_canonical']  ?? true;
        $setOg        = $data['set_og']          ?? true;
        $setTwitter   = $data['set_twitter_og']  ?? true;
        $setJsonLd    = $data['set_json_ld']     ?? true;

        // ── Build JSON-LD ─────────────────────────────────────────────────────
        $jsonLd = [
            '@context'    => 'https://schema.org',
            '@type'       => ucfirst($category),
            '@id'         => $url ?: '/',
            'name'        => $title,
        ];
        if ($description !== '') {
            $jsonLd['description'] = $description;
        }
        if ($image) {
            $jsonLd['image'] = $image;
        }
        if (!empty($data['json_ld']) && is_array($data['json_ld'])) {
            $jsonLd = array_merge($jsonLd, $data['json_ld']);
        }

        // ── Build OG ─────────────────────────────────────────────────────────
        $og = [
            'og:title'    => $title,
            'og:type'     => $category,
            'og:locale'   => $locale,
        ];
        if ($description !== '') $og['og:description'] = $description;
        if ($url)                $og['og:url']          = $url;
        if ($image)              $og['og:image']        = $image;
        if (self::$config['site_name'] !== null) {
            $og['og:site_name'] = self::$config['site_name'];
        }
        if (!empty($data['og']) && is_array($data['og'])) {
            $og = array_merge($og, $data['og']);
        }

        // ── Build Twitter Card ────────────────────────────────────────────────
        $twitter = ['twitter:card' => 'summary_large_image', 'twitter:title' => $title];
        if ($description !== '') $twitter['twitter:description'] = $description;
        if ($image)              $twitter['twitter:image']       = $image;
        if (self::$config['twitter_handle'] !== null) {
            $twitter['twitter:site'] = self::$config['twitter_handle'];
        }
        if (!empty($data['twitter_og']) && is_array($data['twitter_og'])) {
            $twitter = array_merge($twitter, $data['twitter_og']);
        }

        // ── Render output ─────────────────────────────────────────────────────
        $out  = '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
        if ($description !== '') {
            $out .= '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if ($noindex) {
            $out .= '<meta name="robots" content="noindex,nofollow">' . "\n";
        }
        if ($favicon) {
            $out .= '<link rel="icon" href="' . htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8') . '" type="image/x-icon">' . "\n";
        }
        if ($setCanonical) {
            if ($url) {
                $out .= '<link rel="canonical" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            } else {
                $out .= '<script>console.warn("' . static::class . ': canonical skipped — no URL resolved.");</script>' . "\n";
            }
        }
        if ($setJsonLd) {
            $out .= '<script type="application/ld+json">'
                  . json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                  . '</script>' . "\n";
        }
        if ($setOg) {
            foreach ($og as $property => $content) {
                if (!empty($content)) {
                    $out .= '<meta property="' . htmlspecialchars($property, ENT_QUOTES, 'UTF-8')
                          . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '">' . "\n";
                }
            }
        }
        if ($setTwitter) {
            foreach ($twitter as $name => $content) {
                if (!empty($content)) {
                    $out .= '<meta name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                          . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '">' . "\n";
                }
            }
        }

        echo $out;
    }

    // =========================================================================
    //  SITEMAP
    // =========================================================================

    /**
     * Generates an XML sitemap string for the given base URL and page paths.
     *
     * @param  string   $baseUrl  Base URL of the website (e.g. 'https://example.com').
     * @param  string[] $pages    Page paths to include (e.g. ['/about', '/contact']).
     * @param  array    $options  Per-page overrides keyed by path:
     *                            ['changefreq' => 'daily', 'priority' => '1.0']
     * @return string  The complete XML sitemap string.
     * @throws \InvalidArgumentException On invalid base URL or non-string page path.
     *
     * @example
     * ```php
     * $xml = LCS_TemplateOps::generateSitemap('https://example.com', [
     *     '/about',
     *     '/products',
     *     '/contact',
     * ], [
     *     '/products' => ['changefreq' => 'daily', 'priority' => '0.9'],
     * ]);
     *
     * header('Content-Type: application/xml; charset=UTF-8');
     * echo $xml;
     * ```
     */
    public static function generateSitemap(string $baseUrl, array $pages = [], array $options = []): string
    {
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException(static::class . "::generateSitemap — invalid base URL '{$baseUrl}'.");
        }
        foreach ($pages as $page) {
            if (!is_string($page)) {
                throw new \InvalidArgumentException(static::class . '::generateSitemap — all page paths must be strings.');
            }
        }

        $baseUrl = rtrim($baseUrl, '/');
        $xml     = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                 . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $xml .= self::sitemapUrl($baseUrl . '/', 'daily', '1.0');

        foreach ($pages as $page) {
            $page       = '/' . ltrim($page, '/');
            $opts       = $options[$page] ?? [];
            $xml .= self::sitemapUrl(
                $baseUrl . $page,
                $opts['changefreq'] ?? 'weekly',
                $opts['priority']   ?? '0.8'
            );
        }

        return $xml . '</urlset>';
    }

    // =========================================================================
    //  FILE MANAGEMENT BLOCK
    // =========================================================================

    /**
     * Generates the HTML block for file management, including a file tree and Monaco editor.
     *
     * @param  string $fileOrDir  Directory path or single file path to manage.
     * @param  array  $configData {
     *     @type string $id            Required. HTML element ID.
     *     @type bool   $expandable    Show expand/collapse button. Default false.
     *     @type bool   $listDirectory Show file tree sidebar. Default false.
     *     @type bool   $previewFile   Load file in Monaco editor on init. Default false.
     *     @type string $sensDir       Sensitive path prefix to strip from file paths
     *                                 shown to the browser. Default ''.
     * }
     * @return array{
     *     fmHTML: string,
     *     initialFileContents: mixed,
     *     initialFilePath: string,
     *     initialFileName: string,
     *     initialFileExtension: string|null
     * }
     * @throws \InvalidArgumentException On missing 'id' or invalid path.
     * @throws \RuntimeException         When previewFile is true but directory is empty.
     */
    public static function fileManagementBlock(string $fileOrDir, array $configData = []): array
    {
        $isFile = is_file($fileOrDir);
        $isDir  = is_dir($fileOrDir);

        if (!$isFile && !$isDir) {
            throw new \InvalidArgumentException(
                static::class . "::fileManagementBlock — '{$fileOrDir}' is neither a valid file nor a directory."
            );
        }
        if (empty($configData['id'])) {
            throw new \InvalidArgumentException(
                static::class . "::fileManagementBlock — 'id' configuration key is required."
            );
        }

        $expandable        = !empty($configData['expandable']);
        $shouldListDirs    = !empty($configData['listDirectory']);
        $shouldPreviewFile = !empty($configData['previewFile']);
        $sensitiveDir      = !empty($configData['sensDir']) ? (string) $configData['sensDir'] : '';

        $out  = '<div class="lcsFileManagement" id="' . htmlspecialchars($configData['id'], ENT_QUOTES, 'UTF-8') . '">';
        $out .= '<div class="_overhead_bar">';
        if ($expandable) {
            $out .= '<button type="button" class="_editor_expander" title="Expand/Collapse Editor" onclick="lcsToggleEditorExpand(this)"><i class="fa fa-expand"></i></button>';
        }
        $out .= '<div class="_code_action_buttons">'
              . '<button type="button" id="_save_file_content">Save</button>'
              . '</div></div>';

        if ($isDir && $shouldListDirs) {
            $out .= '<div class="lcsFileListingWrapper"><div class="lcsFileListing">'
                  . LCS_DirOps::listDirData($fileOrDir, $sensitiveDir)
                  . '</div></div>';
        }

        $out .= '<div class="lcsFileEditorWrapper"><div class="lcsFileEditor">'
              . '<div class="_editor_header"></div>';

        $returnedData = [];
        $jsCode       = '';
        $fileManager  = new LCS_FileManager(null, false);

        if ($shouldPreviewFile) {
            if ($isFile) {
                $initialFile = $fileOrDir;
            } else {
                $dirData = $fileManager->readDir($fileOrDir, [], true);
                if (empty($dirData['total_files'])) {
                    throw new \RuntimeException(
                        static::class . "::fileManagementBlock — directory '{$fileOrDir}' contains no files to preview."
                    );
                }
                $initialFile = $dirData['total_files'][0];
            }

            $clipped   = pathinfo($initialFile, PATHINFO_FILENAME) . '.' . pathinfo($initialFile, PATHINFO_EXTENSION);
            $nSD       = LCS_DirOps::normalizePath($sensitiveDir);
            $niF       = LCS_DirOps::normalizePath($initialFile);

            // normalizePath returns false when the path doesn't exist — fall back to manual normalisation
            if ($nSD === false) $nSD = str_replace('\\', '/', $sensitiveDir);
            if ($niF === false) $niF = str_replace('\\', '/', $initialFile);

            $cleanPath = ($nSD !== '') ? str_replace($nSD, '', $niF) : $niF;
            $contents  = $fileManager->getFileContents($initialFile, false, false);
            $cleanName = preg_replace('/\.$/', '', $clipped);
            $ext       = pathinfo($initialFile, PATHINFO_EXTENSION) ?: null;

            $returnedData['initialFileContents']  = $contents;
            $returnedData['initialFilePath']       = $cleanPath;
            $returnedData['initialFileName']       = $cleanName;
            $returnedData['initialFileExtension']  = $ext;

            $jsCode = '<script>(async()=>{ await lcsInitializeMonacoEditor('
                . json_encode($contents,   JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ','
                . json_encode($cleanPath,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ','
                . json_encode($cleanName,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ','
                . json_encode($ext,        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . '); })();</script>';
        }

        $out .= '<div class="_editor_body"></div>'
              . '<div class="_editor_footer">' . $jsCode . '</div>'
              . '</div></div></div>';

        $returnedData['fmHTML'] = $out;

        return $returnedData;
    }

    // =========================================================================
    //  PRIVATE HELPERS
    // =========================================================================

    /**
     * Resolves the current page's absolute URL from the configured 'site_url'
     * or falls back to $_SERVER variables. Query string is stripped so the
     * canonical URL is always the clean path.
     *
     * @return string|null Null when neither site_url nor SERVER host is available.
     */
    private static function resolveCurrentUrl(): ?string
    {
        $base = self::$config['site_url'] !== null
            ? rtrim(self::$config['site_url'], '/')
            : null;

        if ($base === null) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? null;
            if ($host === null) return null;
            $base = $scheme . '://' . $host;
        }

        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        return $base . $path;
    }

    /**
     * Renders a single <url> block for the XML sitemap.
     */
    private static function sitemapUrl(string $loc, string $changefreq, string $priority): string
    {
        return '<url>'
             . '<loc>'        . htmlspecialchars($loc,        ENT_XML1, 'UTF-8') . '</loc>'
             . '<changefreq>' . htmlspecialchars($changefreq, ENT_XML1, 'UTF-8') . '</changefreq>'
             . '<priority>'   . htmlspecialchars($priority,   ENT_XML1, 'UTF-8') . '</priority>'
             . '</url>' . "\n";
    }
}