<?php
session_start();
require_once __DIR__ . '/includes/auth.php';

// Check authentication
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

// Configuration
$user = $_SESSION['user'];
$uploadDir = "users/$user/";
$apiDir = "users/$user/api/";
$limitMB = 2; // 2MB storage limit

// Check if user is blocked
if (file_exists("users/$user/BLOCKED")) {
    session_unset();
    session_destroy();
    header("Location: index.php?error=blocked");
    exit;
}

// Calculate storage usage
function getDirectorySize($path) {
    $size = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
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
    $usedSize = getDirectorySize($uploadDir) + getDirectorySize($apiDir);
    $usedMB = round($usedSize / (1024 * 1024), 2);
    $percentage = min(round(($usedMB / $limitMB) * 100), 100);
    $apiUrl = "https://".$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME'])."/users/$user/api/";
} catch (Exception $e) {
    die("Failed to calculate storage usage: " . $e->getMessage());
}

// Handle messages
$message = '';
$messageClass = '';
if (isset($_GET['success'])) {
    $message = htmlspecialchars($_GET['success']);
    $messageClass = 'alert-success';
} elseif (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $messageClass = 'alert-danger';
} elseif (isset($_GET['upload']) && $_GET['upload'] === 'success') {
    $message = 'File uploaded successfully!';
    $messageClass = 'alert-success';
}

// Get file list
function getFileList($dir, $relativePath = '') {
    $files = [];
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . $item;
        $relative = $relativePath . $item;
        
        if (is_dir($path)) {
            $files = array_merge($files, getFileList($path . '/', $relative . '/'));
        } else {
            $files[] = [
                'path' => $path,
                'relative' => $relative,
                'name' => $item,
                'size' => filesize($path),
                'modified' => filemtime($path),
                'is_api' => strpos($path, '/api/') !== false
            ];
        }
    }
    
    return $files;
}

$fileList = array_merge(
    getFileList($uploadDir),
    getFileList($apiDir)
);

