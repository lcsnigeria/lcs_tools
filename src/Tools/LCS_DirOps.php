<?php
namespace lcsTools\Tools;

class LCS_DirOps
{
    /**
     * Require all files with given extension(s) from a directory, with optional exclusions.
     *
     * @param string               $dir         Path to the directory containing files to require.
     * @param string|array<string> $extensions  File extension or list of extensions to look for (without leading dot). Default 'php'.
     * @param bool                 $once        If true, use require_once; if false, use require. Default true.
     * @param string|array<string> $exclusions  Filename or list of filenames (with or without extension) to skip. Default [].
     *
     * @return void
     * @throws \InvalidArgumentException if $dir is not a valid directory.
     *
     * @example
     * // Require all PHP files in "src/Services" using require_once:
     * LCS_DirOps::requireAll(__DIR__ . '/src/Services');
     *
     * @example
     * // Require all ".php" and ".js" files in "lib/Assets", but skip "DebugHelper.php" and "vendor.js":
     * LCS_DirOps::requireAll(
     *     __DIR__ . '/lib/Assets',
     *     ['php', 'js'],
     *     true,
     *     ['DebugHelper.php', 'vendor.js']
     * );
     *
     * @example
     * // Require all ".inc" files in "includes", skipping any named "legacy.inc":
     * LCS_DirOps::requireAll(
     *     __DIR__ . '/includes',
     *     'inc',
     *     false,
     *     'legacy.inc'
     * );
     *
     * @example
     * // Require all ".css", ".scss", or ".less" files in "styles", skipping any named "legacy":
     * LCS_DirOps::requireAll(
     *     __DIR__ . '/styles',
     *     ['css', 'scss', 'less'],
     *     true,
     *     ['legacy']
     * );
     */
    public static function requireAll(string $dir, $extensions = 'php', bool $once = true, $exclusions = []): void
    {
        // Normalize directory path (remove trailing slash/backslash)
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);

        if (!is_dir($dir)) {
            throw new \InvalidArgumentException("LCS_DirOps::requireAll(): “{$dir}” is not a valid directory.");
        }

        // Normalize $extensions to an array of strings
        if (is_string($extensions)) {
            $extensions = [trim($extensions)];
        } elseif (is_array($extensions)) {
            $extensions = array_values(array_filter(array_map('trim', $extensions), function($ext) {
                return $ext !== '';
            }));
        } else {
            throw new \InvalidArgumentException("LCS_DirOps::requireAll(): \$extensions must be a string or array of strings.");
        }

        if (empty($extensions)) {
            // Nothing to require
            return;
        }

        // Normalize $exclusions to an array of strings
        if (is_string($exclusions)) {
            $exclusions = [trim($exclusions)];
        } elseif (is_array($exclusions)) {
            $exclusions = array_values(array_filter(array_map('trim', $exclusions), function($item) {
                return $item !== '';
            }));
        } else {
            throw new \InvalidArgumentException("LCS_DirOps::requireAll(): \$exclusions must be a string or array of strings.");
        }

        // Prepare exclusion maps: full filename => true, basename (without ext) => true
        $excludeFullNames = [];
        $excludeBasenames = [];
        foreach ($exclusions as $ex) {
            // Strip any directory separators
            $exClean = trim($ex, DIRECTORY_SEPARATOR);
            if ($exClean === '') {
                continue;
            }

            $dotPos = strrpos($exClean, '.');
            if ($dotPos !== false) {
                // If the extension part matches any in $extensions, treat as full filename
                $extPart = substr($exClean, $dotPos + 1);
                if (in_array($extPart, $extensions, true)) {
                    $excludeFullNames[$exClean] = true;
                    continue;
                }
            }
            // Otherwise treat as basename without extension
            $excludeBasenames[$exClean] = true;
        }

        // For each extension, glob and require matching files
        foreach ($extensions as $ext) {
            $pattern = $dir . DIRECTORY_SEPARATOR . '*.' . $ext;
            $files = glob($pattern);
            if ($files === false) {
                continue;
            }

            foreach ($files as $filePath) {
                if (!is_file($filePath)) {
                    continue;
                }

                $basename  = basename($filePath);                        // e.g. "MyClass.php"
                $nameNoExt = pathinfo($filePath, PATHINFO_FILENAME);      // e.g. "MyClass"

                // Skip if in full-filename exclusions or basename exclusions
                if (isset($excludeFullNames[$basename]) || isset($excludeBasenames[$nameNoExt])) {
                    continue;
                }

                if ($once) {
                    require_once $filePath;
                } else {
                    require $filePath;
                }
            }
        }
    }
}