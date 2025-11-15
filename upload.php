<?php
session_start();

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Authentication check
if (!isset($_SESSION['user'])) {
    header("HTTP/1.1 401 Unauthorized");
    die(json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access',
        'code' => 401
    ]));
}

// Configuration
$user = $_SESSION['user'];
$uploadDir = "users/$user/";
$apiDir = "users/$user/api/";
$limitMB = 2; // 2MB storage limit
$allowedExtensions = ['php', 'html', 'htm', 'js', 'css', 'txt', 'json', 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'zip'];
$maxFileAge = 60 * 60 * 24 * 7; // 7 days in seconds

// Security checks
if (file_exists("users/$user/BLOCKED")) {
    header("HTTP/1.1 403 Forbidden");
    die(json_encode([
        'status' => 'error',
        'message' => 'Your account has been suspended',
        'code' => 403
    ]));
}

// Create directories if not exists
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        header("HTTP/1.1 500 Internal Server Error");
        die(json_encode([
            'status' => 'error',
            'message' => 'Failed to create user directory',
            'code' => 500
        ]));
    }
}

if (!file_exists($apiDir)) {
    if (!mkdir($apiDir, 0755, true)) {
        header("HTTP/1.1 500 Internal Server Error");
        die(json_encode([
            'status' => 'error',
            'message' => 'Failed to create API directory',
            'code' => 500
        ]));
    }
}

// Calculate current storage usage
function calculateDirectorySize($dir) {
    $size = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    
    return $size;
}

try {
    $usedSize = calculateDirectorySize($uploadDir) + calculateDirectorySize($apiDir);
    $usedMB = round($usedSize / (1024 * 1024), 2);
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    die(json_encode([
        'status' => 'error',
        'message' => 'Failed to calculate storage usage',
        'code' => 500,
        'details' => $e->getMessage()
    ]));
}

// Validate file upload
if (!isset($_FILES['file'])) {
    header("HTTP/1.1 400 Bad Request");
    die(json_encode([
        'status' => 'error',
        'message' => 'No file was uploaded',
        'code' => 400
    ]));
}

$file = $_FILES['file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    
    header("HTTP/1.1 400 Bad Request");
    die(json_encode([
        'status' => 'error',
        'message' => $errorMessages[$file['error']] ?? 'Unknown upload error',
        'code' => 400
    ]));
}

// Check storage limit
if (($file['size'] + $usedSize) > ($limitMB * 1024 * 1024)) {
    header("HTTP/1.1 403 Forbidden");
    die(json_encode([
        'status' => 'error',
        'message' => 'Storage limit exceeded (Max '.$limitMB.'MB)',
        'code' => 403,
        'used' => $usedMB,
        'limit' => $limitMB
    ]));
}

// Validate file name
$fileName = basename($file['name']);
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileExt, $allowedExtensions)) {
    header("HTTP/1.1 400 Bad Request");
    die(json_encode([
        'status' => 'error',
        'message' => 'File type not allowed',
        'code' => 400,
        'allowed_extensions' => $allowedExtensions
    ]));
}

// Sanitize file name
$fileName = preg_replace("/[^a-zA-Z0-9\._-]/", "", $fileName);
$filePath = (in_array($fileExt, ['php', 'html', 'htm']) ? $apiDir : $uploadDir) . $fileName;

// Check if file already exists
if (file_exists($filePath)) {
    // Delete old file if it exists for more than max age
    if (filemtime($filePath) < (time() - $maxFileAge)) {
        if (!unlink($filePath)) {
            header("HTTP/1.1 500 Internal Server Error");
            die(json_encode([
                'status' => 'error',
                'message' => 'Failed to replace old file',
                'code' => 500
            ]));
        }
    } else {
        header("HTTP/1.1 409 Conflict");
        die(json_encode([
            'status' => 'error',
            'message' => 'File already exists',
            'code' => 409
        ]));
    }
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    header("HTTP/1.1 500 Internal Server Error");
    die(json_encode([
        'status' => 'error',
        'message' => 'Failed to move uploaded file',
        'code' => 500
    ]));
}

// Set proper permissions
chmod($filePath, 0644);

// Log the activity
$logMessage = sprintf(
    "[%s] %s uploaded %s (%s bytes)\n",
    date('Y-m-d H:i:s'),
    $user,
    $fileName,
    filesize($filePath)
);

file_put_contents("logs/activity.log", $logMessage, FILE_APPEND);

// Return success response
header("Content-Type: application/json");
echo json_encode([
    'status' => 'success',
    'message' => 'File uploaded successfully',
    'data' => [
        'filename' => $fileName,
        'path' => $filePath,
        'size' => filesize($filePath),
        'url' => "https://".$_SERVER['HTTP_HOST'].'/'.ltrim($filePath, '/')
    ]
]);
?>