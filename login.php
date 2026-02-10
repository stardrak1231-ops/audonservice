<?php
/**
 * Member Login
 * หน้า Login สำหรับสมาชิก
 */

require_once 'config/database.php';
require_once 'config/session.php';

// ถ้า login อยู่แล้ว redirect ไปหน้า member
if (isMember()) {
    header('Location: member/index.php');
    exit;
}

$errors = [];
$phone = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($phone)) {
        $errors['phone'] = 'กรุณากรอกเบอร์โทรศัพท์';
    }

    if (empty($password)) {
        $errors['password'] = 'กรุณากรอกรหัสผ่าน';
    }

    // Verify credentials
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();

            // First check if phone exists (regardless of status)
            $stmt = $pdo->prepare("SELECT * FROM members WHERE phone = ?");
            $stmt->execute([$phone]);
            $member = $stmt->fetch();

            // Check if account is suspended
            if ($member && $member['status'] === 'suspended') {
                $errors['general'] = '❌ บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อทางร้าน โทร. 089-953-0201';
            }
            // Check password only for active accounts
            elseif ($member && $member['status'] === 'active') {
                if (password_verify($password, $member['password_hash'])) {
                    // Login success
                    loginMember($member);
                    header('Location: member/index.php');
                    exit;
                } else {
                    $errors['general'] = 'เบอร์โทรศัพท์หรือรหัสผ่านไม่ถูกต้อง';
                }
            } else {
                $errors['general'] = 'เบอร์โทรศัพท์หรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (Exception $e) {
            $errors['general'] = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ';
        }
    }
}

$pageTitle = 'เข้าสู่ระบบ';
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<main class="min-h-screen bg-gradient-to-br from-blue-50 to-white py-12">
    <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-8 py-10 text-center text-white">
                <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                        </path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold mb-2">เข้าสู่ระบบสมาชิก</h1>
                <p class="text-blue-100">ยินดีต้อนรับกลับมา!</p>
            </div>

            <!-- Form -->
            <form method="POST" class="p-8">
                <?php if (isset($errors['general'])): ?>
                    <?php if (strpos($errors['general'], 'ถูกระงับ') !== false): ?>
                        <!-- Suspended Account Message -->
                        <div
                            class="bg-gradient-to-r from-red-50 to-orange-50 border-l-4 border-red-500 rounded-lg p-5 mb-6 shadow-sm">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                                        </path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-red-800 font-semibold text-lg mb-1">บัญชีถูกระงับการใช้งาน</h3>
                                    <p class="text-red-600 text-sm mb-3">กรุณาติดต่อทางร้านเพื่อดำเนินการแก้ไข</p>
                                    <a href="tel:0899530201"
                                        class="inline-flex items-center gap-2 bg-white border-2 border-red-200 text-red-700 px-4 py-2 rounded-lg text-sm font-semibold pointer-events-none">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                            </path>
                                        </svg>
                                        089-953-0201
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Normal Error Message -->
                        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <?php echo htmlspecialchars($errors['general']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Phone -->
                <div class="mb-6">
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                        เบอร์โทรศัพท์
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                </path>
                            </svg>
                        </div>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>"
                            class="w-full pl-12 pr-4 py-3 rounded-lg border <?php echo isset($errors['phone']) ? 'border-red-500 bg-red-50' : 'border-gray-300'; ?> focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="0812345678">
                    </div>
                    <?php if (isset($errors['phone'])): ?>
                        <p class="mt-1 text-sm text-red-500">
                            <?php echo $errors['phone']; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        รหัสผ่าน
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
                            placeholder="••••••••">
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <p class="mt-1 text-sm text-red-500">
                            <?php echo $errors['password']; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-xl font-semibold text-lg transition-all transform hover:scale-[1.02] shadow-lg hover:shadow-xl flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                        </path>
                    </svg>
                    เข้าสู่ระบบ
                </button>

                <!-- Register Link -->
                <p class="text-center text-gray-600 mt-6">
                    ยังไม่มีบัญชี?
                    <a href="register.php" class="text-blue-600 hover:text-blue-800 font-semibold">สมัครสมาชิก</a>
                </p>
            </form>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>