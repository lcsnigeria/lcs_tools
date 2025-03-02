<?php
namespace LCSNG_EXT\FileManagement;

/**
 * Class LCS_FileManager
 *
 * Provides tools for managing file operations, including uploading, copying, moving, deleting,
 * compressing, extracting, and reading files and archives. Supports formats such as ZIP, RAR, and TAR.GZ,
 * along with file metadata validation and management.
 *
 * @property array|null $file Stores uploaded file data from the $_FILES array.
 * @property string|null $file_path Direct path to the file on the system.
 * @property string|null $path Target directory path for file operations (e.g., upload, compression).
 * @property int $previous_time_limit Stores the previous PHP script execution time limit, allowing restoration upon class destruction.
 *
 * @method array upload()
 *         Uploads multiple files to the specified directory based on their purpose.
 * @method array copy()
 *         Copies multiple files to a new directory.
 * @method array move()
 *         Moves multiple files to a new directory.
 * @method array delete()
 *         Deletes multiple files.
 * @method string rename(string $new_name)
 *         Renames the file.
 * @method bool download()
 *         Downloads multiple files as a ZIP archive or a single file directly.
 * @method void render()
 *         Renders a file to the browser by setting appropriate headers and streaming the file content.
 * @method string|false zipData(string|array $data, string|null $zipFileName = null, bool $returnData = false)
 *         Creates a ZIP archive from files or directories.
 * @method bool unzipData()
 *         Extracts an archive (ZIP, RAR, TAR.GZ) to the specified directory.
 * @method array|string compress()
 *         Compresses a file or multiple files using gzip.
 * @method array fetch()
 *         Retrieves metadata of a specified file.
 * @method array readArchive(string|array|null $fileNames = null)
 *         Reads the contents of an archive and retrieves contents of specified files.
 * @method string compressFile(string $filePath)
 *         Compresses a single file using gzip.
 * @method void __destruct()
 *         Resets the PHP script execution time limit to its original value.
 *
 * @package File Management
 * @dependencies ZipArchive, RarArchive (optional), PharData
 */
class LCS_FileManager {

    /**
     * @var array $file
     * Uploaded file(s) from the `$_FILES` global variable.
     * - For single file uploads: An associative array containing details of the uploaded file.
     * - For multiple file uploads: An associative array where each key (`name`, `type`, `tmp_name`, `error`, `size`) maps to an array of corresponding values.
     * 
     * Example (Single File Upload):
     * ```php
     * $file = [
     *     'name' => 'file1.zip',
     *     'type' => 'application/zip',
     *     'tmp_name' => '/tmp/phpA1.tmp',
     *     'error' => 0,
     *     'size' => 12345
     * ];
     * ```
     * 
     * Example (Multiple Files Upload - Before Normalization):
     * ```php
     * $file = [
     *     'name' => ['file1.zip', 'file2.zip'],
     *     'type' => ['application/zip', 'application/zip'],
     *     'tmp_name' => ['/tmp/phpA1.tmp', '/tmp/phpB2.tmp'],
     *     'error' => [0, 0],
     *     'size' => [12345, 67890]
     * ];
     * ```
     * 
     * Example (After Normalization for Multiple Files):
     * ```php
     * $file = [
     *     [
     *         'name' => 'file1.zip',
     *         'type' => 'application/zip',
     *         'tmp_name' => '/tmp/phpA1.tmp',
     *         'error' => 0,
     *         'size' => 12345
     *     ],
     *     [
     *         'name' => 'file2.zip',
     *         'type' => 'application/zip',
     *         'tmp_name' => '/tmp/phpB2.tmp',
     *         'error' => 0,
     *         'size' => 67890
     *     ]
     * ];
     * ```
     */
    public $file;

    /**
     * @var string $path
     * The target directory where files will be moved, downloaded, uploaded or managed.
     * Must be a valid, writable directory path.
     * Example:
     * - `/var/www/uploads/profile_pictures/`
     */
    public $path;

    /**
     * @var string|array $file_path
     * The path of a specific file for operations such as download, delete, move, or copy.
     * This property is useful for direct file manipulations outside of the upload process.
     * Example:
     * - `/var/www/uploads/documents/file1.pdf`
     */
    public $file_path;

    /**
     * @var string|null $file_name
     * The name to set for the file when uploading.
     * This property is useful when renaming files during the upload process.
     * Example:
     * - `profile_picture.jpg`
     */
    public $file_name = null;

