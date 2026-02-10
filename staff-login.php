<?php
/**
 * Staff Login (Shared)
 * หน้า Login สำหรับพนักงานทุกตำแหน่ง
 */

require_once 'config/database.php';
require_once 'config/session.php';

// ถ้า login อยู่แล้ว redirect ตาม role
if (isStaff()) {
    $currentUser = getCurrentUser();
    if ($currentUser['role'] === 'technician') {
        header('Location: /model01/technician/');
    } elseif ($currentUser['role'] === 'accountant') {
        header('Location: /model01/accounting/');
    } else {
        header('Location: /model01/admin/');
    }
    exit;
}

$errors = [];
$username = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($username)) {
        $errors['username'] = 'กรุณากรอกชื่อผู้ใช้';
    }

    if (empty($password)) {
        $errors['password'] = 'กรุณากรอกรหัสผ่าน';
    }

    // Verify credentials
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();

            // First check if username exists (regardless of status)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Check if account is suspended
            if ($user && $user['status'] === 'suspended') {
                $errors['general'] = '❌ บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
            }
            // Check password only for active accounts
            elseif ($user && $user['status'] === 'active') {
                // ตรวจสอบ password (รองรับทั้ง hash และ plain text สำหรับ testing)
                $passwordValid = false;
                // ลองตรวจสอบแบบ hash ก่อน
                if (password_verify($password, $user['password_hash'])) {
                    $passwordValid = true;
                }
                // ถ้าไม่ผ่าน ลองตรวจสอบแบบ plain text (สำหรับ data เก่า)
                elseif ($password === $user['password_hash']) {
                    $passwordValid = true;
                }

                if ($passwordValid) {
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $updateStmt->execute([$user['user_id']]);

                    // Login success
                    loginUser($user);

                    // Redirect based on role
                    if ($user['role'] === 'technician') {
                        header('Location: /model01/technician/');
                    } elseif ($user['role'] === 'accountant') {
                        header('Location: /model01/accounting/');
                    } else {
                        header('Location: /model01/admin/');
                    }
                    exit;
                } else {
                    $errors['general'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                }
            } else {
                $errors['general'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (Exception $e) {
            $errors['general'] = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ';
        }
    }
}

$pageTitle = 'เข้าสู่ระบบพนักงาน';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $pageTitle; ?> | ระบบจัดการอู่ซ่อมรถ
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-gray-800 via-gray-900 to-black flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div
                class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                    </path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white">ระบบพนักงาน</h1>
            <p class="text-gray-400">อู่อุดร Service</p>
        </div>

        <!-- Login Form -->
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20">
            <h2 class="text-xl font-semibold text-white text-center mb-6">เข้าสู่ระบบ</h2>

            <form method="POST">
                <?php if (isset($errors['general'])): ?>
                    <div class="bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-lg mb-6 text-sm">
                        <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>

                <!-- Username -->
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-300 mb-2">ชื่อผู้ใช้</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                        placeholder="username">
                    <?php if (isset($errors['username'])): ?>
                        <p class="mt-1 text-sm text-red-400">
                            <?php echo $errors['username']; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-2">รหัสผ่าน</label>
                    <input type="password" id="password" name="password"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                        placeholder="••••••••">
                    <?php if (isset($errors['password'])): ?>
                        <p class="mt-1 text-sm text-red-400">
                            <?php echo $errors['password']; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Submit -->
                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-semibold transition-colors flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                        </path>
                    </svg>
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>

        <!-- Back to main site -->
        <p class="text-center text-gray-500 mt-6 text-sm">
            <a href="index.php" class="hover:text-white transition-colors">← กลับหน้าหลัก</a>
        </p>
    </div>

</body>

</html>