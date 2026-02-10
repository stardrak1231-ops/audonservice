<?php
/**
 * Member Detail View - รายละเอียดสมาชิก
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();
$memberId = $_GET['id'] ?? 0;

if (!$memberId) {
    header('Location: index.php');
    exit;
}

// Handle Add Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_vehicle') {
    $licensePlate = trim($_POST['license_plate'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $year = $_POST['year'] ?? null;

    if ($licensePlate) {
        // Handle image upload
        $vehicleImageUrl = null;
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/vehicles/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['vehicle_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $filename = 'vehicle_' . str_replace([' ', '-'], '', $licensePlate) . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $uploadDir . $filename)) {
                    $vehicleImageUrl = '/model01/uploads/vehicles/' . $filename;
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO vehicles (member_id, license_plate, brand, model, color, year, vehicle_image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$memberId, $licensePlate, $brand, $model, $color ?: null, $year ?: null, $vehicleImageUrl]);
        header('Location: view.php?id=' . $memberId . '&success=vehicle_added');
        exit;
    }
}

// Handle Edit Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_vehicle') {
    $vehicleId = $_POST['vehicle_id'] ?? 0;
    $licensePlate = trim($_POST['license_plate'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $year = $_POST['year'] ?? null;

    if ($vehicleId && $licensePlate) {
        // Get current image
        $currentStmt = $pdo->prepare("SELECT vehicle_image_url FROM vehicles WHERE vehicle_id = ?");
        $currentStmt->execute([$vehicleId]);
        $current = $currentStmt->fetch();
        $vehicleImageUrl = $current['vehicle_image_url'];

        // Handle new image upload
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/vehicles/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['vehicle_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $filename = 'vehicle_' . str_replace([' ', '-'], '', $licensePlate) . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $uploadDir . $filename)) {
                    $vehicleImageUrl = '/model01/uploads/vehicles/' . $filename;
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE vehicles SET license_plate = ?, brand = ?, model = ?, color = ?, year = ?, vehicle_image_url = ? WHERE vehicle_id = ?");
        $stmt->execute([$licensePlate, $brand, $model, $color ?: null, $year ?: null, $vehicleImageUrl, $vehicleId]);
        header('Location: view.php?id=' . $memberId . '&success=vehicle_updated');
        exit;
    }
}

// Handle Delete Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_vehicle') {
    $vehicleId = $_POST['vehicle_id'] ?? 0;
    if ($vehicleId) {
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE vehicle_id = ? AND member_id = ?");
        $stmt->execute([$vehicleId, $memberId]);
        header('Location: view.php?id=' . $memberId . '&success=vehicle_deleted');
        exit;
    }
}

// Get member details
$member = $pdo->prepare("SELECT m.*, 
    (SELECT COALESCE(SUM(net_amount), 0) FROM invoices WHERE member_id = m.member_id AND payment_status = 'paid') as total_spent
    FROM members m WHERE m.member_id = ?");
$member->execute([$memberId]);
$member = $member->fetch();

if (!$member) {
    header('Location: index.php');
    exit;
}

// Get member's vehicles
$vehicles = $pdo->prepare("SELECT v.*, 
    (SELECT COUNT(*) FROM job_orders WHERE vehicle_id = v.vehicle_id) as job_count
    FROM vehicles v WHERE v.member_id = ? ORDER BY v.vehicle_id DESC");
$vehicles->execute([$memberId]);
$vehicles = $vehicles->fetchAll();

// Get member's job history
$jobs = $pdo->prepare("SELECT jo.*, 
    v.license_plate, v.brand, v.model,
    u.first_name as tech_first_name, u.last_name as tech_last_name,
    js.status_name,
    i.net_amount, i.payment_status
    FROM job_orders jo
    LEFT JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN users u ON jo.assigned_to = u.user_id
    LEFT JOIN job_status js ON jo.status = js.status_code AND jo.job_category = js.job_category
    LEFT JOIN invoices i ON jo.job_id = i.job_id
    WHERE jo.member_id = ?
    ORDER BY jo.opened_date DESC
    LIMIT 20");
$jobs->execute([$memberId]);
$jobs = $jobs->fetchAll();

// VIP status from settings
$vipStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vip_threshold'");
$vipThreshold = $vipStmt ? ($vipStmt->fetchColumn() ?: 50000) : 50000;
$isVip = $member['total_spent'] >= $vipThreshold;

$pageTitle = 'สมาชิก: ' . $member['first_name'] . ' ' . $member['last_name'];
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$success = $_GET['success'] ?? '';
?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo [
            'vehicle_added' => 'เพิ่มรถยนต์สำเร็จ',
            'vehicle_updated' => 'แก้ไขข้อมูลรถสำเร็จ',
            'vehicle_deleted' => 'ลบรถยนต์สำเร็จ'
        ][$success] ?? 'ดำเนินการสำเร็จ'; ?>
    </div>
<?php endif; ?>

<!-- Back Link -->
<a href="index.php" class="text-blue-600 hover:text-blue-700 text-sm mb-4 inline-flex items-center gap-1">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
    </svg>
    กลับรายชื่อสมาชิก
</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-4">
    <!-- Member Info Card -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <!-- Profile Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white text-center">
                <?php if ($member['profile_image_url']): ?>
                    <img src="/model01/<?php echo htmlspecialchars($member['profile_image_url']); ?>"
                        class="w-24 h-24 rounded-full mx-auto border-4 border-white/30 object-cover mb-3">
                <?php else: ?>
                    <div
                        class="w-24 h-24 rounded-full mx-auto bg-white/20 flex items-center justify-center text-4xl font-bold mb-3">
                        <?php echo mb_substr($member['first_name'], 0, 1); ?>
                    </div>
                <?php endif; ?>

                <div class="flex items-center justify-center gap-2 mb-1">
                    <h2 class="text-xl font-bold">
                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                    </h2>
                    <?php if ($isVip): ?>
                        <span class="bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-0.5 rounded-full">VIP</span>
                    <?php endif; ?>
                </div>
                <div class="text-blue-100">
                    <?php echo htmlspecialchars($member['member_code']); ?>
                </div>
            </div>

            <!-- Member Details -->
            <div class="p-5 space-y-4">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                        </path>
                    </svg>
                    <div>
                        <div class="text-xs text-gray-500">เบอร์โทร</div>
                        <div class="font-medium">
                            <?php echo htmlspecialchars($member['phone']); ?>
                        </div>
                    </div>
                </div>

                <?php if ($member['email']): ?>
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                            </path>
                        </svg>
                        <div>
                            <div class="text-xs text-gray-500">อีเมล</div>
                            <div class="font-medium">
                                <?php echo htmlspecialchars($member['email']); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <hr>

                <div class="grid grid-cols-2 gap-4 text-center">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-2xl font-bold text-blue-600">
                            <?php echo count($vehicles); ?>
                        </div>
                        <div class="text-xs text-gray-500">รถยนต์</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-2xl font-bold text-green-600">฿
                            <?php echo number_format($member['total_spent'], 0); ?>
                        </div>
                        <div class="text-xs text-gray-500">ยอดใช้จ่าย</div>
                    </div>
                </div>

                <div class="text-center text-xs text-gray-400 pt-2">
                    สมัครเมื่อ
                    <?php echo date('d/m/Y', strtotime($member['created_at'])); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Vehicles & Jobs -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Vehicles Section -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-5 border-b flex items-center justify-between">
                <h3 class="font-semibold text-lg">🚗 รถยนต์ของสมาชิก</h3>
                <button onclick="document.getElementById('addVehicleModal').classList.remove('hidden')"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    เพิ่มรถ
                </button>
            </div>

            <?php if (empty($vehicles)): ?>
                <div class="p-8 text-center text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0">
                        </path>
                    </svg>
                    ยังไม่มีรถยนต์
                </div>
            <?php else: ?>
                <div class="divide-y">
                    <?php foreach ($vehicles as $vehicle): ?>
                        <div class="p-4 flex items-center gap-4 hover:bg-gray-50">
                            <!-- Vehicle Image -->
                            <?php if ($vehicle['vehicle_image_url']): ?>
                                <img src="<?php echo htmlspecialchars($vehicle['vehicle_image_url']); ?>"
                                    class="w-20 h-14 rounded-lg object-cover flex-shrink-0">
                            <?php else: ?>
                                <div class="w-20 h-14 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>

                            <!-- Vehicle Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">
                                        <?php echo htmlspecialchars($vehicle['license_plate']); ?>
                                    </span>
                                    <?php if ($vehicle['year']): ?>
                                        <span class="text-xs text-gray-400">ปี
                                            <?php echo $vehicle['year']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-gray-700">
                                    <?php echo htmlspecialchars(($vehicle['brand'] ?: '') . ' ' . ($vehicle['model'] ?: '')); ?>
                                    <?php if ($vehicle['color']): ?>
                                        <span class="text-gray-400">สี
                                            <?php echo htmlspecialchars($vehicle['color']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?php echo $vehicle['job_count']; ?> งาน
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-1">
                                <button onclick="openEditVehicleModal(<?php echo htmlspecialchars(json_encode($vehicle)); ?>)"
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="แก้ไข">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                        </path>
                                    </svg>
                                </button>
                                <button
                                    onclick="confirmDeleteVehicle(<?php echo $vehicle['vehicle_id']; ?>, '<?php echo htmlspecialchars($vehicle['license_plate'], ENT_QUOTES); ?>')"
                                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="ลบ">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Job History Section -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-5 border-b">
                <h3 class="font-semibold text-lg">📋 ประวัติการใช้บริการ</h3>
            </div>

            <?php if (empty($jobs)): ?>
                <div class="p-8 text-center text-gray-400">
                    ยังไม่มีประวัติ
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">งาน</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">รถ</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">สถานะ</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">ยอด</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($jobs as $job): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="../jobs/view.php?id=<?php echo $job['job_id']; ?>"
                                        class="text-blue-600 hover:underline font-mono">
                                        #
                                        <?php echo str_pad($job['job_id'], 5, '0', STR_PAD_LEFT); ?>
                                    </a>
                                    <div class="text-xs text-gray-400">
                                        <?php echo date('d/m/Y', strtotime($job['opened_date'])); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-sm">
                                        <?php echo htmlspecialchars($job['license_plate']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $statusColors = [
                                        'RECEIVED' => 'bg-gray-100 text-gray-700',
                                        'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
                                        'COMPLETED' => 'bg-green-100 text-green-700',
                                        'DELIVERED' => 'bg-emerald-100 text-emerald-700',
                                    ];
                                    $color = $statusColors[$job['status']] ?? 'bg-gray-100 text-gray-700';
                                    ?>
                                    <span
                                        class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?php echo $color; ?>">
                                        <?php echo htmlspecialchars($job['status_name'] ?? $job['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?php if ($job['net_amount']): ?>
                                        <span
                                            class="font-medium <?php echo $job['payment_status'] === 'paid' ? 'text-green-600' : 'text-orange-600'; ?>">
                                            ฿
                                            <?php echo number_format($job['net_amount'], 0); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Vehicle Modal -->
<div id="addVehicleModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('addVehicleModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-xl font-semibold">เพิ่มรถยนต์</h2>
                <button onclick="document.getElementById('addVehicleModal').classList.add('hidden')"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add_vehicle">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">ทะเบียน <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="license_plate" required
                            class="w-full px-4 py-2 border rounded-lg font-mono" placeholder="กก 1234">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">ปี</label>
                        <input type="number" name="year" min="1990" max="2030"
                            class="w-full px-4 py-2 border rounded-lg" placeholder="2024">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">ยี่ห้อ</label>
                        <input type="text" name="brand" class="w-full px-4 py-2 border rounded-lg" placeholder="Toyota">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">รุ่น</label>
                        <input type="text" name="model" class="w-full px-4 py-2 border rounded-lg" placeholder="Camry">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">สี</label>
                        <input type="text" name="color" class="w-full px-4 py-2 border rounded-lg" placeholder="ขาว">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">รูปรถ</label>
                    <input type="file" name="vehicle_image" accept="image/*" class="w-full text-sm">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">บันทึก</button>
                    <button type="button" onclick="document.getElementById('addVehicleModal').classList.add('hidden')"
                        class="px-8 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Vehicle Modal -->
<div id="editVehicleModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50"
        onclick="document.getElementById('editVehicleModal').classList.add('hidden')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-xl font-semibold">แก้ไขรถยนต์</h2>
                <button onclick="document.getElementById('editVehicleModal').classList.add('hidden')"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_vehicle">
                <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">ทะเบียน <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="license_plate" id="edit_license_plate" required
                            class="w-full px-4 py-2 border rounded-lg font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">ปี</label>
                        <input type="number" name="year" id="edit_year" min="1990" max="2030"
                            class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">ยี่ห้อ</label>
                        <input type="text" name="brand" id="edit_brand" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">รุ่น</label>
                        <input type="text" name="model" id="edit_model" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">สี</label>
                        <input type="text" name="color" id="edit_color" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">รูปรถ (เว้นว่างถ้าไม่เปลี่ยน)</label>
                    <div id="edit_image_preview" class="mb-2"></div>
                    <input type="file" name="vehicle_image" accept="image/*" class="w-full text-sm">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">บันทึก</button>
                    <button type="button" onclick="document.getElementById('editVehicleModal').classList.add('hidden')"
                        class="px-8 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Vehicle Modal -->
<div id="deleteVehicleModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50"
        onclick="document.getElementById('deleteVehicleModal').classList.add('hidden')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <svg class="w-16 h-16 mx-auto text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
                <h3 class="text-lg font-semibold mb-2">ยืนยันการลบ?</h3>
                <p class="text-gray-500 mb-6">ต้องการลบรถ "<span id="delete_vehicle_plate"
                        class="font-medium font-mono"></span>"?</p>
                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="action" value="delete_vehicle">
                    <input type="hidden" name="vehicle_id" id="delete_vehicle_id">
                    <button type="button"
                        onclick="document.getElementById('deleteVehicleModal').classList.add('hidden')"
                        class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                    <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">ลบ</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openEditVehicleModal(vehicle) {
        document.getElementById('edit_vehicle_id').value = vehicle.vehicle_id;
        document.getElementById('edit_license_plate').value = vehicle.license_plate;
        document.getElementById('edit_brand').value = vehicle.brand || '';
        document.getElementById('edit_model').value = vehicle.model || '';
        document.getElementById('edit_color').value = vehicle.color || '';
        document.getElementById('edit_year').value = vehicle.year || '';

        const preview = document.getElementById('edit_image_preview');
        if (vehicle.vehicle_image_url) {
            preview.innerHTML = '<img src="' + vehicle.vehicle_image_url + '" class="w-20 h-14 rounded-lg object-cover">';
        } else {
            preview.innerHTML = '';
        }

        document.getElementById('editVehicleModal').classList.remove('hidden');
    }

    function confirmDeleteVehicle(vehicleId, plate) {
        document.getElementById('delete_vehicle_id').value = vehicleId;
        document.getElementById('delete_vehicle_plate').textContent = plate;
        document.getElementById('deleteVehicleModal').classList.remove('hidden');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('addVehicleModal').classList.add('hidden');
            document.getElementById('editVehicleModal').classList.add('hidden');
            document.getElementById('deleteVehicleModal').classList.add('hidden');
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>