    /**
     * Custom file type restrictions for uploads.
     *
     * If set, only the specified MIME types will be allowed. 
     * If left empty, all default valid file types will be permitted.
     *
     * ⚠ WARNING: Setting this improperly may block necessary file uploads. 
     * Ensure the specified MIME types are correct and include all required formats.
     *
     * **Example usage:**
     * ```php
     * $this->file_types = ['image/jpeg', 'image/png']; // Only allow JPG and PNG images.
     * ```
     *
     * **Security Notice:** Allowing PHP files can pose a security risk. Ensure that:
     * - Uploaded PHP files are stored in a non-executable directory.
     * - Proper authentication and validation are enforced.
     * - Execution permissions on uploaded PHP files are restricted.
     *
     * @var array|string List of user-defined allowed MIME types.
     */
    public $file_types = [];

    /**
     * @var int $file_limit
     * The maximum number of files allowed for upload in one operation.
     * Default value is `10`.
     */
    public $file_limit = 10;

    /**
     * @var int $time_limit
     * Defines the maximum execution time for file operations.
     * - Default is `0` (no limit).
     * - Users can override this value to set a specific execution time limit.
     * 
     * Example:
     * ```php
     * $fileManager->time_limit = 300; // Set limit to 5 minutes
     * ```
     */
    public $time_limit = 0;

    /**
     * @var bool $rename Indicates whether the file renaming feature is enabled.
     */
    public $rename = true;

    /**
     * Indicates whether existing files should be overwritten.
     *
     * @var bool $overwrite If true, existing files will be overwritten. If false, existing files will be preserved.
     */
    public $overwrite = false;


    /**
     * @var int $previous_time_limit
     * Stores the previous `max_execution_time` PHP configuration value.
     * This is used to revert back to the original execution time limit after file operations are completed.
     */
    private $previous_time_limit;

    /**
     * Constructor for LCS_FileManager class.
     *
     * Initializes the class by temporarily setting the execution time limit and clearing the output buffer.
     * This allows for handling large file uploads or manipulations without worrying about PHP's default execution time restrictions.
     *
     * @param int|null $time_limit Optional. Custom execution time limit in seconds.
     *                             If not provided, the `$time_limit` property will be used.
     */
    public function __construct(int $time_limit = null)
    {
        // Store the current max execution time
        $this->previous_time_limit = ini_get('max_execution_time');

        // Allow overriding the default time limit via parameter
        if ($time_limit !== null) {
            $this->time_limit = $time_limit;
        }

        // Temporarily set the execution time to the user-defined limit or no limit (0)
        set_time_limit($this->time_limit);

        // Clear any existing output buffers to avoid corrupting the output
        while (ob_get_level()) {
            ob_end_clean();
        }
    }

    /**
     * List of valid MIME types for file operations.
     * 
     * This array defines the allowed MIME types for uploaded or rendered files,
     * ensuring only supported file types are processed.
     */
    private static $validMimeTypes = [
        // Images
        "image/jpeg", "image/png", "image/gif", "image/svg+xml", "image/webp",

        // Audio & Video
        "audio/mpeg", "audio/wav", "video/mp4",

        // Documents
        "application/pdf", "text/plain", "text/html", "text/javascript", "application/xhtml+xml",
        "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/javascript", "text/css",

        // Archives
        "application/zip",          // ZIP files
        "application/vnd.rar",      // RAR files
        "application/x-tar",        // TAR files
        "application/gzip",         // GZIP files
        "application/x-7z-compressed", // 7z files

        // PHP Files
        "application/x-httpd-php",  // Standard PHP files
        "text/x-php"                // Alternative PHP MIME type
    ];

    /**
     * Validates if a given MIME type is supported.
     * 
     * This method checks whether the provided MIME type is within the allowed list.
     * If custom file types are set via `$file_types`, they are validated against 
     * the default list of supported MIME types.
     * 
     * @param string $mime_type The MIME type to validate.
     * @return bool True if the MIME type is valid, false otherwise.
     * @throws \Exception If an invalid custom file type is set.
     */
    private function isValidMimeType($mime_type) {
        // Default valid MIME types
        $validMimeTypes = self::$validMimeTypes;

        if (!empty($this->file_types)) {
            $providedFileTypes = is_string($this->file_types) || !is_array($this->file_types) 
                ? (array) $this->file_types 
                : $this->file_types;

            // Check if any provided file types are not in the default valid list
            $invalidFileTypes = array_diff($providedFileTypes, $validMimeTypes);
            if (!empty($invalidFileTypes)) {
                throw new \Exception("Invalid file types detected: " . implode(', ', $invalidFileTypes) . '. Check the class allowed file types in the documentation.');
            }

            // Validate the provided MIME type only against the custom defined file_types
            return in_array($mime_type, $providedFileTypes, true);
        }

        // Validate the provided MIME type
        return in_array($mime_type, $validMimeTypes, true);
    }

