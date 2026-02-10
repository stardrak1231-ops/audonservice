<?php
/**
 * สมัครสมาชิก - Member Registration
 * ระบบจัดการอู่ซ่อมรถ
 */

require_once 'config/database.php';

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($firstName)) {
        $errors['first_name'] = 'กรุณากรอกชื่อ';
    }

    if (empty($lastName)) {
        $errors['last_name'] = 'กรุณากรอกนามสกุล';
    }

    if (empty($phone)) {
        $errors['phone'] = 'กรุณากรอกเบอร์โทรศัพท์';
    } elseif (!preg_match('/^[0-9]{9,10}$/', $phone)) {
        $errors['phone'] = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }

    if (empty($password)) {
        $errors['password'] = 'กรุณากรอกรหัสผ่าน';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    }

    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'รหัสผ่านไม่ตรงกัน';
    }

    // Check if phone already exists
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT member_id FROM members WHERE phone = ?");
            $stmt->execute([$phone]);

            if ($stmt->fetch()) {
                $errors['phone'] = 'เบอร์โทรศัพท์นี้ถูกใช้งานแล้ว';
            }
        } catch (Exception $e) {
            $errors['general'] = 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูล';
        }
    }

    // Insert new member
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();

            // Generate member code
            $memberCode = 'M' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO members (member_code, first_name, last_name, phone, email, password_hash, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
            ");

            $stmt->execute([$memberCode, $firstName, $lastName, $phone, $email ?: null, $passwordHash]);
            $success = true;

        } catch (Exception $e) {
            $errors['general'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'สมัครสมาชิก';
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<main class="min-h-screen bg-gradient-to-br from-blue-50 to-white py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

        <?php if ($success): ?>
            <!-- Success Modal Overlay -->
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-2xl p-8 text-center max-w-md w-full animate-fade-in">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-4">สมัครสมาชิกสำเร็จ!</h1>
                    <p class="text-gray-600 mb-6">
                        ขอบคุณที่สมัครสมาชิกกับเรา คุณสามารถเข้าสู่ระบบเพื่อใช้บริการได้ทันที
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="login.php"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors inline-flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                                </path>
                            </svg>
                            เข้าสู่ระบบ
                        </a>
                        <a href="index.php"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                            กลับหน้าแรก
                        </a>
                    </div>
                </div>
            </div>

            <style>
                @keyframes fade-in {
                    from {
                        opacity: 0;
                        transform: scale(0.95);
                    }

                    to {
                        opacity: 1;
                        transform: scale(1);
                    }
                }

                .animate-fade-in {
                    animation: fade-in 0.3s ease-out;
                }
            </style>
        <?php else: ?>
            <!-- Registration Form -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-8 py-10 text-center text-white">
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                            </path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold mb-2">สมัครสมาชิก</h1>
                    <p class="text-blue-100">เข้าร่วมกับเราเพื่อรับสิทธิประโยชน์มากมาย</p>
                </div>

                <!-- Form -->
                <form method="POST" class="p-8">
                    <?php if (isset($errors['general'])): ?>
                        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <?php echo htmlspecialchars($errors['general']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- First Name -->
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                ชื่อ <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="first_name" name="first_name"
                                value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                class="w-full px-4 py-3 rounded-lg border <?php echo isset($errors['first_name']) ? 'border-red-500 bg-red-50' : 'border-gray-300'; ?> focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="กรอกชื่อ">
                            <?php if (isset($errors['first_name'])): ?>
                                <p class="mt-1 text-sm text-red-500"><?php echo $errors['first_name']; ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Last Name -->
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                นามสกุล <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="last_name" name="last_name"
                                value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                class="w-full px-4 py-3 rounded-lg border <?php echo isset($errors['last_name']) ? 'border-red-500 bg-red-50' : 'border-gray-300'; ?> focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="กรอกนามสกุล">
                            <?php if (isset($errors['last_name'])): ?>
                                <p class="mt-1 text-sm text-red-500"><?php echo $errors['last_name']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="mt-6">
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            เบอร์โทรศัพท์ <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                    </path>
                                </svg>
                            </div>
                            <input type="tel" id="phone" name="phone"
                                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                class="w-full pl-12 pr-4 py-3 rounded-lg border <?php echo isset($errors['phone']) ? 'border-red-500 bg-red-50' : 'border-gray-300'; ?> focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="0812345678">
                        </div>
                        <?php if (isset($errors['phone'])): ?>
                            <p class="mt-1 text-sm text-red-500"><?php echo $errors['phone']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Email -->
                    <div class="mt-6">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            อีเมล <span class="text-gray-400">(ไม่บังคับ)</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                    </path>
                                </svg>
                            </div>
                            <input type="email" id="email" name="email"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                class="w-full pl-12 pr-4 py-3 rounded-lg border <?php echo isset($errors['email']) ? 'border-red-500 bg-red-50' : 'border-gray-300'; ?> focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="example@email.com">
                        </div>
                        <?php if (isset($errors['email'])): ?>
                            <p class="mt-1 text-sm text-red-500"><?php echo $errors['email']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Password -->
                    <div class="mt-6">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            รหัสผ่าน <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                    </path>
                                </svg>
                            </div>
                            <input type="password" id="password" name="password"
                                class="w-full pl-12 pr-4 py-3 rounded-lg border <?php echo isset($errors['password']) ? 'border-red-500 bg-red-50' : 'border-gray-300'; ?> focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="อย่างน้อย 6 ตัวอักษร">
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <p class="mt-1 text-sm text-red-500"><?php echo $errors['password']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mt-6">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            ยืนยันรหัสผ่าน <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                    </path>
                                </svg>
                            </div>
                            <input type="password" id="confirm_password" name="confirm_password"
                                class="w-full pl-12 pr-4 py-3 rounded-lg border <?php echo isset($errors['confirm_password']) ? 'border-red-500 bg-red-50' : 'border-gray-300'; ?> focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="กรอกรหัสผ่านอีกครั้ง">
                        </div>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <p class="mt-1 text-sm text-red-500"><?php echo $errors['confirm_password']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-8">
                        <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-xl font-semibold text-lg transition-all transform hover:scale-[1.02] shadow-lg hover:shadow-xl flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                                </path>
                            </svg>
                            สมัครสมาชิก
                        </button>
                    </div>

                    <!-- Login Link -->
                    <p class="text-center text-gray-600 mt-6">
                        มีบัญชีอยู่แล้ว?
                        <a href="login.php" class="text-blue-600 hover:text-blue-800 font-semibold">เข้าสู่ระบบ</a>
                    </p>
                </form>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>