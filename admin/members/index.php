<?php
/**
 * Member List - จัดการข้อมูลสมาชิก
 * VIP คำนวณอัตโนมัติจากยอดใช้บริการ (ตามค่าที่ตั้งไว้ในระบบ)
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Get VIP threshold from settings
$vipStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vip_threshold'");
$VIP_THRESHOLD = $vipStmt ? ($vipStmt->fetchColumn() ?: 50000) : 50000;

// Handle Create Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($firstName && $lastName && $phone) {
        try {
            // Generate member code
            $year = date('y');
            $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM members WHERE YEAR(created_at) = YEAR(NOW())");
            $count = $countStmt->fetch()['cnt'] + 1;
            $memberCode = 'M' . $year . str_pad($count, 4, '0', STR_PAD_LEFT);

            // Handle profile image upload
            $profileImageUrl = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../uploads/profiles/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $filename = $memberCode . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $filename)) {
                        $profileImageUrl = 'uploads/profiles/' . $filename;
                    }
                }
            }

            $passwordHash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
            $stmt = $pdo->prepare("INSERT INTO members (member_code, first_name, last_name, phone, email, password_hash, profile_image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$memberCode, $firstName, $lastName, $phone, $email, $passwordHash, $profileImageUrl]);
            header('Location: index.php?success=created');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                // Duplicate phone number error
                header('Location: index.php?error=duplicate_phone');
                exit;
            }
            throw $e;
        }
    }
}

// Handle Edit Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $memberId = $_POST['member_id'] ?? 0;
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $status = $_POST['status'] ?? 'active';

    if ($memberId && $firstName && $lastName && $phone) {
        $currentStmt = $pdo->prepare("SELECT member_code, profile_image_url FROM members WHERE member_id = ?");
        $currentStmt->execute([$memberId]);
        $currentMember = $currentStmt->fetch();
        $profileImageUrl = $currentMember['profile_image_url'];

        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/profiles/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $filename = $currentMember['member_code'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $filename)) {
                    $profileImageUrl = 'uploads/profiles/' . $filename;
                }
            }
        }

        if (!empty($newPassword)) {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE members SET first_name = ?, last_name = ?, phone = ?, email = ?, password_hash = ?, status = ?, profile_image_url = ? WHERE member_id = ?");
            $stmt->execute([$firstName, $lastName, $phone, $email, $passwordHash, $status, $profileImageUrl, $memberId]);
        } else {
            $stmt = $pdo->prepare("UPDATE members SET first_name = ?, last_name = ?, phone = ?, email = ?, status = ?, profile_image_url = ? WHERE member_id = ?");
            $stmt->execute([$firstName, $lastName, $phone, $email, $status, $profileImageUrl, $memberId]);
        }
        header('Location: index.php?success=updated');
        exit;
    }
}

// Handle Suspend Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'suspend') {
    $memberId = $_POST['member_id'] ?? 0;
    if ($memberId) {
        $stmt = $pdo->prepare("UPDATE members SET status = 'suspended' WHERE member_id = ?");
        $stmt->execute([$memberId]);
        header('Location: index.php?success=suspended');
        exit;
    }
}

$pageTitle = 'จัดการสมาชิก';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Search
$search = $_GET['search'] ?? '';
$searchWhere = $search ? " WHERE m.first_name LIKE ? OR m.last_name LIKE ? OR m.phone LIKE ? OR m.member_code LIKE ?" : '';
$searchParams = $search ? array_fill(0, 4, "%$search%") : [];

// Get members with total spending (from invoices)
$sql = "SELECT m.*, ms.status_name, COALESCE(SUM(i.net_amount), 0) as total_spending
        FROM members m
        LEFT JOIN account_status ms ON UPPER(m.status) = ms.status_code
        LEFT JOIN invoices i ON m.member_id = i.member_id AND i.payment_status = 'paid'
        " . $searchWhere . "
        GROUP BY m.member_id
        ORDER BY m.member_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($searchParams);
$members = $stmt->fetchAll();

// Get status list for dropdown
$statusList = $pdo->query("SELECT * FROM account_status ORDER BY status_code")->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<?php if ($error === 'duplicate_phone'): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        ❌ เบอร์โทรศัพท์นี้มีในระบบแล้ว กรุณาใช้เบอร์อื่น
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo ['created' => 'เพิ่มสมาชิกสำเร็จ', 'updated' => 'แก้ไขข้อมูลสำเร็จ', 'suspended' => 'ระงับสมาชิกสำเร็จ'][$success] ?? 'ดำเนินการสำเร็จ'; ?>
    </div>
<?php endif; ?>

<!-- Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <p class="text-gray-500">จัดการข้อมูลสมาชิก</p>
        <p class="text-xs text-blue-600 mt-1">⭐ VIP อัตโนมัติเมื่อใช้บริการครบ
            <?php echo number_format($VIP_THRESHOLD); ?> บาท
        </p>
    </div>
    <div class="flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ค้นหา..."
                class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 w-48">
            <button type="submit" class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg">ค้นหา</button>
        </form>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                </path>
            </svg>
            เพิ่มสมาชิก
        </button>
    </div>
</div>

<!-- Members Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">สมาชิก</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">รหัส</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">เบอร์โทร</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ยอดสะสม</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ระดับ</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">สถานะ</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($members)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <?php echo $search ? 'ไม่พบสมาชิก' : 'ยังไม่มีสมาชิก'; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($members as $member):
                    $isVip = $member['total_spending'] >= $VIP_THRESHOLD;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <?php if ($member['profile_image_url']): ?>
                                    <img src="/model01/<?php echo htmlspecialchars($member['profile_image_url']); ?>"
                                        class="w-10 h-10 rounded-full object-cover">
                                <?php else: ?>
                                    <div
                                        class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 font-semibold">
                                        <?php echo mb_substr($member['first_name'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($member['email'] ?? '-'); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-mono text-sm text-gray-700">
                            <?php echo htmlspecialchars($member['member_code']); ?>
                        </td>
                        <td class="px-6 py-4 text-gray-700"><?php echo htmlspecialchars($member['phone']); ?></td>
                        <td class="px-6 py-4 font-medium <?php echo $isVip ? 'text-green-600' : 'text-gray-700'; ?>">
                            ฿<?php echo number_format($member['total_spending'], 0); ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($isVip): ?>
                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">⭐ VIP</span>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">ทั่วไป</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php
                            $statusColors = [
                                'active' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'dot' => 'bg-green-500'],
                                'suspended' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'dot' => 'bg-red-500']
                            ];
                            $sc = $statusColors[$member['status']] ?? $statusColors['active'];
                            ?>
                            <span
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium <?php echo $sc['bg'] . ' ' . $sc['text']; ?>">
                                <span class="w-2 h-2 <?php echo $sc['dot']; ?> rounded-full"></span>
                                <?php echo htmlspecialchars($member['status_name'] ?? $member['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="view.php?id=<?php echo $member['member_id']; ?>"
                                    class="p-2 text-green-600 hover:bg-green-50 rounded-lg" title="ดูรายละเอียด">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                        </path>
                                    </svg>
                                </a>
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($member)); ?>)"
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
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-6 text-sm text-gray-500">รวมทั้งหมด <?php echo count($members); ?> คน</div>

<!-- Create Member Modal -->
<div id="createModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('createModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl">
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-xl font-semibold">เพิ่มสมาชิกใหม่</h2>
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
                                <label class="block text-sm font-medium mb-1">เบอร์โทร <span
                                        class="text-red-500">*</span></label>
                                <input type="tel" name="phone" required
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">อีเมล</label>
                                <input type="email" name="email"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">รหัสผ่าน</label>
                            <input type="password" name="password" minlength="6"
                                placeholder="สำหรับเข้าสู่ระบบ (อย่างน้อย 6 ตัว)"
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700">
                            ⭐ สมาชิกจะได้รับสถานะ VIP อัตโนมัติเมื่อใช้บริการครบ
                            <?php echo number_format($VIP_THRESHOLD); ?> บาท
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

<!-- Edit Member Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('editModal').classList.add('hidden')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl">
            <div class="flex items-center justify-between p-6 border-b">
                <div>
                    <h2 class="text-xl font-semibold">แก้ไขข้อมูลสมาชิก</h2>
                    <p class="text-sm text-gray-500">รหัส: <span id="edit_member_code"
                            class="font-mono font-medium"></span> | ยอดสะสม: <span id="edit_total_spending"
                            class="font-medium text-green-600"></span></p>
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
                <input type="hidden" name="member_id" id="edit_member_id">
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
                                <label class="block text-sm font-medium mb-1">เบอร์โทร <span
                                        class="text-red-500">*</span></label>
                                <input type="tel" name="phone" id="edit_phone" required
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">อีเมล</label>
                                <input type="email" name="email" id="edit_email"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <p class="text-sm text-yellow-700 mb-2">เปลี่ยนรหัสผ่าน (เว้นว่างถ้าไม่เปลี่ยน)</p>
                            <input type="password" name="new_password" minlength="6" placeholder="รหัสผ่านใหม่"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
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
                <p class="text-gray-500 mb-6">ต้องการระงับสมาชิก "<span id="suspend_name" class="font-medium"></span>"?
                </p>
                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="member_id" id="suspend_member_id">
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
    function openEditModal(member) {
        document.getElementById('edit_member_id').value = member.member_id;
        document.getElementById('edit_member_code').textContent = member.member_code;
        document.getElementById('edit_total_spending').textContent = '฿' + Number(member.total_spending).toLocaleString();
        document.getElementById('edit_first_name').value = member.first_name;
        document.getElementById('edit_last_name').value = member.last_name;
        document.getElementById('edit_phone').value = member.phone;
        document.getElementById('edit_email').value = member.email || '';
        document.getElementById('edit_status').value = member.status || 'active';

        const preview = document.getElementById('editPreview');
        if (member.profile_image_url) {
            preview.innerHTML = '<img src="/model01/' + member.profile_image_url + '" class="w-full h-full object-cover">';
        } else {
            preview.innerHTML = '<svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>';
        }
        document.getElementById('editModal').classList.remove('hidden');
    }

    function confirmSuspend(memberId, memberName) {
        document.getElementById('suspend_member_id').value = memberId;
        document.getElementById('suspend_name').textContent = memberName;
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