    /**
     * Normalizes multiple file uploads into an array of individual file arrays.
     *
     * @param array $files $_FILES['input_name'] array.
     * @return array Array of normalized file arrays.
     */
    private function normalizeFiles(array $files): array {
        $normalized = [];

        if (isset($files[0]) && is_array($files[0])) {
            return array_values($files);
        } elseif (!isset($files['name']) || !is_array($files['name'])) {
            return [$files]; // Already a single file upload
        } else {
            foreach ($files['name'] as $index => $name) {
                $normalized[] = [
                    'name' => $name,
                    'type' => $files['type'][$index],
                    'tmp_name' => $files['tmp_name'][$index],
                    'error' => $files['error'][$index],
                    'size' => $files['size'][$index]
                ];
            }
        }

        return $normalized;
    }

    /**
     * Retrieves the file name from a given file path or file array.
     *
     * @param string|array|null $file The file path or file array.
     * @param bool $withExtension Whether to include the file extension in the returned name.
     * @return string|array The file name or array of them if multiple files are provided.
     * @throws \Exception If the file parameter is invalid or the file does not exist.
     */
    public function getFileName($file = null, $withExtension = false) {
        if (is_null($file) || empty($file)) {
            if ($this->file) {
                $file = $this->file;
            } elseif ($this->file_path) {
                $file = $this->file_path;
            } else {
                throw new \Exception("No file parameter provided and no default file available.");
            }
        }

        $fileNames = '';
        if (is_array($file)) {
            $fileNames = [];
            $file = $this->normalizeFiles($file);
            foreach ($file as $f) {
                if (!isset($f['name'])) {
                    throw new \Exception("Invalid file array provided.");
                }
                $fileNames[] = $withExtension ? basename($f['name']) : pathinfo($f['name'], PATHINFO_FILENAME);
            }
        } elseif (is_string($file)) {
            if (!file_exists($file)) {
                throw new \Exception("File does not exist: " . $file);
            }
            $fileNames = $withExtension ? basename($file) : pathinfo($file, PATHINFO_FILENAME);
        } else {
            throw new \Exception("Invalid file parameter provided.");
        }

        return $fileNames;
    }

    /**
     * Uploads multiple files to the specified directory based on their purpose.
     *
     * @return array The paths of the uploaded files.
     * @throws Exception If any file is invalid or an upload fails.
     */
    public function upload() :array {
        // Check if path is provided and valid
        if (empty($this->path) || !is_dir($this->path) || !is_writable($this->path)) {
            throw new \Exception("Invalid or unwritable path provided.");
        }
    
        // Update time limit for this upload process
        set_time_limit($this->time_limit);
        
        if (!$this->file) {
            throw new \Exception("No files provided.");
        }

        // Normalize $this->file to an array of files
        $files = $this->normalizeFiles($this->file);

        // Limit the number of files
        if (count($files) > $this->file_limit) {
            throw new \Exception("Exceeded file upload limit of {$this->file_limit}.");
        }

        $uploadedFiles = [];
        foreach ($files as $file) {
            if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception("Invalid file provided: " . ($file['name'] ?? 'unknown'));
            }

            if (!file_exists($file['tmp_name'])) {
                throw new \Exception("File does not exist or failed to upload.");
            }

            // Validate MIME type using Fileinfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!$this->isValidMimeType($mime_type)) {
                throw new \Exception("Invalid file type specified for file: " . $file['name']);
            }

            // Determine the target directory
            $targetDirectory = rtrim($this->path, '/') . '/';

            // Create target directory if it doesn't exist
            if (!file_exists($targetDirectory)) {
                if (!mkdir($targetDirectory, 0777, true)) {
                    throw new \Exception("Failed to create target directory: " . $targetDirectory);
                }
            }

            // If $file_name not set; Generate a unique filename to avoid overwriting
            $filename = pathinfo($file['name'], PATHINFO_FILENAME) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            if ($this->rename) {
                $filename = $this->file_name ?? 'lcs_file_' . time() . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            }

            // Check if the file already exists in the target directory
            // Then check if overwrite is enabled, set the file path and delete the existing file before proceeding
            // Else, throw an exception if overwrite is disabled and the file exists
            if ($this->isFileExist($filename, $targetDirectory)) {
                if ($this->overwrite) {
                    $this->file_path = $targetDirectory . $filename;
                    $this->delete();
                } else {
                    throw new \Exception("File '{$filename}' already exists in '{$targetDirectory}'. Either delete it manually or enable overwriting.");
                }
            }

            $targetFile = $targetDirectory . $filename;

