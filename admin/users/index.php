<?php
/**
 * User List - รายการผู้ใช้งานระบบ
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Handle Create User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $username = trim($_POST['username'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $role = $_POST['role'] ?? 'technician';
    $password = $_POST['password'] ?? '';

    if ($username && $firstName && $lastName && $role && $password) {
        $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if (!$checkStmt->fetch()) {
            // Handle profile image upload
            $profileImageUrl = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../uploads/users/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $filename = 'user_' . $username . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $filename)) {
                        $profileImageUrl = '/model01/uploads/users/' . $filename;
                    }
                }
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, first_name, last_name, profile_image_url, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $passwordHash, $firstName, $lastName, $profileImageUrl, $role]);
            header('Location: index.php?success=created');
            exit;
        }
    }
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $userId = $_POST['user_id'] ?? 0;
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $role = $_POST['role'] ?? 'technician';
    $status = $_POST['status'] ?? 'active';
    $newPassword = $_POST['new_password'] ?? '';

    if ($userId && $firstName && $lastName && $role) {
        // Get current user data
        $currentStmt = $pdo->prepare("SELECT username, profile_image_url FROM users WHERE user_id = ?");
        $currentStmt->execute([$userId]);
        $currentUser = $currentStmt->fetch();
        $profileImageUrl = $currentUser['profile_image_url'];

        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/users/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $filename = 'user_' . $currentUser['username'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $filename)) {
                    $profileImageUrl = '/model01/uploads/users/' . $filename;
                }
            }
        }

        // Prevent user from changing own status to suspended
        if ($userId == $_SESSION['user_id']) {
            $status = 'active';
        }

        if (!empty($newPassword)) {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, profile_image_url = ?, role = ?, status = ?, password_hash = ? WHERE user_id = ?");
            $stmt->execute([$firstName, $lastName, $profileImageUrl, $role, $status, $passwordHash, $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, profile_image_url = ?, role = ?, status = ? WHERE user_id = ?");
            $stmt->execute([$firstName, $lastName, $profileImageUrl, $role, $status, $userId]);
        }
        header('Location: index.php?success=updated');
        exit;
    }
}

// Handle Suspend User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'suspend') {
    $userId = $_POST['user_id'] ?? 0;
    // Prevent suspending yourself
    if ($userId && $userId != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE user_id = ?");
        $stmt->execute([$userId]);
        header('Location: index.php?success=suspended');
        exit;
    }
}

$pageTitle = 'จัดการผู้ใช้งาน';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get all users with status
$stmt = $pdo->query("SELECT u.*, ast.status_name 
    FROM users u 
    LEFT JOIN account_status ast ON UPPER(u.status) = ast.status_code
    ORDER BY u.user_id DESC");
$users = $stmt->fetchAll();

// Role options (hardcoded since ENUM)
$roles = [
    ['value' => 'admin', 'label' => 'ผู้ดูแลระบบ'],
    ['value' => 'accountant', 'label' => 'บัญชี'],
    ['value' => 'technician', 'label' => 'ช่าง']
];

// Get status list for dropdown
$statusList = $pdo->query("SELECT * FROM account_status ORDER BY status_code")->fetchAll();

$success = $_GET['success'] ?? '';
?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo ['created' => 'เพิ่มผู้ใช้งานสำเร็จ', 'updated' => 'แก้ไขข้อมูลสำเร็จ', 'suspended' => 'ระงับผู้ใช้งานสำเร็จ'][$success] ?? 'ดำเนินการสำเร็จ'; ?>
    </div>
<?php endif; ?>

<!-- Header -->
<div class="flex justify-between items-center mb-6">
    <p class="text-gray-500">จัดการบัญชีผู้ใช้งานและกำหนดบทบาท</p>
    <button onclick="document.getElementById('createModal').classList.remove('hidden')"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        เพิ่มผู้ใช้งาน
    </button>
</div>

<!-- Users Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ผู้ใช้งาน</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ชื่อผู้ใช้</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">บทบาท</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">สถานะ</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">เข้าใช้ล่าสุด</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($users as $user): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <?php if ($user['profile_image_url']): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image_url']); ?>"
                                    class="w-10 h-10 rounded-full object-cover">
                            <?php else: ?>
                                <div
                                    class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-semibold">
                                    <?php echo mb_substr($user['first_name'], 0, 1); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </div>
                                <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                    <span class="text-xs text-blue-600">(คุณ)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 font-mono text-sm text-gray-700"><?php echo htmlspecialchars($user['username']); ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php
                        $roleLabels = ['admin' => 'ผู้ดูแลระบบ', 'accountant' => 'บัญชี', 'technician' => 'ช่าง'];
                        $roleColors = ['admin' => 'bg-purple-100 text-purple-700', 'accountant' => 'bg-blue-100 text-blue-700', 'technician' => 'bg-orange-100 text-orange-700'];
                        $roleLabel = $roleLabels[$user['role']] ?? $user['role'];
                        $roleColor = $roleColors[$user['role']] ?? 'bg-gray-100 text-gray-700';
                        ?>
                        <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $roleColor; ?>">
                            <?php echo htmlspecialchars($roleLabel); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <?php
                        $statusColors = [
                            'active' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'dot' => 'bg-green-500'],
                            'suspended' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'dot' => 'bg-red-500']
                        ];
                        $sc = $statusColors[$user['status']] ?? $statusColors['active'];
                        ?>
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium <?php echo $sc['bg'] . ' ' . $sc['text']; ?>">
                            <span class="w-2 h-2 <?php echo $sc['dot']; ?> rounded-full"></span>
                            <?php echo htmlspecialchars($user['status_name'] ?? $user['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-'; ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="แก้ไข">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                    </path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="mt-6 text-sm text-gray-500">รวมทั้งหมด <?php echo count($users); ?> บัญชี</div>

<!-- Create User Modal -->
<div id="createModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('createModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl">
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-xl font-semibold">เพิ่มผู้ใช้งานใหม่</h2>
                <button onclick="document.getElementById('createModal').classList.add('hidden')"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="action" value="create">
                <div class="grid grid-cols-3 gap-6">
                    <div class="flex flex-col items-center pt-2">
                        <label class="block text-sm font-medium mb-3">รูปโปรไฟล์</label>
                        <div class="w-28 h-28 bg-gray-100 rounded-full flex items-center justify-center mb-3 overflow-hidden border-4 border-gray-200"
                            id="createPreview">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <input type="file" name="profile_image" accept="image/*" class="text-xs w-full"
                            onchange="previewImage(this, 'createPreview')">
                    </div>
                    <div class="col-span-2 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">ชื่อ <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="first_name" required
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">นามสกุล <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="last_name" required
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">ชื่อผู้ใช้ <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="username" required pattern="[a-zA-Z0-9_]{3,20}"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 font-mono"
                                    placeholder="ภาษาอังกฤษ 3-20 ตัว">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">บทบาท <span
                                        class="text-red-500">*</span></label>
                                <select name="role" required
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">-- เลือก --</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['value']; ?>">
                                            <?php echo htmlspecialchars($role['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">รหัสผ่าน <span
                                    class="text-red-500">*</span></label>
                            <input type="password" name="password" required minlength="6"
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="อย่างน้อย 6 ตัวอักษร">
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 pt-6 mt-6 border-t">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">บันทึก</button>
                    <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                        class="px-8 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('editModal').classList.add('hidden')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl">
            <div class="flex items-center justify-between p-6 border-b">
                <div>
                    <h2 class="text-xl font-semibold">แก้ไขผู้ใช้งาน</h2>
                    <p class="text-sm text-gray-500">ชื่อผู้ใช้: <span id="edit_username_display"
                            class="font-mono font-medium"></span></p>
                </div>
                <button onclick="document.getElementById('editModal').classList.add('hidden')"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="grid grid-cols-3 gap-6">
                    <div class="flex flex-col items-center pt-2">
                        <label class="block text-sm font-medium mb-3">รูปโปรไฟล์</label>
                        <div class="w-28 h-28 bg-gray-100 rounded-full flex items-center justify-center mb-3 overflow-hidden border-4 border-gray-200"
                            id="editPreview">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <input type="file" name="profile_image" accept="image/*" class="text-xs w-full"
                            onchange="previewImage(this, 'editPreview')">
                    </div>
                    <div class="col-span-2 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">ชื่อ <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="first_name" id="edit_first_name" required
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">นามสกุล <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="last_name" id="edit_last_name" required
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">บทบาท <span
                                        class="text-red-500">*</span></label>
                                <select name="role" id="edit_role" required
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['value']; ?>">
                                            <?php echo htmlspecialchars($role['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">สถานะ</label>
                                <select name="status" id="edit_status"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <?php foreach ($statusList as $st): ?>
                                        <option value="<?php echo strtolower($st['status_code']); ?>">
                                            <?php echo htmlspecialchars($st['status_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <p class="text-sm text-yellow-700 mb-2">เปลี่ยนรหัสผ่าน (เว้นว่างถ้าไม่เปลี่ยน)</p>
                            <input type="password" name="new_password" minlength="6" placeholder="รหัสผ่านใหม่"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 pt-6 mt-6 border-t">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">บันทึก</button>
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                        class="px-8 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Suspend Modal -->
<div id="suspendModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('suspendModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <svg class="w-16 h-16 mx-auto text-orange-500 mb-4" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
                <h3 class="text-lg font-semibold mb-2">ยืนยันการระงับ?</h3>
                <p class="text-gray-500 mb-6">ต้องการระงับผู้ใช้งาน "<span id="suspend_name"
                        class="font-medium"></span>"?
                </p>
                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="user_id" id="suspend_user_id">
                    <button type="button" onclick="document.getElementById('suspendModal').classList.add('hidden')"
                        class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                    <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium">ระงับ</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openEditModal(user) {
        document.getElementById('edit_user_id').value = user.user_id;
        document.getElementById('edit_username_display').textContent = user.username;
        document.getElementById('edit_first_name').value = user.first_name;
        document.getElementById('edit_last_name').value = user.last_name;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_status').value = user.status || 'active';

        const preview = document.getElementById('editPreview');
        if (user.profile_image_url) {
            preview.innerHTML = '<img src="' + user.profile_image_url + '" class="w-full h-full object-cover">';
        } else {
            preview.innerHTML = '<svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>';
        }
        document.getElementById('editModal').classList.remove('hidden');
    }

    function confirmSuspend(userId, userName) {
        document.getElementById('suspend_user_id').value = userId;
        document.getElementById('suspend_name').textContent = userName;
        document.getElementById('suspendModal').classList.remove('hidden');
    }

    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.innerHTML = '<img src="' + e.target.result + '" class="w-full h-full object-cover">';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('createModal').classList.add('hidden');
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('suspendModal').classList.add('hidden');
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>