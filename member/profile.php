<?php
/**
 * Member Profile - จัดการข้อมูลส่วนตัว
 */

$pageTitle = 'ข้อมูลส่วนตัว';

// Handle form submissions before including header
require_once '../config/database.php';
require_once '../config/session.php';

requireMemberLogin();
$currentMember = getCurrentMember();
$pdo = getDBConnection();

// Refresh member data from DB
$memberStmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ?");
$memberStmt->execute([$currentMember['member_id']]);
$memberData = $memberStmt->fetch();

$success = '';
$errors = [];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($firstName))
            $errors[] = 'กรุณากรอกชื่อ';
        if (empty($lastName))
            $errors[] = 'กรุณากรอกนามสกุล';
        if (empty($phone))
            $errors[] = 'กรุณากรอกเบอร์โทร';

        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE members SET first_name = ?, last_name = ?, phone = ?, email = ? WHERE member_id = ?");
            $stmt->execute([$firstName, $lastName, $phone, $email, $currentMember['member_id']]);

            $_SESSION['member']['first_name'] = $firstName;
            $_SESSION['member']['last_name'] = $lastName;
            $_SESSION['member']['phone'] = $phone;
            $_SESSION['member']['email'] = $email;
            $currentMember = getCurrentMember();
            $memberData['first_name'] = $firstName;
            $memberData['last_name'] = $lastName;
            $memberData['phone'] = $phone;
            $memberData['email'] = $email;

            $success = 'อัปเดตข้อมูลเรียบร้อย';
        }
    }

    if ($action === 'upload_photo') {
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['profile_image']['type'];

            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = 'รองรับเฉพาะไฟล์ JPG, PNG, GIF, WEBP';
            } else {
                // Create uploads directory if not exists
                $uploadDir = '../uploads/profiles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Generate unique filename
                $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $filename = 'member_' . $currentMember['member_id'] . '_' . time() . '.' . $ext;
                $filepath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filepath)) {
                    // Delete old image if exists
                    if ($memberData['profile_image_url'] && file_exists('../' . $memberData['profile_image_url'])) {
                        @unlink('../' . $memberData['profile_image_url']);
                    }

                    // Update database
                    $imageUrl = 'uploads/profiles/' . $filename;
                    $pdo->prepare("UPDATE members SET profile_image_url = ? WHERE member_id = ?")->execute([$imageUrl, $currentMember['member_id']]);
                    $memberData['profile_image_url'] = $imageUrl;
                    $_SESSION['member']['profile_image_url'] = $imageUrl;

                    $success = 'อัปโหลดรูปโปรไฟล์เรียบร้อย';
                } else {
                    $errors[] = 'ไม่สามารถอัปโหลดไฟล์ได้';
                }
            }
        }
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword))
            $errors[] = 'กรุณากรอกรหัสผ่านปัจจุบัน';
        if (empty($newPassword))
            $errors[] = 'กรุณากรอกรหัสผ่านใหม่';
        if (strlen($newPassword) < 6)
            $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
        if ($newPassword !== $confirmPassword)
            $errors[] = 'รหัสผ่านใหม่ไม่ตรงกัน';

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT password_hash FROM members WHERE member_id = ?");
            $stmt->execute([$currentMember['member_id']]);
            $hash = $stmt->fetchColumn();

            $valid = password_verify($currentPassword, $hash) || $currentPassword === $hash;

            if (!$valid) {
                $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE members SET password_hash = ? WHERE member_id = ?")->execute([$newHash, $currentMember['member_id']]);
                $success = 'เปลี่ยนรหัสผ่านเรียบร้อย';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
        <?php foreach ($errors as $error): ?>
            <div><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="max-w-3xl mx-auto space-y-6">
    <!-- Profile Photo Section -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-6 flex items-center gap-6">
            <div class="relative">
                <?php if ($memberData['profile_image_url']): ?>
                    <img src="/model01/<?php echo htmlspecialchars($memberData['profile_image_url']); ?>" alt="Profile"
                        class="w-24 h-24 rounded-full object-cover border-4 border-blue-100">
                <?php else: ?>
                    <div class="w-24 h-24 rounded-full bg-blue-100 flex items-center justify-center text-4xl">
                        👤
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex-1">
                <h2 class="text-xl font-bold">
                    <?php echo htmlspecialchars($memberData['first_name'] . ' ' . $memberData['last_name']); ?>
                </h2>
                <p class="text-gray-500 font-mono"><?php echo htmlspecialchars($memberData['member_code']); ?></p>
                <form method="POST" enctype="multipart/form-data" class="mt-3 flex items-center gap-2">
                    <input type="hidden" name="action" value="upload_photo">
                    <input type="file" name="profile_image" accept="image/*" id="profileInput" class="hidden"
                        onchange="this.form.submit()">
                    <label for="profileInput"
                        class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-4 py-2 rounded-lg cursor-pointer text-sm font-medium">
                        📷 เปลี่ยนรูป
                    </label>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Form -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
            <h2 class="font-semibold text-lg">📝 ข้อมูลติดต่อ</h2>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="update_profile">

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1">ชื่อ</label>
                    <input type="text" name="first_name"
                        value="<?php echo htmlspecialchars($memberData['first_name']); ?>"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">นามสกุล</label>
                    <input type="text" name="last_name"
                        value="<?php echo htmlspecialchars($memberData['last_name']); ?>"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">เบอร์โทรศัพท์</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($memberData['phone']); ?>"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium mb-1">อีเมล</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($memberData['email'] ?? ''); ?>"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">
                บันทึกข้อมูล
            </button>
        </form>
    </div>

    <!-- Change Password (Collapsible) -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <button type="button" onclick="togglePasswordForm()" 
            class="w-full p-4 bg-gray-50 flex items-center justify-between hover:bg-gray-100 transition">
            <h2 class="font-semibold text-lg">🔐 เปลี่ยนรหัสผ่าน</h2>
            <svg id="togglePasswordIcon" class="w-5 h-5 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>

        <!-- Password Form (Hidden by default) -->
        <div id="passwordFormContainer" class="hidden">
            <form method="POST" class="p-6 border-t">
                <input type="hidden" name="action" value="change_password">

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">รหัสผ่านปัจจุบัน</label>
                    <input type="password" name="current_password"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" minlength="6"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium mb-1">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                </div>

                <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white py-2.5 rounded-lg font-medium">
                    เปลี่ยนรหัสผ่าน
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function togglePasswordForm() {
    const form = document.getElementById('passwordFormContainer');
    const icon = document.getElementById('togglePasswordIcon');
    
    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        icon.style.transform = 'rotate(180deg)';
    } else {
        form.classList.add('hidden');
        icon.style.transform = 'rotate(0deg)';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>