            // Move the file to the target directory
            if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
                throw new \Exception("Error moving uploaded file: " . $file['name']);
            }

            $uploadedFiles[] = $targetFile;
        }

        return $uploadedFiles;
    }

    /**
     * Copies multiple files to a new directory.
     *
     * @return array The paths of the copied files.
     * @throws Exception If any file path or target directory is invalid, or a copy operation fails.
     */
    public function copy() {
        if (!$this->file_path || !$this->path) {
            throw new \Exception("File path or target directory not provided.");
        }

        // Normalize $this->file_path into an array
        $filePaths = !is_array($this->file_path) ? [$this->file_path] : $this->file_path;

        // Limit the number of files
        if (count($filePaths) > $this->file_limit) {
            throw new \Exception("Exceeded file copy limit of {$this->file_limit}.");
        }

        // Create the target directory if it doesn't exist
        if (!file_exists($this->path)) {
            mkdir($this->path, 0777, true);
        }

        $copiedFiles = [];
        foreach ($filePaths as $filePath) {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: " . $filePath);
            }

            $filename = basename($filePath);
            $targetFile = rtrim($this->path, '/') . '/' . $filename;

            if (!copy($filePath, $targetFile)) {
                throw new \Exception("Error copying file: " . $filePath);
            }

            $copiedFiles[] = $targetFile;
        }

        return $copiedFiles;
    }

    /**
     * Moves multiple files to a new directory.
     *
     * @return array The paths of the moved files.
     * @throws Exception If any file path or target directory is invalid, or a move operation fails.
     */
    public function move() {
        if (!$this->file_path || !$this->path) {
            throw new \Exception("File path or target directory not provided.");
        }

        // Normalize $this->file_path into an array
        $filePaths = !is_array($this->file_path) ? [$this->file_path] : $this->file_path;

        // Limit the number of files
        if (count($filePaths) > $this->file_limit) {
            throw new \Exception("Exceeded file move limit of {$this->file_limit}.");
        }

        // Create the target directory if it doesn't exist
        if (!file_exists($this->path)) {
            mkdir($this->path, 0777, true);
        }

        $movedFiles = [];
        foreach ($filePaths as $filePath) {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: " . $filePath);
            }

            $filename = basename($filePath);
            $targetFile = rtrim($this->path, '/') . '/' . $filename;

            if (!rename($filePath, $targetFile)) {
                throw new \Exception("Error moving file: " . $filePath);
            }

            $movedFiles[] = $targetFile;
        }

        return $movedFiles;
    }

    /**
     * Deletes multiple files or directories.
     *
     * @return array List of successfully deleted file paths.
     * @throws Exception If any file path is invalid or a delete operation fails.
     */
    public function delete() {
        if (!$this->file_path) {
            throw new \Exception("File path not provided.");
        }

        // Normalize $this->file_path into an array
        $filePaths = !is_array($this->file_path) ? [$this->file_path] : $this->file_path;

        // Limit the number of files
        if (count($filePaths) > $this->file_limit) {
            throw new \Exception("Exceeded file delete limit of {$this->file_limit}.");
        }

        $deletedFiles = [];
        foreach ($filePaths as $filePath) {
            if (!file_exists($filePath)) {
                throw new \Exception("File or directory not found: " . $filePath);
            }

            if (is_dir($filePath)) {
                // Handle directory deletion
                if (!$this->deleteDirectory($filePath)) {
                    throw new \Exception("Error deleting directory: " . $filePath);
                }
            } else {
                // Handle file deletion
                if (!unlink($filePath)) {
                    throw new \Exception("Error deleting file: " . $filePath);
                }
            }

            $deletedFiles[] = $filePath;
        }

        return $deletedFiles;
    }

    /**
     * Recursively deletes a directory and its contents.
     *
     * @param string $dir Directory path to delete.
     * @return bool True on success, false on failure.
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path); // Recursive call
            } else {
                unlink($path);
            }
        }

        return rmdir($dir); // Delete the directory itself
    }

    /**
     * Renames the file.
     *
     * @param string $new_name New name for the file.
     * @return string The new file path.
     * @throws Exception If the file path is invalid or rename fails.
     */
    public function rename($new_name) {
        if (!$this->file_path || !file_exists($this->file_path)) {
            throw new \Exception("File not found.");
        }

        $directory = dirname($this->file_path);
        $targetFile = $directory . '/' . $new_name;

        if (rename($this->file_path, $targetFile)) {
            return $targetFile;
        } else {
            throw new \Exception("Error renaming file.");
        }
    }

    /**
     * Checks if a file with the same name and extension already exists in the specified path.
     *
     * @param string $fileName The name of the file to check.
     * @param string $fileDir The Dir where to check for the file.
     * @return bool True if the file exists, false otherwise.
     * @throws Exception If the provided Dir is invalid.
     */
    public function isFileExist($fileName, $fileDir) {
        if (empty($fileDir) || !is_dir($fileDir)) {
            throw new \Exception("Invalid Dir provided.");
        }

        $fullDir = rtrim($fileDir, '/') . '/' . $fileName;
        return file_exists($fullDir);
    }

    /**
     * Downloads multiple files as a ZIP archive or a single file directly.
     * If a valid $path is provided, files are downloaded to that path.
     * If no $path is provided, the files are zipped and downloaded in the browser.
     *
     * @return true
     * @throws Exception If files cannot be downloaded.
     */
    public function download() {
        if (!$this->file_path) {
            throw new \Exception("File path not provided.");
        }

        // Ensure file_paths is always an array
        $filePaths = !is_array($this->file_path) ? [$this->file_path] : $this->file_path;

        // Enforce file limit
        if (count($filePaths) > $this->file_limit) {
            throw new \Exception("Exceeded file download limit of {$this->file_limit}.");
        }

        // Check if the $path property is set for saving files
        if ($this->path) {
            // If path is set, we save files to the specified path
            foreach ($filePaths as $filePath) {
                if (!file_exists($filePath)) {
                    throw new \Exception("File not found: " . $filePath);
                }

                // Copy the file to the specified path
                $filename = basename($filePath);
                $targetPath = rtrim($this->path, '/') . '/' . $filename;

                if (!copy($filePath, $targetPath)) {
                    throw new \Exception("Failed to copy file to the target path: " . $targetPath);
                }
            }

            return true; // Files are successfully copied to the target path
        }

        // If no path is provided, we create a ZIP archive of the files and send it to the browser
        if (count($filePaths) === 1) {
            // Single File Download
            $filePath = $filePaths[0];
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: " . $filePath);
            }

            // Clear output buffers and set headers for file download
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');

            // Output the file
            readfile($filePath);
            exit;
        }

        // Multiple Files: Create ZIP Archive using zipData
        $zipFileName = 'files_bundle_' . time() . '.zip';
        
        // Call zipData() to generate the ZIP file content
        $zipData = $this->zipData($filePaths, $zipFileName, true);  // true to return ZIP data as string

        // Send the ZIP archive to the browser
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
        header('Content-Length: ' . strlen($zipData));

        // Output the binary ZIP data
        echo $zipData;
        exit;
    }

    /**
     * Renders a file to the browser by setting appropriate headers and streaming the file content.
     *
     * This function checks the validity of the file, sets HTTP headers based on the file's MIME type,
     * and streams the file content to the client. It supports caching headers for optimized performance.
     *
     * @throws Exception If the file does not exist, is unsupported, or cannot be read.
     *
     * @return void Outputs the file content directly to the client and exits.
     */
    public function render() {

        // Validate that the file path is set and the file exists
        if (!$this->file_path || !file_exists($this->file_path)) {
            throw new \Exception("File not found: " . $this->file_path);
        }

        // Check if the file is empty
        if (filesize($this->file_path) === 0) {
            return; // Silently skip empty files
        }

        // Get the file's last modified time and size for caching and ETag
        $file_last_modified = filemtime($this->file_path);
        $file_size = filesize($this->file_path);

        // Generate an ETag based on the file's last modified time and size
        $etag = md5($file_last_modified . $file_size);

        // Check if the browser's cached ETag matches the server's
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
            // If the ETags match, return 304 Not Modified
            header('HTTP/1.1 304 Not Modified');
            exit; // No need to send the file again
        }

        // Set Cache-Control and Expires headers to optimize caching
        header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        header('ETag: "' . $etag . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $file_last_modified) . ' GMT');

        // Detect the file's MIME type
        $extension = pathinfo($this->file_path, PATHINFO_EXTENSION);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $this->file_path);
        finfo_close($finfo);

        // Force correct MIME type for CSS and JavaScript files
        if ($extension === 'css') {
            $mime_type = 'text/css';
        } elseif ($extension === 'js') {
            $mime_type = 'text/javascript';
        }

        // Validate the MIME type against a list of supported types
        if (!$this->isValidMimeType($mime_type)) {
            throw new \Exception("Unsupported file type: " . $mime_type);
        }

        // Prevent duplicate headers
        if (headers_sent()) {
            throw new \Exception("Headers already sent. Cannot render file.");
        }

        // Set HTTP headers for file delivery
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($this->file_path));

        // Stream the file content to the client
        if (@readfile($this->file_path) === false) {
            throw new \Exception("Failed to read and output the file.");
        }
        exit;
    }

    /**
     * Create a ZIP archive from one or more files or directories.
     *
     * @param string|array $data The file(s) or directory(ies) to be zipped.
     * @param string|null $zipFileName The name of the resulting ZIP file.
     * @param bool $returnData Whether to return ZIP binary data or save it to a file.
     *
     * @return string|false If $returnData is true, returns ZIP binary data. 
     *                      If false, returns the ZIP file path. Returns false on failure.
     */
    public function zipData($data, $zipFileName = null, $returnData = false) 
    {
        // Set default ZIP file name if not provided
        $zipFileName = $zipFileName ?? 'LCS_Data_' . date("Ymd_His") . '.zip';

        // Determine ZIP file save path
        if ($returnData || !$this->path) {
            $zipFilePath = sys_get_temp_dir() . '/' . $zipFileName; // Default to temp dir
        } else {
            $zipFilePath = rtrim($this->path, '/') . '/' . $zipFileName;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath, \ZipArchive::CREATE) !== true) {
            error_log("Error: Failed to create ZIP file ($zipFilePath).");
            return false;
        }

        // Ensure $data is treated as an array
        $items = is_array($data) ? $data : [$data];

        foreach ($items as $item) {
            if (!file_exists($item)) {
                error_log("Warning: Directory or File '$item' does not exist and will be skipped.");
                continue;
            }

            $basePath = realpath($item);

            if (is_file($item)) {
                // If $item is a file, add it directly with its filename
                $relativePath = basename($item);
                $zip->addFile($basePath, $relativePath);
            } elseif (is_dir($item)) {
                // If $item is a directory, add its contents recursively
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        // Generate relative path inside the ZIP
                        $relativePath = substr($filePath, strlen($basePath) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }
        }

        $zip->close();

        // Return the ZIP data as binary if $returnData is true
        if ($returnData) {
            $binaryData = file_get_contents($zipFilePath);
            unlink($zipFilePath); // Clean up temporary ZIP file
            return $binaryData;
        }

        // Return the path of the saved ZIP file
        return $zipFilePath;
    }

    /**
     * Extracts an archive file (ZIP, RAR, TAR.GZ) to a specified directory.
     * 
     * The archive file is taken from the `$file` property (if available), otherwise from the `$file_path` property.
     * The extraction location is provided by the `$path` property.
     *
     * @param bool $createPath If true, the destination path will be created if it does not exist.
     * @return bool Returns true if the files were successfully extracted, false otherwise.
     * @throws Exception If multiple files are provided, the file is invalid, extraction fails, or the destination path is not provided.
     */
    public function unzipData($createPath = false) {
        // Ensure only one file is provided
        if (isset($this->file['name']) && is_array($this->file['name'])) {
            throw new \Exception("Only a single archive file can be processed at a time for extraction.");
        }

        // Determine file path
        $archiveFilePath = $this->file ? $this->file['tmp_name'] : $this->file_path;
        
        // Validate archive file
        if (!$archiveFilePath || !file_exists($archiveFilePath)) {
            throw new \Exception("Archive file not found or invalid.");
        }

        // Validate or create destination path
        if (!$this->path) {
            throw new \Exception("Destination path is not provided.");
        }

        if (!is_dir($this->path)) {
            if ($createPath) {
                if (!mkdir($this->path, 0755, true) && !is_dir($this->path)) {
                    throw new \Exception("Failed to create destination directory: {$this->path}");
                }
            } else {
                throw new \Exception("Destination path does not exist: {$this->path}");
            }
        }

        // Determine file extension
        $fileExtension = strtolower(pathinfo($this->file['name'] ?? $this->file_path, PATHINFO_EXTENSION));

        // Extract based on file type
        switch ($fileExtension) {
            case 'zip':
                return $this->extractZip($archiveFilePath);
            case 'gz':
                return $this->extractTarGz($archiveFilePath);
            default:
                throw new \Exception("Unsupported archive type: .$fileExtension");
        }
    }

    /**
     * Extracts a ZIP archive.
     *
     * @param string $filePath Path to the ZIP file.
     * @return bool True if extraction is successful.
     * @throws Exception If extraction fails.
     */
    private function extractZip($filePath) {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \Exception("Failed to open the ZIP file.");
        }

        if ($zip->extractTo($this->path)) {
            $zip->close();
            return true;
        } else {
            $zip->close();
            throw new \Exception("Failed to extract the ZIP file.");
        }
    }

    /**
     * Extracts a TAR.GZ archive.
     *
     * @param string $filePath Path to the TAR.GZ file.
     * @return bool True if extraction is successful.
     * @throws Exception If extraction fails.
     */
    private function extractTarGz($filePath) {
        if (!class_exists('PharData')) {
            throw new \Exception("TAR.GZ extraction requires the 'PharData' PHP extension.");
        }

        try {
            $phar = new \PharData($filePath);
            $phar->decompress(); // Converts .tar.gz to .tar

            $tarPath = str_replace('.gz', '', $filePath);
            $pharTar = new \PharData($tarPath);
            $pharTar->extractTo($this->path);

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to extract the TAR.GZ file: " . $e->getMessage());
        }
    }

    /**
     * Reads the contents of an archive file (ZIP, RAR, TAR, TAR.GZ) and optionally retrieves contents of specified files.
     * 
     * @param string|array|null $fileNames A single filename or an array of filenames to retrieve contents from the archive.
     * @return array Associative array with archive details and file contents (if requested).
     * @throws Exception If multiple files are provided, the archive file cannot be found, opened, or if there is an issue reading its contents.
     */
    public function readArchive($fileNames = null) {
        if (isset($this->file['name']) && is_array($this->file['name'])) {
            throw new \Exception("Only a single archive file can be processed at a time for reading.");
        }

        $uploadedFile = null;
        if ($this->file && !empty($this->file)) {
            $uploadedFile = $this->normalizeFiles($this->file)[0];
        }

        $archivePath = $uploadedFile ? $uploadedFile['tmp_name'] : $this->file_path;

        if (!$archivePath || !file_exists($archivePath)) {
            throw new \Exception("Archive file not found.");
        }

        $fileNames = $fileNames ? (array)$fileNames : [];
        $extension = strtolower(pathinfo($uploadedFile['name'] ?? $this->file_path, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'zip':
                return $this->readZipArchive($archivePath, $fileNames);
            case 'tar':
            case 'gz':
                return $this->readTarArchive($archivePath, $fileNames);
            default:
                throw new \Exception("Unsupported archive format: ." . $extension);
        }
    }

    /**
     * Handles ZIP archives using ZipArchive and retrieves specified file contents.
     */
    private function readZipArchive($archivePath, $fileNames) {
        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new \Exception("Unable to open ZIP file: " . $archivePath);
        }

        $fileContents = [];
        $fileNamesInArchive = [];
        $dirNames = [];
        $containsFile = false;
        $containsDir = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            $entryWithoutFileName = trim(preg_replace('/[^\/]+\.[a-zA-Z0-9]+$/', '', $entryName));

            if (!empty($entryWithoutFileName)) {
                if (!in_array($entryWithoutFileName, $dirNames)) {
                    $dirNames[] = $entryWithoutFileName;
                }
                $containsDir = true;
            }

            if (preg_match('/\.[a-zA-Z0-9]+$/', $entryName)) {
                $fileNamesInArchive[] = $entryName;
                $containsFile = true;

                if (in_array($entryName, $fileNames)) {
                    $fileContents[$entryName] = $zip->getFromName($entryName);
                }
            }
        }

        $zip->close();

        return $this->formatArchiveResult($fileNamesInArchive, $dirNames, $containsFile, $containsDir, $fileContents);
    }

    /**
     * Handles TAR and TAR.GZ archives using PharData and retrieves specified file contents.
     */
    private function readTarArchive($archivePath, $fileNames) {
        if (!class_exists('PharData')) {
            throw new \Exception("TAR extraction requires the 'PharData' PHP extension.");
        }

        $tar = new \PharData($archivePath);

        $fileContents = [];
        $fileNamesInArchive = [];
        $dirNames = [];
        $containsFile = false;
        $containsDir = false;

        foreach (new \RecursiveIteratorIterator($tar) as $file) {
            $entryName = $file->getPathname();

            $entryWithoutFileName = trim(preg_replace('/[^\/]+\.[a-zA-Z0-9]+$/', '', $entryName));

            if (!empty($entryWithoutFileName)) {
                if (!in_array($entryWithoutFileName, $dirNames)) {
                    $dirNames[] = $entryWithoutFileName;
                }
                $containsDir = true;
            }

            if (preg_match('/\.[a-zA-Z0-9]+$/', $entryName)) {
                $fileNamesInArchive[] = $entryName;
                $containsFile = true;

                if (in_array($entryName, $fileNames)) {
                    $fileContents[$entryName] = file_get_contents($file->getPathname());
                }
            }
        }

        return $this->formatArchiveResult($fileNamesInArchive, $dirNames, $containsFile, $containsDir, $fileContents);
    }

    /**
     * Formats the archive result into a structured array, including file contents and metadata.
     *
     * @param array $fileNames List of file names in the archive.
     * @param array $dirNames List of directory names in the archive (with paths).
     * @param bool $containsFile Whether the archive contains files.
     * @param bool $containsDir Whether the archive contains directories.
     * @param array $fileContents Contents of requested files in the archive.
     * @return array Structured array with file and directory metadata.
     */
    private function formatArchiveResult($fileNames, $dirNames, $containsFile, $containsDir, $fileContents) {
        $childFileNames = [];
        $childDirNames = [];

        // Identify root-level files (files whose path does not have a sub-directory)
        foreach ($fileNames as $fileName) {
            $parts = explode('/', $fileName);
            if (count($parts) === 1) {
                $childFileNames[] = $fileName; // Root-level file (no directory part)
            }
        }

        // Identify root-level directories (directories that have subdirectories or files)
        foreach ($dirNames as $dirName) {
            $parts = explode('/', rtrim($dirName, '/'));

            // The first part is always the root directory
            $rootDir = $parts[0] . '/';

            // Add it to the root-level directories list if not already present
            if (!in_array($rootDir, $childDirNames)) {
                $childDirNames[] = $rootDir;
            }
        }

        return [
            'file_names' => $fileNames,
            'dir_names' => $dirNames,
            'child_file_names' => $childFileNames,
            'child_dir_names' => $childDirNames,
            'contains_file' => $containsFile,
            'contains_dir' => $containsDir,
            'file_count' => count($fileNames),
            'dir_count' => count($dirNames),
            'child_file_count' => count($childFileNames),
            'child_dir_count' => count($childDirNames),
            'file_contents' => count($fileContents) === 1 ? reset($fileContents) : $fileContents,
        ];
    }

    /**
     * Compresses a file or multiple files using gzip to reduce their size.
     *
     * If $file is provided, it compresses the uploaded file(s). 
     * If $file is not provided, it uses the file at $file_path.
     *
     * @return array|string The path(s) of the compressed file(s).
     * @throws Exception If no valid file is provided or compression fails.
     */
    public function compress() {
        // Check if $file is provided
        if ($this->file) {
            // Normalize the files for consistency (handling both single and multiple file uploads)
            $files = $this->normalizeFiles($this->file);
            $compressedFiles = [];

            foreach ($files as $file) {
                $filePath = $file['tmp_name'];

                // Check if the file exists and is valid
                if (!$filePath || !file_exists($filePath)) {
                    throw new \Exception("No valid file provided for compression.");
                }

                // Compress the file and store its path in the results array
                $compressedFiles[] = $this->compressFile($filePath);
            }

            // Return an array of compressed file paths
            return $compressedFiles;
        }

        // Fallback to $file_path if no $file is provided
        if (!$this->file_path || !file_exists($this->file_path)) {
            throw new \Exception("No valid file path provided for compression.");
        }

        // Compress the single file from the $file_path
        return $this->compressFile($this->file_path);
    }

    /**
     * Compress a single file using gzip and save it in the defined `$path` property.
     *
     * @param string $filePath The path of the file to compress.
     * @return string The path of the compressed file.
     * @throws Exception If compression fails or `$path` is invalid.
     */
    private function compressFile(string $filePath): string {
        // Validate the $path property for correctness
        if (empty($this->path) || !is_dir($this->path) || !is_writable($this->path)) {
            throw new \Exception("Invalid or unwritable path defined in `\$this->path`.");
        }

        // Get the MIME type of the file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        // Warn if the file type is unsuitable for compression
        $unsuitableTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg', 'application/zip'];
        if (in_array($mime_type, $unsuitableTypes)) {
            error_log("Warning: The file type '$mime_type' may not compress effectively.");
        }

        // Determine the compressed file path using $this->path
        $fileName = basename($filePath) . '.gz';
        $compressedFilePath = rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        // Open the source file for reading
        $source = fopen($filePath, 'rb');
        if (!$source) {
            throw new \Exception("Failed to open source file for reading: $filePath");
        }

        // Open the compressed file for writing
        $compressed = gzopen($compressedFilePath, 'wb9'); // '9' means maximum compression level
        if (!$compressed) {
            fclose($source);
            throw new \Exception("Failed to open compressed file for writing: $compressedFilePath");
        }

        // Stream data from source to compressed file
        while (!feof($source)) {
            $buffer = fread($source, 1024 * 512); // Read in 512KB chunks
            gzwrite($compressed, $buffer);
        }

        // Close file handles
        fclose($source);
        gzclose($compressed);

        // Return the path of the compressed file
        return $compressedFilePath;
    }

    /**
     * Fetches the file metadata.
     *
     * @return array Metadata of the file.
     * @throws Exception If the file path is invalid.
     */
    public function fetch() {
        if (!$this->file_path || !file_exists($this->file_path)) {
            throw new \Exception("File not found.");
        }

        return [
            "name" => basename($this->file_path),
            "size" => filesize($this->file_path),
            "type" => mime_content_type($this->file_path),
            "path" => $this->file_path
        ];
    }

    /**
     * Resets the properties of the LCS_FileManager instance to their default values.
     *
     * This method can be used to clear the current state of the instance, making it ready for new operations
     * without needing to create a new instance.
     */
    public function resetProperties() {
        $this->file = null;
        $this->file_name = null;
        $this->file_path = null;
        $this->path = null;
        $this->file_limit = 10;
        $this->time_limit = 0;
        $this->rename = true;
    }

    /**
     * Destructor for LCS_FileManager class.
     *
     * This method is automatically called when the instance of the class is destroyed. It ensures that the PHP script
     * reverts back to the original maximum execution time limit, which was temporarily changed in the constructor.
     * 
     * The destructor helps to clean up any modifications made during the class's lifecycle, restoring the environment
     * to its previous state, specifically the execution time limit.
     */
    public function __destruct()
    {
        // Revert to the previous time limit
        set_time_limit($this->previous_time_limit);
    }

}