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

    /**
     * Generates an HTML nested list representing the files and directories within a specified directory.
     *
     * Traverses the given directory path, recursively listing all files and subdirectories.
     * Optionally, a sensitive directory path can be provided to highlight or handle specific directories differently.
     *
     * @param string $directory         Absolute or relative path to the directory to be listed.
     * @param string $sensitiveDirectory Optional path to a directory that should be treated as sensitive (e.g., for highlighting or exclusion).
     * @return string                   HTML markup of the directory contents as a nested list, or a message if the directory is invalid or empty.
     */
    public static function listDirData( string $directory, string $sensitiveDirectory = '' ) :string {
        if (!is_dir($directory)) {
            return '<p>The provided path is not a directory or does not exist.</p>';
        }

        // Use the listing and rendering functions
        $items = self::getDirData($directory, $sensitiveDirectory);

        if (!empty($items)) {
            return self::renderDirDataList($items);
        } else {
            return '<p>No files or directories found.</p>';
        }
    }

    /**
     * Recursively retrieves detailed information about all files and directories within a specified directory.
     *
     * This method traverses the given directory and its subdirectories, returning a structured array
     * that describes each file and directory found. The returned array contains metadata for each item,
     * including its name, normalized path, type, and, for directories, their children.
     *
     * @param string $directory The absolute or relative path to the directory to scan.
     * @param string $sensitiveDirectory (Optional) A directory path to be treated as sensitive, which may affect path normalization or filtering.
     * @return array An array of associative arrays, each representing a file or directory with the following keys:
     *               - 'name': string - The name of the file or directory.
     *               - 'path': string - The normalized full path to the item, relative to one directory above the server root.
     *               - 'type': string - Either 'file' or 'directory'.
     *               - 'children': array (optional) - If the item is a directory, contains an array of its children in the same format.
     */
    public static function getDirData(string $directory, string $sensitiveDirectory = ''): array {
        if (!is_dir($directory)) {
            return []; // Return empty if the directory is invalid
        }

        // Get one directory above the server root
        $serverRoot = realpath($_SERVER['DOCUMENT_ROOT'] . '/..'); // Go up one directory
        $serverRoot = str_replace('\\', '/', $serverRoot); // Normalize server root

        $sensDir = !empty($sensitiveDirectory) ? strval($sensitiveDirectory) : $serverRoot;

        $directory = str_replace('\\', '/', realpath($directory)); // Normalize input directory

        $files = scandir($directory); // Fetch all items in the directory
        $result = [];

        foreach ($files as $file) {
            // Exclude "." and ".."
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Get the full path to the file or directory
            $file_path = realpath($directory . DIRECTORY_SEPARATOR . $file);

            // Skip invalid paths
            if ($file_path === false) {
                continue;
            }

            // Normalize the path and make it relative to one directory above the server root
            $normalized_path = self::normalizePath($file_path);
            $normalized_sens_dir = self::normalizePath($sensDir);
            $relative_path = str_replace($normalized_sens_dir, '', $normalized_path);

            // Create an item for the file or directory
            $item = [
                'name' => $file,
                'path' => $relative_path,
                'type' => is_dir($file_path) ? 'directory' : 'file',
            ];

            // Set the file extension if it's a file
            if ($item['type'] === 'file') {
                $item['file_extension'] = pathinfo($file_path, PATHINFO_EXTENSION);
            }

            // If it's a directory, recursively fetch its contents
            if ($item['type'] === 'directory') {
                $item['children'] = self::getDirData($file_path, $sensitiveDirectory);
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Normalizes a file or directory path by resolving it to its absolute path and converting backslashes to forward slashes.
     *
     * @param string $path The file or directory path to normalize.
     * @return string|false The normalized absolute path, or false if the path does not exist.
     */
    public static function normalizePath($path) {
        $realPath = realpath($path);
        if ($realPath === false) {
            return false;
        }
        return str_replace('\\', '/', $realPath);
    }

    /**
     * Renders files and directories as a nested HTML unordered list.
     *
     * @param array $items An array of files and directories as returned by `lcs_get_dirs_data`.
     * @return string The HTML representation of the files and directories as a nested list.
     */
    public static function renderDirDataList( array $items ) :string {
        if (empty($items)) {
            return '';
        }

        $html = '<ul>';
        foreach ($items as $item) {
            // Use different icons for directories and files
            $icon = $item['type'] === 'directory' ? '📁' : '📄';
            
            // Add a class for directories and files
            $liClassName = $item['type'] === 'directory' ? 'lcs_dir_data_item lcs_dir_item' : 'lcs_dir_data_item lcs_file_item';
            $liClassName .= $item['type'] === 'directory' && !empty($item['children']) 
            ? ' _has_children' : '';

            // Get the file path
            $filePath = $item['path'];

            // Get the file extension if it's a file
            $fileExtension = $item['type'] === 'file' ? $item['file_extension'] : '';

            // Set data attributes for the file extension if it's a file and $fileExtension is not empty
            $fileExtensionDataSet = '';
            if ($item['type'] === 'file' && !empty($fileExtension)) {
                $fileExtensionDataSet = ' data-file_extension="' . $fileExtension . '"';
            }

            // Display directory/file name
            $html .= '<li class="' . $liClassName . '" data-file_path="' . $filePath . '" ' . $fileExtensionDataSet . '>';
            $html .= $icon . ' ' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');

            // If it's a directory and has children, recursively render them
            if ($item['type'] === 'directory' && !empty($item['children'])) {
                $html .= self::renderDirDataList($item['children']);
            }

            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

}