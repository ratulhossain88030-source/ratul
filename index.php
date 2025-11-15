<?php 
define('ADMIN_USERNAME', 'Admin');
define('ADMIN_PASSWORD', '123456');

session_start();

// Login Handler
if (isset($_POST['username']) && isset($_POST['password'])) {
    if ($_POST['username'] === ADMIN_USERNAME && $_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid admin credentials";
    }
}

// Logout Handler
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

// Block User Handler
if (isset($_GET['block'])) {
    $username = basename($_GET['block']);
    file_put_contents("../users/$username/BLOCKED", "Blocked by admin on ".date('Y-m-d H:i:s'));
    header("Location: index.php?action=blocked&user=$username");
    exit;
}

// Unblock User Handler
if (isset($_GET['unblock'])) {
    $username = basename($_GET['unblock']);
    if (file_exists("../users/$username/BLOCKED")) {
        unlink("../users/$username/BLOCKED");
    }
    header("Location: index.php?action=unblocked&user=$username");
    exit;
}

// Check Admin Auth
if (!isset($_SESSION['admin'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            padding: 20px;
        }
        
        .admin-login-container {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .admin-login-header {
            padding: 30px;
            text-align: center;
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            color: white;
        }
        
        .admin-login-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: white;
            background: rgba(255, 255, 255, 0.2);
            width: 80px;
            height: 80px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .admin-login-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .admin-login-body {
            padding: 30px;
        }
        
        .admin-form-group {
            margin-bottom: 20px;
        }
        
        .admin-form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .admin-form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .admin-form-control:focus {
            outline: none;
            border-color: #1e88e5;
            box-shadow: 0 0 0 3px rgba(30, 136, 229, 0.2);
        }
        
        .admin-login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .admin-login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(42, 82, 152, 0.4);
        }
        
        @media (max-width: 576px) {
            .admin-login-header {
                padding: 20px;
            }
            
            .admin-login-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-login-page">
        <div class="admin-login-container">
            <div class="admin-login-header">
                <i class="fas fa-lock"></i>
                <h2>Admin Panel</h2>
            </div>
            
            <div class="admin-login-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" style="margin-bottom: 20px;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="admin-form-group">
                        <label for="admin-username" class="admin-form-label">Username</label>
                        <input type="text" id="admin-username" name="username" class="admin-form-control" placeholder="Admin username" required>
                    </div>
                    
                    <div class="admin-form-group">
                        <label for="admin-password" class="admin-form-label">Password</label>
                        <input type="password" id="admin-password" name="password" class="admin-form-control" placeholder="Password" required>
                    </div>
                    
                    <button type="submit" class="admin-login-btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
<?php
exit;
}

// Get All Users
$users = [];
foreach (glob("../users/*") as $userDir) {
    if (is_dir($userDir)) {
        $username = basename($userDir);
        $isBlocked = file_exists("$userDir/BLOCKED");
        
        // Calculate storage usage
        $storageUsed = 0;
        $fileCount = 0;
        $lastActive = 0;
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($userDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $storageUsed += $file->getSize();
                $fileCount++;
                
                $fileMTime = $file->getMTime();
                if ($fileMTime > $lastActive) {
                    $lastActive = $fileMTime;
                }
            }
        }
        
        $users[] = [
            'username' => $username,
            'files' => $fileCount,
            'storage' => round($storageUsed / (1024 * 1024), 2),
            'last_active' => $lastActive ? date("F d, Y H:i", $lastActive) : 'Never',
            'blocked' => $isBlocked
        ];
    }
}

// Sort users by last active (newest first)
usort($users, function($a, $b) {
    return $b['last_active'] <=> $a['last_active'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-dashboard {
            min-height: 100vh;
            background: #f5f7fa;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .admin-header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .admin-header-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-header-title i {
            font-size: 1.8rem;
        }
        
        .admin-logout-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .admin-logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .admin-stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .admin-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .admin-stat-title {
            font-size: 1rem;
            color: #7f8c8d;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .admin-stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }
        
        .admin-stat-details {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 10px;
        }
        
        .admin-users-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .admin-users-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .admin-users-title {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .admin-users-search {
            display: flex;
            align-items: center;
            background: #f5f7fa;
            border-radius: 6px;
            padding: 8px 15px;
            width: 300px;
        }
        
        .admin-users-search input {
            border: none;
            background: transparent;
            padding: 5px;
            width: 100%;
            outline: none;
        }
        
        .admin-users-search i {
            color: #7f8c8d;
            margin-right: 10px;
        }
        
        .admin-users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-users-table th {
            background: #f5f7fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
        }
        
        .admin-users-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .admin-user-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .admin-user-status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .admin-user-status-blocked {
            background: #ffebee;
            color: #c62828;
        }
        
        .admin-user-actions {
            display: flex;
            gap: 10px;
        }
        
        .admin-user-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .admin-user-btn-block {
            background: #ffebee;
            color: #c62828;
        }
        
        .admin-user-btn-block:hover {
            background: #ffcdd2;
        }
        
        .admin-user-btn-unblock {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .admin-user-btn-unblock:hover {
            background: #c8e6c9;
        }
        
        .admin-user-btn-view {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .admin-user-btn-view:hover {
            background: #bbdefb;
        }
        
        .admin-alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .admin-alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .admin-alert-danger {
            background: #ffebee;
            color: #c62828;
        }
        
        @media (max-width: 768px) {
            .admin-users-search {
                width: 200px;
            }
            
            .admin-users-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="admin-dashboard">
        <header class="admin-header">
            <div class="admin-header-container">
                <h1 class="admin-header-title">
                    <i class="fas fa-cog"></i> Admin Dashboard
                </h1>
                <a href="?logout=1" class="admin-logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>
        
        <main class="admin-container">
            <?php if (isset($_GET['action'])): ?>
                <div class="admin-alert <?= $_GET['action'] === 'blocked' ? 'admin-alert-danger' : 'admin-alert-success' ?>">
                    User "<?= htmlspecialchars($_GET['user']) ?>" has been <?= $_GET['action'] ?> successfully.
                </div>
            <?php endif; ?>
            
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="admin-stat-title">Total Users</div>
                    <div class="admin-stat-value"><?= count($users) ?></div>
                    <div class="admin-stat-details">Managing all user accounts</div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="admin-stat-title">Active Users</div>
                    <div class="admin-stat-value">
                        <?= count(array_filter($users, function($user) { return !$user['blocked']; })) ?>
                    </div>
                    <div class="admin-stat-details">Currently using the service</div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="admin-stat-title">Blocked Users</div>
                    <div class="admin-stat-value">
                        <?= count(array_filter($users, function($user) { return $user['blocked']; })) ?>
                    </div>
                    <div class="admin-stat-details">Suspended accounts</div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="admin-stat-title">Total Storage Used</div>
                    <div class="admin-stat-value">
                        <?= array_reduce($users, function($carry, $user) { return $carry + $user['storage']; }, 0) ?>MB
                    </div>
                    <div class="admin-stat-details">Across all user accounts</div>
                </div>
            </div>
            
            <div class="admin-users-card">
                <div class="admin-users-header">
                    <h2 class="admin-users-title">User Management</h2>
                    <div class="admin-users-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="userSearch" placeholder="Search users...">
                    </div>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="admin-users-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Files</th>
                                <th>Storage Used</th>
                                <th>Last Active</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= $user['files'] ?></td>
                                    <td><?= $user['storage'] ?>MB</td>
                                    <td><?= $user['last_active'] ?></td>
                                    <td>
                                        <span class="admin-user-status <?= $user['blocked'] ? 'admin-user-status-blocked' : 'admin-user-status-active' ?>">
                                            <?= $user['blocked'] ? 'Blocked' : 'Active' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="admin-user-actions">
                                            <a href="../users/<?= htmlspecialchars($user['username']) ?>/" target="_blank" class="admin-user-btn admin-user-btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($user['blocked']): ?>
                                                <a href="?unblock=<?= urlencode($user['username']) ?>" class="admin-user-btn admin-user-btn-unblock">
                                                    <i class="fas fa-check"></i> Unblock
                                                </a>
                                            <?php else: ?>
                                                <a href="?block=<?= urlencode($user['username']) ?>" class="admin-user-btn admin-user-btn-block">
                                                    <i class="fas fa-ban"></i> Block
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // User search functionality
        document.getElementById('userSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.admin-users-table tbody tr');
            
            rows.forEach(row => {
                const username = row.cells[0].textContent.toLowerCase();
                if (username.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>