## **LCS_FileManager Class Documentation**

### **Overview**

The `LCS_FileManager` class is a powerful file management tool designed to handle various operations like uploading, copying, moving, deleting, compressing, extracting, and reading files and archives. It supports a variety of formats including ZIP, RAR, and TAR.GZ, and provides methods for metadata retrieval and file validation.

---

### **Properties**

1. **`$file`**
   - **Type:** `array|null`
   - **Description:** Stores uploaded file data from `$_FILES` array. Primarily used for uploading files.

2. **`$file_path`**
   - **Type:** `string|null`
   - **Description:** The direct path to the file on the system. This is used for operations like copying, moving, deleting, and compressing files that are already on the server.

3. **`$path`**
   - **Type:** `string|null`
   - **Description:** The target directory path for file operations (e.g., extraction, compression, or storage location).

4. **`$previous_time_limit`**
   - **Type:** `int`
   - **Description:** Stores the previous PHP script execution time limit, allowing restoration upon destruction of the class.

---

### **Methods**

1. **`upload`**
   - **Description:** Uploads multiple files to the specified directory based on their intended purpose.
   - **Parameters:**  
     - None (relies on the `$file` and `$path` properties)
   - **Returns:** `array` (Result of the upload operation, including success or failure for each file)
   - **Exceptions:** Throws `Exception` if upload fails or file validation issues occur.

2. **`copy`**
   - **Description:** Copies multiple files to a new directory. This operation is performed on files already on the system (using `file_path`).
   - **Parameters:**  
     - None (relies on the `$file_path` and `$path` properties)
   - **Returns:** `array` (Result of the copy operation, including success or failure for each file)
   - **Exceptions:** Throws `Exception` if copying fails.

3. **`move`**
   - **Description:** Moves multiple files to a new directory. This operation is performed on files already on the system (using `file_path`).
   - **Parameters:**  
     - None (relies on the `$file_path` and `$path` properties)
   - **Returns:** `array` (Result of the move operation, including success or failure for each file)
   - **Exceptions:** Throws `Exception` if moving fails.

4. **`delete`**
   - **Description:** Deletes multiple files. This operation is performed on files already on the system (using `file_path`).
   - **Parameters:**  
     - None (relies on the `$file_path` property)
   - **Returns:** `array` (Result of the delete operation, including success or failure for each file)
   - **Exceptions:** Throws `Exception` if deleting fails.

5. **`rename`**
   - **Description:** Renames a file.
   - **Parameters:**  
     - `string $new_name`: The new name for the file.
   - **Returns:** `string` (The new file name or path)
   - **Exceptions:** Throws `Exception` if renaming fails.

6. **`download`**
   - **Description:** Downloads multiple files as a ZIP archive or a single file directly.
   - **Parameters:**  
     - None (relies on `$file` and `$path` properties)
   - **Returns:** `bool` (True if download succeeds)
   - **Exceptions:** Throws `Exception` if download fails.

7. **`render`**
   - **Description:** Renders a file to the browser by setting appropriate headers and streaming the file content.
   - **Parameters:**  
     - None (relies on `$file` and `$file_path` properties)
   - **Returns:** `void`
   - **Exceptions:** Throws `Exception` if rendering fails.

8. **`zipData`**
   - **Description:** Creates a ZIP archive from files or directories.
   - **Parameters:**  
     - `string|array $data`: Files or directories to zip.
     - `string|null $zipFileName`: Optional custom name for the ZIP file.
     - `bool $returnData`: Whether to return binary data or save it as a file.
   - **Returns:** `string|false` (Path to the created ZIP file or binary data, `false` if operation fails)
   - **Exceptions:** Throws `Exception` if zipping fails.

9. **`unzipData`**
   - **Description:** Extracts an archive (ZIP, RAR, TAR.GZ) to the specified directory.
   - **Returns:** `bool` (True if extraction is successful)
   - **Exceptions:** Throws `Exception` if extraction fails, if multiple files are provided, or if the destination path is invalid.

10. **`compress`**
    - **Description:** Compresses a file or multiple files using gzip.
    - **Returns:** `array|string` (Compressed file paths)
    - **Exceptions:** Throws `Exception` if file validation or compression fails.

11. **`fetch`**
    - **Description:** Retrieves metadata of a specified file.
    - **Returns:** `array` (File metadata)
    - **Exceptions:** Throws `Exception` if the file path is invalid.

12. **`readArchive`**
    - **Description:** Reads the contents of an archive and retrieves contents of specified files.
    - **Parameters:**  
      - `string|array|null $fileNames`: Specific file(s) to extract content from the archive.
    - **Returns:** `array` (Contents of the archive and metadata)
    - **Exceptions:** Throws `Exception` if the archive cannot be processed.

13. **`compressFile`**
    - **Description:** Compresses a single file using gzip.
    - **Parameters:**  
      - `string $filePath`: Path to the file to compress.
    - **Returns:** `string` (Path of the compressed file)
    - **Exceptions:** Throws `Exception` if compression fails.

14. **`__destruct`**
    - **Description:** Resets the PHP script execution time limit to its original value.

---

### **Private Methods**

1. **`extractZip`**
   - **Description:** Extracts a ZIP archive.
   - **Parameters:**  
     - `string $filePath`: Path to the ZIP file.
   - **Returns:** `bool` (True if extraction is successful)
   - **Exceptions:** Throws `Exception` if extraction fails.

