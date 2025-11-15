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
$fileToDelete = isset($_GET['file']) ? urldecode($_GET['file']) : '';

// Validate input
if (empty($fileToDelete)) {
    header("HTTP/1.1 400 Bad Request");
    die(json_encode([
        'status' => 'error',
        'message' => 'No file specified for deletion',
        'code' => 400
    ]));
}

// Security verification - user can only delete their own files
$userDir = realpath("users/$user");
$filePath = realpath($fileToDelete);

if ($filePath === false || strpos($filePath, $userDir) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    die(json_encode([
        'status' => 'error',
        'message' => 'You can only delete your own files',
        'code' => 403
    ]));
}

// Check if file exists
if (!file_exists($filePath)) {
    header("HTTP/1.1 404 Not Found");
    die(json_encode([
        'status' => 'error',
        'message' => 'File not found',
        'code' => 404
    ]));
}

// Check if it's a directory
if (is_dir($filePath)) {
    header("HTTP/1.1 400 Bad Request");
    die(json_encode([
        'status' => 'error',
        'message' => 'Directories cannot be deleted this way',
        'code' => 400
    ]));
}

// Attempt to delete the file
if (!unlink($filePath)) {
    header("HTTP/1.1 500 Internal Server Error");
    die(json_encode([
        'status' => 'error',
        'message' => 'Failed to delete file',
        'code' => 500
    ]));
}

// Log the activity
$logMessage = sprintf(
    "[%s] %s deleted %s\n",
    date('Y-m-d H:i:s'),
    $user,
    basename($filePath)
);

file_put_contents("logs/activity.log", $logMessage, FILE_APPEND);

// Return success response
header("Content-Type: application/json");
echo json_encode([
    'status' => 'success',
    'message' => 'File deleted successfully',
    'data' => [
        'filename' => basename($filePath),
        'deleted_at' => date('Y-m-d H:i:s')
    ]
]);
?>