// Sort files by modification time (newest first)
usort($fileList, function($a, $b) {
    return $b['modified'] - $a['modified'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Free Hosting</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .storage-info {
            background: linear-gradient(135deg, #f5f7fa, #e4e8ed);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .storage-info h3 {
            margin-top: 0;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .storage-bar-container {
            margin-top: 15px;
        }
        
        .storage-bar {
            height: 20px;
            background: #ecf0f1;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .storage-used {
            height: 100%;
            background: linear-gradient(90deg, #3498db, #2980b9);
            transition: width 0.6s ease;
        }
        
        .storage-details {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s ease;
        }
        
        .file-item:hover {
            background: #f8f9fa;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e3f2fd;
            border-radius: 8px;
            margin-right: 15px;
            color: #1976d2;
            font-size: 18px;
        }
        
        .file-info {
            flex: 1;
            min-width: 0;
        }
        
        .file-name {
            font-weight: 500;
            color: #2c3e50;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .file-meta {
            display: flex;
            margin-top: 5px;
            font-size: 13px;
            color: #7f8c8d;
        }
        
        .file-size {
            margin-right: 15px;
        }
        
        .file-date {
            margin-right: 15px;
        }
        
        .file-type {
            background: #e0e0e0;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-view {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-view:hover {
            background: #bbdefb;
        }
        
        .btn-download {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .btn-download:hover {
            background: #c8e6c9;
        }
        
        .btn-delete {
            background: #ffebee;
            color: #d32f2f;
        }
        
        .btn-delete:hover {
            background: #ffcdd2;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-icon {
            font-size: 60px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .empty-text {
            color: #7f8c8d;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .api-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 8px;
        }
        
        @media (max-width: 768px) {
            .file-item {
                flex-wrap: wrap;
            }
            
            .file-actions {
                width: 100%;
                margin-top: 10px;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header card">
            <div>
                <h1 class="card-title">Welcome, <?= htmlspecialchars($user) ?></h1>
                <p style="color: var(--gray); margin-top: 8px;">
                    <i class="fas fa-link"></i> API Base URL: 
                    <code style="background: rgba(30, 136, 229, 0.1); padding: 4px 8px; border-radius: 4px;">
                        <?= htmlspecialchars($apiUrl) ?>
                    </code>
                </p>
            </div>
            <a href="includes/auth.php?logout=1" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= $messageClass ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Storage Info -->
        <div class="storage-info card">
            <h3>Storage Usage</h3>
            <div class="storage-bar-container">
                <div class="storage-bar">
                    <div class="storage-used" style="width: <?= $percentage ?>%"></div>
                </div>
                <div class="storage-details">
                    <span><?= $usedMB ?>MB of <?= $limitMB ?>MB used</span>
                    <span><?= $percentage ?>% full</span>
                </div>
            </div>
        </div>

        <!-- Upload Section -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Upload Files</h2>
            </div>
            <form id="uploadForm" method="POST" action="upload.php" enctype="multipart/form-data">
                <div class="file-upload">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary); margin-bottom: 12px;"></i>
                    <p style="margin-bottom: 16px;">Drag & drop files here or click to browse</p>
                    <input type="file" name="file" id="fileInput" required style="display: none;" onchange="updateFileName()">
                    <label for="fileInput" class="btn btn-outline" style="cursor: pointer;">
                        <i class="fas fa-folder-open"></i> Choose File
                    </label>
                    <div id="fileName" style="margin-top: 12px; font-size: 0.9rem; color: var(--gray);"></div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload File
                </button>
            </form>
        </div>

        <!-- File List -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title" style="margin: 0;">Your Files</h2>
                <button onclick="refreshFiles()" class="btn btn-sm btn-secondary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <div id="file-list">
                <?php if (empty($fileList)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div class="empty-text">
                            No files uploaded yet. Upload your first file to get started.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($fileList as $file): ?>
                        <div class="file-item">
                            <div class="file-icon">
                                <?php
                                $icon = 'fa-file';
                                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                
                                switch ($extension) {
                                    case 'php':
                                    case 'html':
                                    case 'htm':
                                    case 'js':
                                    case 'css':
                                        $icon = 'fa-file-code';
                                        break;
                                    case 'jpg':
                                    case 'jpeg':
                                    case 'png':
                                    case 'gif':
                                    case 'svg':
                                        $icon = 'fa-file-image';
                                        break;
                                    case 'pdf':
                                        $icon = 'fa-file-pdf';
                                        break;
                                    case 'zip':
                                    case 'rar':
                                    case 'tar':
                                    case 'gz':
                                        $icon = 'fa-file-archive';
                                        break;
                                    case 'txt':
                                    case 'md':
                                        $icon = 'fa-file-alt';
                                        break;
                                    case 'json':
                                        $icon = 'fa-file-code';
                                        break;
                                }
                                ?>
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                            
                            <div class="file-info">
                                <div class="file-name">
                                    <?= htmlspecialchars($file['name']) ?>
                                    <?php if ($file['is_api']): ?>
                                        <span class="api-badge">API</span>
                                    <?php endif; ?>
                                </div>
                                <div class="file-meta">
                                    <span class="file-size">
                                        <?= formatFileSize($file['size']) ?>
                                    </span>
                                    <span class="file-date">
                                        <?= date('M d, Y H:i', $file['modified']) ?>
                                    </span>
                                    <span class="file-type">
                                        <?= strtoupper($extension) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="file-actions">
                                <a href="<?= htmlspecialchars($file['path']) ?>" target="_blank" class="btn-icon btn-view" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= htmlspecialchars($file['path']) ?>" download class="btn-icon btn-download" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <a href="delete_file.php?file=<?= urlencode($file['path']) ?>" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this file?')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Update file name display
        function updateFileName() {
            const fileInput = document.getElementById('fileInput');
            const fileNameDisplay = document.getElementById('fileName');
            
            if (fileInput.files.length > 0) {
                fileNameDisplay.textContent = 'Selected: ' + fileInput.files[0].name;
            } else {
                fileNameDisplay.textContent = '';
            }
        }
        
        // Refresh file list
        function refreshFiles() {
            location.reload();
        }
        
        // Drag and drop functionality
        const fileUpload = document.querySelector('.file-upload');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUpload.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            fileUpload.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileUpload.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            fileUpload.style.borderColor = '#1e88e5';
            fileUpload.style.backgroundColor = 'rgba(30, 136, 229, 0.05)';
        }
        
        function unhighlight() {
            fileUpload.style.borderColor = '#e0e0e0';
            fileUpload.style.backgroundColor = 'transparent';
        }
        
        fileUpload.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            const fileInput = document.getElementById('fileInput');
            
            if (files.length) {
                fileInput.files = files;
                updateFileName();
            }
        }
        
        // AJAX file upload
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            submitBtn.disabled = true;
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Show success message
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'alert alert-success';
                    messageDiv.textContent = data.message;
                    document.querySelector('.container').prepend(messageDiv);
                    
                    // Refresh file list
                    refreshFiles();
                } else {
                    // Show error message
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'alert alert-danger';
                    messageDiv.textContent = data.message;
                    document.querySelector('.container').prepend(messageDiv);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const messageDiv = document.createElement('div');
                messageDiv.className = 'alert alert-danger';
                messageDiv.textContent = 'An error occurred during file upload';
                document.querySelector('.container').prepend(messageDiv);
            })
            .finally(() => {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                form.reset();
                document.getElementById('fileName').textContent = '';
            });
        });
    </script>
</body>
</html>
<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>