2. **`extractRar`**
   - **Description:** Extracts a RAR archive.
   - **Parameters:**  
     - `string $filePath`: Path to the RAR file.
   - **Returns:** `bool` (True if extraction is successful)
   - **Exceptions:** Throws `Exception` if the PHP `RarArchive` extension is missing or extraction fails.

3. **`extractTarGz`**
   - **Description:** Extracts a TAR.GZ archive.
   - **Parameters:**  
     - `string $filePath`: Path to the TAR.GZ file.
   - **Returns:** `bool` (True if extraction is successful)
   - **Exceptions:** Throws `Exception` if the PHP `PharData` extension is missing or extraction fails.

4. **`readZipArchive`**
   - **Description:** Reads contents from a ZIP archive.
   - **Parameters:**  
     - `string $archivePath`: Path to the ZIP file.
     - `array $fileNames`: Files to retrieve from the archive.
   - **Returns:** `array` (File and directory metadata)
   - **Exceptions:** Throws `Exception` if the ZIP cannot be opened.

5. **`readRarArchive`**
   - **Description:** Reads contents from a RAR archive.
   - **Parameters:**  
     - `string $archivePath`: Path to the RAR file.
     - `array $fileNames`: Files to retrieve from the archive.
   - **Returns:** `array` (File and directory metadata)
   - **Exceptions:** Throws `Exception` if the RAR cannot be opened.

6. **`readTarArchive`**
   - **Description:** Reads contents from a TAR.GZ archive.
   - **Parameters:**  
     - `string $archivePath`: Path to the TAR.GZ file.
     - `array $fileNames`: Files to retrieve from the archive.
   - **Returns:** `array` (File and directory metadata)
   - **Exceptions:** Throws `Exception` if the TAR.GZ cannot be opened.

7. **`compressFile`**
   - **Description:** Compresses a single file using gzip.
   - **Parameters:**  
     - `string $filePath`: Path to the file to compress.
   - **Returns:** `string` (Path of the compressed file)
   - **Exceptions:** Throws `Exception` if compression fails.

8. **`formatArchiveResult`**
   - **Description:** Formats archive metadata for unified output.
   - **Parameters:**  
     - `array $fileNames`: List of file names.
     - `array $dirNames`: List of directory names.
     - `array $dataNames`: All entries.
     - `bool $containsFile`: Whether files are present.
     - `bool $containsDir`: Whether directories are present.
     - `array $fileContents`: Contents of specific files.
   - **Returns:** `array` (Structured archive data)

---

### **Usage Example**

```php
$fileManager = new LCS_FileManager();

// Example: Upload files
$fileManager->file = $_FILES['fileToUpload']; // Assuming a file is uploaded via a form
$fileManager->path = '/path/to/upload/directory/';
$uploadResult = $fileManager->upload();
echo "Upload Result: ";
print_r($uploadResult);

// Example: Copy files (using file_path instead of $_FILES)
$fileManager->file_path = '/path/to/fileToCopy.txt'; // The file already exists on the system
$fileManager->path = '/path/to/destination/directory/';
$copyResult = $fileManager->copy();
echo "Copy Result: ";
print_r($copyResult);

// Example: Move files (using file_path instead of $_FILES)
$fileManager->file_path = '/path/to/fileToMove.txt'; // The file already exists on the system
$fileManager->path = '/path/to/new/location/';
$moveResult = $fileManager->move();
echo "Move Result: ";
print_r($moveResult);

// Example: Rename file (using file_path instead of $_FILES)
$fileManager->file_path = '/path/to/fileToRename.txt'; // The file already exists on the system
$newName = 'newFileName.txt';
$renameResult = $fileManager->rename($newName);
echo "File Renamed to: $renameResult";

// Example: Delete files (using file_path instead of $_FILES)
$fileManager->file_path = '/path/to/fileToDelete.txt'; // The file already exists on the system
$deleteResult = $fileManager->delete();
echo "Delete Result: ";
print_r($deleteResult);

// Example: Download files as a ZIP (using file_path instead of $_FILES)
$fileManager->file_path = '/path/to/fileToDownload.txt'; // The file already exists on the system
$fileManager->path = '/path/to/download/directory/';
$downloadResult = $fileManager->download();
echo "Download Result: " . ($downloadResult ? 'Success' : 'Failure');

// Example: Render file to browser (using file_path instead of $_FILES)
$fileManager->file_path = '/path/to/render/file.txt';
$fileManager->render();  // This will stream the file to the browser

// Example: Zip multiple files
$zipPath = $fileManager->zipData(['/path/to/file1.txt', '/path/to/file2.txt'], 'archive.zip');
echo "ZIP created at: $zipPath";

// Example: Unzip files
$fileManager->file_path = '/path/to/archive.zip';
$fileManager->path = '/path/to/extracted/';
$fileManager->unzipData();

// Example: Read Archive Contents
$contents = $fileManager->readArchive();
echo "Archive Contents: ";
print_r($contents);

// Example: Compress file
$fileManager->file_path = '/path/to/file.txt';
$fileManager->path = '/path/to/store/';
$compressedPath = $fileManager->compress();
echo "File compressed to: $compressedPath";

// Example: Fetch file metadata
$fileManager->file_path = '/path/to/file.txt';
$metadata = $fileManager->fetch();
echo "File Metadata: ";
print_r($metadata);
```

---

### **Dependencies**
- `ZipArchive` (PHP extension)
- `RarArchive` (PHP extension, optional)
- `PharData` (PHP extension)

---

### **Error Handling**
- Exceptions are thrown for invalid files, paths, unsupported formats, or PHP extension issues.
- Warnings are logged for non-critical errors.