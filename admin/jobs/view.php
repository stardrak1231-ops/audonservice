<?php
/**
 * Job Order View - รายละเอียดใบงาน
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();
$jobId = $_GET['id'] ?? 0;

if (!$jobId) {
    header('Location: index.php');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['new_status'];
        $remark = $_POST['remark'] ?? '';

        // Get old status
        $oldStatus = $pdo->query("SELECT status FROM job_orders WHERE job_id = $jobId")->fetchColumn();

        // Update status
        $pdo->prepare("UPDATE job_orders SET status = ?, completed_date = IF(? IN ('COMPLETED','DELIVERED'), NOW(), completed_date) WHERE job_id = ?")
            ->execute([$newStatus, $newStatus, $jobId]);

        // Log timeline
        $pdo->prepare("INSERT INTO job_timeline (job_id, old_status, new_status, changed_by, remark) VALUES (?, ?, ?, ?, ?)")
            ->execute([$jobId, $oldStatus, $newStatus, $_SESSION['user_id'], $remark]);

        header('Location: view.php?id=' . $jobId . '&success=status_updated');
        exit;
    }

    if ($_POST['action'] === 'assign_technician') {
        $techId = $_POST['technician_id'] ?: null;
        $pdo->prepare("UPDATE job_orders SET assigned_to = ? WHERE job_id = ?")->execute([$techId, $jobId]);
        header('Location: view.php?id=' . $jobId . '&success=assigned');
        exit;
    }

    if ($_POST['action'] === 'add_service') {
        $serviceId = $_POST['service_id'];
        $qty = $_POST['quantity'] ?? 1;
        $price = $_POST['custom_price'] ?? 0; // Use submitted price

        $pdo->prepare("INSERT INTO job_services (job_id, service_id, quantity, price) VALUES (?, ?, ?, ?)")->execute([$jobId, $serviceId, $qty, $price]);
        header('Location: view.php?id=' . $jobId . '&success=service_added');
        exit;
    }

    if ($_POST['action'] === 'add_part') {
        $partId = $_POST['part_id'];
        $qty = (int) ($_POST['quantity'] ?? 1);

        // Check stock
        $stock = $pdo->query("SELECT stock_qty FROM spare_parts WHERE part_id = $partId")->fetchColumn();
        if ($stock >= $qty) {
            $pdo->prepare("INSERT INTO job_parts (job_id, part_id, quantity) VALUES (?, ?, ?)")->execute([$jobId, $partId, $qty]);
            $pdo->prepare("UPDATE spare_parts SET stock_qty = stock_qty - ? WHERE part_id = ?")->execute([$qty, $partId]);
            $pdo->prepare("INSERT INTO stock_movements (part_id, movement_type, quantity, reference_id) VALUES (?, 'OUT', ?, ?)")->execute([$partId, $qty, $jobId]);
        }
        header('Location: view.php?id=' . $jobId . '&success=part_added');
        exit;
    }

    // Cancel job
    if ($_POST['action'] === 'cancel_job') {
        $reason = trim($_POST['cancel_reason'] ?? '');
        if ($reason) {
            $oldStatus = $pdo->query("SELECT status FROM job_orders WHERE job_id = $jobId")->fetchColumn();
            $pdo->prepare("UPDATE job_orders SET status = 'CANCELLED' WHERE job_id = ?")->execute([$jobId]);
            $pdo->prepare("INSERT INTO job_timeline (job_id, old_status, new_status, changed_by, remark) VALUES (?, ?, 'CANCELLED', ?, ?)")
                ->execute([$jobId, $oldStatus, $_SESSION['user_id'], 'ยกเลิกงาน: ' . $reason]);
            header('Location: view.php?id=' . $jobId . '&success=cancelled');
            exit;
        }
    }

    // Edit job
    if ($_POST['action'] === 'edit_job') {
        $newJobType = $_POST['job_type'] ?? 'normal';
        $newAppointmentDate = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
        $newSymptom = trim($_POST['symptom'] ?? '');

        // Get old values
        $oldJob = $pdo->query("SELECT job_type, appointment_date, symptom FROM job_orders WHERE job_id = $jobId")->fetch();

        // Update
        $pdo->prepare("UPDATE job_orders SET job_type = ?, appointment_date = ?, symptom = ? WHERE job_id = ?")
            ->execute([$newJobType, $newAppointmentDate, $newSymptom ?: null, $jobId]);

        // Log changes
        $changes = [];
        if ($oldJob['job_type'] !== $newJobType) {
            $typeNames = ['normal' => 'ปกติ', 'urgent' => 'ด่วน', 'appointment' => 'นัดหมาย'];
            $changes[] = 'ลักษณะงาน: ' . ($typeNames[$oldJob['job_type']] ?? $oldJob['job_type']) . ' → ' . ($typeNames[$newJobType] ?? $newJobType);
        }
        if ($oldJob['appointment_date'] !== $newAppointmentDate) {
            $changes[] = 'วันนัดหมาย: ' . ($newAppointmentDate ? date('d/m/Y H:i', strtotime($newAppointmentDate)) : 'ยกเลิก');
        }
        if (($oldJob['symptom'] ?? '') !== $newSymptom) {
            $changes[] = 'อาการเบื้องต้น';
        }

        if (!empty($changes)) {
            $pdo->prepare("INSERT INTO job_timeline (job_id, new_status, changed_by, remark) VALUES (?, (SELECT status FROM job_orders WHERE job_id = ?), ?, ?)")
                ->execute([$jobId, $jobId, $_SESSION['user_id'], 'แก้ไขข้อมูล: ' . implode(', ', $changes)]);
        }

        header('Location: view.php?id=' . $jobId . '&success=updated');
        exit;
    }
}

// Get job details
$job = $pdo->prepare("SELECT j.*, 
    m.first_name, m.last_name, m.phone, m.member_code, m.profile_image_url,
    v.license_plate, v.brand, v.model, v.year, v.vehicle_image_url,
    u.first_name as tech_first_name, u.last_name as tech_last_name,
    o.first_name as opened_first_name, o.last_name as opened_last_name,
    js.status_name
    FROM job_orders j
    LEFT JOIN members m ON j.member_id = m.member_id
    LEFT JOIN vehicles v ON j.vehicle_id = v.vehicle_id
    LEFT JOIN users u ON j.assigned_to = u.user_id
    LEFT JOIN users o ON j.opened_by = o.user_id
    LEFT JOIN job_status js ON j.status = js.status_code AND j.job_category = js.job_category
    WHERE j.job_id = ?");
$job->execute([$jobId]);
$job = $job->fetch();

if (!$job) {
    header('Location: index.php');
    exit;
}

// Get timeline
$timeline = $pdo->prepare("SELECT t.*, u.first_name, u.last_name, 
    js_old.status_name as old_status_name, js_new.status_name as new_status_name
    FROM job_timeline t
    LEFT JOIN users u ON t.changed_by = u.user_id
    LEFT JOIN job_orders jo ON t.job_id = jo.job_id
    LEFT JOIN job_status js_old ON t.old_status = js_old.status_code AND jo.job_category = js_old.job_category
    LEFT JOIN job_status js_new ON t.new_status = js_new.status_code AND jo.job_category = js_new.job_category
    WHERE t.job_id = ? ORDER BY t.changed_at DESC");
$timeline->execute([$jobId]);
$timeline = $timeline->fetchAll();

// Get job services
$jobServices = $pdo->prepare("SELECT js.*, s.service_name, s.standard_price FROM job_services js JOIN service_items s ON js.service_id = s.service_id WHERE js.job_id = ?");
$jobServices->execute([$jobId]);
$jobServices = $jobServices->fetchAll();

// Get job parts
$jobParts = $pdo->prepare("SELECT jp.*, p.part_name, p.sell_price FROM job_parts jp JOIN spare_parts p ON jp.part_id = p.part_id WHERE jp.job_id = ?");
$jobParts->execute([$jobId]);
$jobParts = $jobParts->fetchAll();

// Get status list for this category
$statusList = $pdo->prepare("SELECT * FROM job_status WHERE job_category = ? ORDER BY sort_order");
$statusList->execute([$job['job_category']]);
$statusList = $statusList->fetchAll();

// Get technicians
$technicians = $pdo->query("SELECT * FROM users WHERE role = 'technician' AND status = 'active'")->fetchAll();

// Get available services and parts
$services = $pdo->query("SELECT * FROM service_items ORDER BY service_name")->fetchAll();
$parts = $pdo->query("SELECT * FROM spare_parts WHERE stock_qty > 0 ORDER BY part_name")->fetchAll();

// Calculate totals
$serviceTotal = array_sum(array_map(fn($s) => $s['price'] * $s['quantity'], $jobServices));
$partTotal = array_sum(array_map(fn($p) => $p['sell_price'] * $p['quantity'], $jobParts));
$grandTotal = $serviceTotal + $partTotal;

$pageTitle = 'ใบงาน #' . str_pad($jobId, 5, '0', STR_PAD_LEFT);
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$success = $_GET['success'] ?? '';
?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        ดำเนินการสำเร็จ
    </div>
<?php endif; ?>

<!-- Back Button -->
<div class="mb-4">
    <a href="index.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-blue-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
            </path>
        </svg>
        <span>กลับไปรายการใบงาน</span>
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Job Header -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-2xl font-bold text-blue-600">#
                            <?php echo str_pad($jobId, 5, '0', STR_PAD_LEFT); ?>
                        </span>
                        <?php if ($job['job_category'] === 'repair'): ?>
                            <span class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-sm font-medium">🔧
                                งานซ่อม</span>
                        <?php else: ?>
                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">🔄
                                งานบริการ</span>
                        <?php endif; ?>
                        <?php if ($job['job_type'] === 'urgent'): ?>
                            <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">🔥 ด่วน</span>
                        <?php elseif ($job['job_type'] === 'appointment'): ?>
                            <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm font-medium">
                                📅 นัดหมาย
                                <?php echo $job['appointment_date'] ? date('d/m/Y H:i', strtotime($job['appointment_date'])) : ''; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        เปิดงานโดย
                        <?php echo htmlspecialchars($job['opened_first_name'] . ' ' . $job['opened_last_name']); ?>
                        •
                        <?php echo date('d/m/Y H:i', strtotime($job['opened_date'])); ?>
                    </div>
                </div>
                <div class="text-right">
                    <?php
                    $statusColors = [
                        'RECEIVED' => 'bg-gray-200 text-gray-800',
                        'INSPECTING' => 'bg-yellow-200 text-yellow-800',
                        'WAIT_PART' => 'bg-orange-200 text-orange-800',
                        'IN_PROGRESS' => 'bg-blue-200 text-blue-800',
                        'COMPLETED' => 'bg-green-200 text-green-800',
                        'WAIT_PAYMENT' => 'bg-purple-200 text-purple-800',
                        'DELIVERED' => 'bg-emerald-200 text-emerald-800',
                        'CANCELLED' => 'bg-red-200 text-red-800',
                    ];
                    $color = $statusColors[$job['status']] ?? 'bg-gray-200 text-gray-800';
                    ?>
                    <span
                        class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold <?php echo $color; ?>">
                        <?php echo htmlspecialchars($job['status_name'] ?? $job['status']); ?>
                    </span>
                </div>
            </div>

            <!-- Customer & Vehicle Info -->
            <div class="grid grid-cols-2 gap-6 mt-6 pt-6 border-t">
                <div>
                    <div class="text-xs text-gray-500 uppercase font-medium mb-2">ลูกค้า</div>
                    <div class="font-semibold text-lg">
                        <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>
                    </div>
                    <div class="text-gray-500">
                        <?php echo htmlspecialchars($job['phone']); ?>
                    </div>
                    <div class="text-sm text-gray-400">
                        <?php echo htmlspecialchars($job['member_code']); ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase font-medium mb-2">รถยนต์</div>
                    <div
                        class="font-mono font-semibold text-lg bg-blue-50 text-blue-700 inline-block px-3 py-1 rounded">
                        <?php echo htmlspecialchars($job['license_plate']); ?>
                    </div>
                    <div class="text-gray-500 mt-1">
                        <?php echo htmlspecialchars(($job['brand'] ?? '') . ' ' . ($job['model'] ?? '')); ?>
                    </div>
                    <?php if ($job['year']): ?>
                        <div class="text-sm text-gray-400">ปี
                            <?php echo $job['year']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($job['job_category'] === 'repair' && !empty($job['symptom'])): ?>
                <!-- Symptom / Initial Remark -->
                <div class="mt-6 pt-6 border-t">
                    <div class="text-xs text-gray-500 uppercase font-medium mb-2">อาการเบื้องต้น</div>
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-gray-700">
                        <?php echo htmlspecialchars($job['symptom']); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Services -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50 flex items-center justify-between">
                <h3 class="font-semibold">
                    <?php echo $job['job_category'] === 'repair' ? '🔧 ค่าแรงและรายการซ่อม' : '✨ รายการบริการ'; ?>
                </h3>
            </div>

            <?php if (!empty($jobServices)): ?>
                <div class="divide-y">
                    <?php foreach ($jobServices as $s): ?>
                        <div class="p-3 flex justify-between items-center hover:bg-gray-50">
                            <span>
                                <?php echo htmlspecialchars($s['service_name']); ?>
                                <span class="text-gray-500">x<?php echo $s['quantity']; ?></span>
                            </span>
                            <span
                                class="text-green-600 font-medium">฿<?php echo number_format($s['price'] * $s['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-gray-400">ยังไม่มีรายการบริการ</div>
            <?php endif; ?>

            <!-- Inline Add Service Form -->
            <form method="POST" class="p-3 bg-blue-50 border-t">
                <input type="hidden" name="action" value="add_service">
                <div class="flex gap-2 items-end">
                    <div class="flex-1">
                        <select name="service_id" id="inlineServiceSelect" required
                            class="w-full px-3 py-2 border rounded-lg text-sm" onchange="updateInlineServicePrice()">
                            <option value="">+ เลือกบริการ/ค่าแรง...</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?php echo $s['service_id']; ?>"
                                    data-price="<?php echo $s['standard_price']; ?>">
                                    <?php echo htmlspecialchars($s['service_name']); ?>
                                    (฿<?php echo number_format($s['standard_price'], 0); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-24">
                        <input type="number" name="custom_price" id="inlineServicePrice" placeholder="ราคา" step="0.01"
                            min="0" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        เพิ่ม
                    </button>
                </div>
            </form>

            <div class="bg-gray-50 border-t p-3 flex justify-between font-medium">
                <span>รวมค่าบริการ</span>
                <span class="text-green-600">฿<?php echo number_format($serviceTotal, 2); ?></span>
            </div>
        </div>

        <!-- Parts -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50 flex items-center justify-between">
                <h3 class="font-semibold">📦 อะไหล่ที่ใช้</h3>
            </div>
            
            <?php if (!empty($jobParts)): ?>
            <div class="divide-y">
                <?php foreach ($jobParts as $p): ?>
                    <div class="p-3 flex justify-between items-center hover:bg-gray-50">
                        <span>
                            <?php echo htmlspecialchars($p['part_name']); ?>
                            <span class="text-gray-500">x<?php echo $p['quantity']; ?></span>
                        </span>
                        <span class="text-green-600 font-medium">฿<?php echo number_format($p['sell_price'] * $p['quantity'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="p-4 text-center text-gray-400">ยังไม่มีรายการอะไหล่</div>
            <?php endif; ?>

            <!-- Inline Add Part Form -->
            <form method="POST" class="p-3 bg-orange-50 border-t">
                <input type="hidden" name="action" value="add_part">
                <input type="hidden" name="part_id" id="inlinePartId">
                <div class="flex gap-2 items-end">
                    <div class="flex-1 relative">
                        <input type="text" id="inlinePartSearch" placeholder="พิมพ์ชื่ออะไหล่เพื่อค้นหา..." 
                            class="w-full px-3 py-2 border rounded-lg text-sm" autocomplete="off" oninput="searchPartsInline(this.value)">
                        <div id="inlinePartResults" class="absolute z-10 w-full bg-white border rounded-lg shadow-xl mt-1 max-h-48 overflow-y-auto hidden"></div>
                    </div>
                    <div class="w-20">
                        <input type="number" name="quantity" value="1" min="1" placeholder="จน." required 
                            class="w-full px-3 py-2 border rounded-lg text-sm text-center">
                    </div>
                    <button type="submit" id="btnAddPartInline" disabled class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                        เพิ่ม
                    </button>
                </div>
                <!-- Selected Part Display -->
                <div id="selectedPartInlineDisplay" class="mt-2 text-sm text-green-600 font-medium hidden">
                    เลือก: <span id="selectedPartName"></span>
                </div>
            </form>

            <div class="bg-gray-50 border-t p-3 flex justify-between font-medium">
                <span>รวมค่าอะไหล่</span>
                <span class="text-green-600">฿<?php echo number_format($partTotal, 2); ?></span>
            </div>
        </div>

        <!-- Grand Total -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl shadow-md p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="text-lg">ยอดรวมทั้งหมด</div>
                <div class="text-3xl font-bold">฿
                    <?php echo number_format($grandTotal, 2); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Actions -->
        <div class="bg-white rounded-xl shadow-md p-5 space-y-3">
            <h3 class="font-semibold mb-4">ดำเนินการ</h3>

            <!-- Assign Technician -->
            <form method="POST">
                <input type="hidden" name="action" value="assign_technician">
                <label class="block text-sm text-gray-600 mb-1">มอบหมายช่าง</label>
                <select name="technician_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">-- ยังไม่มอบหมาย --</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['user_id']; ?>" <?php echo $job['assigned_to'] == $tech['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <hr class="my-4">

            <!-- Update Status -->
            <button onclick="document.getElementById('statusModal').classList.remove('hidden')"
                class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                เปลี่ยนสถานะ
            </button>

            <!-- Edit Job -->
            <button onclick="document.getElementById('editJobModal').classList.remove('hidden')"
                class="w-full py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                    </path>
                </svg>
                แก้ไขข้อมูล
            </button>

            <?php if (!in_array($job['status'], ['CANCELLED', 'DELIVERED'])): ?>
                <!-- Cancel Job -->
                <button onclick="document.getElementById('cancelJobModal').classList.remove('hidden')"
                    class="w-full py-2.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                    ยกเลิกใบงาน
                </button>
            <?php endif; ?>
        </div>

        <!-- Timeline -->
        <div class="bg-white rounded-xl shadow-md p-5">
            <h3 class="font-semibold mb-4">ประวัติการทำงาน</h3>
            <div class="space-y-4">
                <?php foreach ($timeline as $t): ?>
                    <div class="flex gap-3">
                        <div class="w-2 h-2 rounded-full bg-blue-500 mt-2"></div>
                        <div class="flex-1">
                            <div class="text-sm font-medium">
                                <?php echo htmlspecialchars($t['new_status_name'] ?? $t['new_status']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>
                                •
                                <?php echo date('d/m H:i', strtotime($t['changed_at'])); ?>
                            </div>
                            <?php if ($t['remark']): ?>
                                <div class="text-xs text-gray-400 mt-1">
                                    <?php echo htmlspecialchars($t['remark']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div id="statusModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('statusModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold">เปลี่ยนสถานะงาน</h2>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update_status">
                <div>
                    <label class="block text-sm font-medium mb-1">สถานะใหม่</label>
                    <select name="new_status" required class="w-full px-4 py-2.5 border rounded-lg">
                        <?php foreach ($statusList as $s): ?>
                            <option value="<?php echo $s['status_code']; ?>" <?php echo $job['status'] === $s['status_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['status_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">หมายเหตุ (ไม่บังคับ)</label>
                    <input type="text" name="remark" class="w-full px-4 py-2.5 border rounded-lg"
                        placeholder="รายละเอียดเพิ่มเติม">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">บันทึก</button>
                    <button type="button" onclick="document.getElementById('statusModal').classList.add('hidden')"
                        class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Job Modal -->
<div id="cancelJobModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('cancelJobModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-6 border-b bg-red-50">
                <h2 class="text-xl font-semibold text-red-700">ยกเลิกใบงาน</h2>
                <p class="text-sm text-red-600 mt-1">การยกเลิกจะบันทึกไว้ในประวัติ</p>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="cancel_job">
                <div>
                    <label class="block text-sm font-medium mb-1">เหตุผลในการยกเลิก <span
                            class="text-red-500">*</span></label>
                    <textarea name="cancel_reason" required rows="3"
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-red-500"
                        placeholder="เช่น: ลูกค้ายกเลิก, เปิดงานผิด..."></textarea>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-lg font-medium">ยืนยันยกเลิก</button>
                    <button type="button" onclick="document.getElementById('cancelJobModal').classList.add('hidden')"
                        class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ปิด</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Job Modal -->
<div id="editJobModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('editJobModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold">แก้ไขข้อมูลใบงาน</h2>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_job">
                <div>
                    <label class="block text-sm font-medium mb-2">ลักษณะงาน</label>
                    <div class="flex gap-2">
                        <label
                            class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer hover:bg-gray-50 flex-1">
                            <input type="radio" name="job_type" value="normal" <?php echo $job['job_type'] === 'normal' ? 'checked' : ''; ?> class="text-blue-600" onchange="toggleEditAppointment()">
                            <span>ปกติ</span>
                        </label>
                        <label
                            class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer hover:bg-gray-50 flex-1">
                            <input type="radio" name="job_type" value="urgent" <?php echo $job['job_type'] === 'urgent' ? 'checked' : ''; ?> class="text-red-600" onchange="toggleEditAppointment()">
                            <span class="text-red-600">🔥 ด่วน</span>
                        </label>
                        <label
                            class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer hover:bg-gray-50 flex-1">
                            <input type="radio" name="job_type" value="appointment" <?php echo $job['job_type'] === 'appointment' ? 'checked' : ''; ?> class="text-purple-600"
                                onchange="toggleEditAppointment()">
                            <span class="text-purple-600">📅</span>
                        </label>
                    </div>
                </div>
                <div id="editAppointmentField"
                    class="<?php echo $job['job_type'] === 'appointment' ? '' : 'hidden'; ?>">
                    <label class="block text-sm font-medium mb-1">วันเวลานัดหมาย</label>
                    <input type="datetime-local" name="appointment_date"
                        value="<?php echo $job['appointment_date'] ? date('Y-m-d\TH:i', strtotime($job['appointment_date'])) : ''; ?>"
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                <?php if ($job['job_category'] === 'repair'): ?>
                    <div>
                        <label class="block text-sm font-medium mb-1">อาการเบื้องต้น</label>
                        <textarea name="symptom" rows="3"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="เช่น: รถสตาร์ทไม่ติด, แอร์ไม่เย็น..."><?php echo htmlspecialchars($job['symptom'] ?? ''); ?></textarea>
                    </div>
                <?php endif; ?>
                <div class="flex gap-3 pt-2">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">บันทึก</button>
                    <button type="button" onclick="document.getElementById('editJobModal').classList.add('hidden')"
                        class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Inline Service Logic
    function updateInlineServicePrice() {
        const select = document.getElementById('inlineServiceSelect');
        const priceInput = document.getElementById('inlineServicePrice');
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.dataset.price) {
            priceInput.value = selectedOption.dataset.price;
        }
    }

    // Inline Part Search Logic
    const allParts = [
        <?php foreach ($parts as $p): ?>
        { id: <?php echo $p['part_id']; ?>, name: "<?php echo addslashes($p['part_name']); ?>", price: <?php echo $p['sell_price']; ?>, stock: <?php echo $p['stock_qty']; ?> },
        <?php endforeach; ?>
    ];

    function searchPartsInline(query) {
        const results = document.getElementById('inlinePartResults');
        if (query.length < 1) {
            results.classList.add('hidden');
            return;
        }

        const filtered = allParts.filter(p => p.name.toLowerCase().includes(query.toLowerCase()));
        
        if (filtered.length === 0) {
            results.innerHTML = '<div class="p-3 text-gray-500 text-center text-sm">ไม่พบอะไหล่</div>';
        } else {
            results.innerHTML = filtered.map(p => 
                `<div class="p-3 hover:bg-blue-50 cursor-pointer border-b last:border-0 text-sm" 
                      onclick="selectPartInline(${p.id}, '${p.name}', ${p.price}, ${p.stock})">
                    <div class="font-medium">${p.name}</div>
                    <div class="text-xs text-gray-500">฿${p.price.toLocaleString()} | เหลือ ${p.stock}</div>
                </div>`
            ).join('');
        }
        results.classList.remove('hidden');
    }

    function selectPartInline(id, name, price, stock) {
        document.getElementById('inlinePartId').value = id;
        document.getElementById('selectedPartName').textContent = name + ' (฿' + price + ')';
        document.getElementById('selectedPartInlineDisplay').classList.remove('hidden');
        document.getElementById('inlinePartResults').classList.add('hidden');
        document.getElementById('inlinePartSearch').value = ''; // Clear search
        document.getElementById('btnAddPartInline').disabled = false;
        
        // Focus quantity
        document.querySelector('input[name="quantity"]').focus();
    }

    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#inlinePartSearch') && !e.target.closest('#inlinePartResults')) {
            document.getElementById('inlinePartResults').classList.add('hidden');
        }
    });

    // Toggle Edit Appointment Field
    function toggleEditAppointment() {
        const type = document.querySelector('input[name="job_type"]:checked').value;
        const field = document.getElementById('editAppointmentField');
        if (type === 'appointment') {
            field.classList.remove('hidden');
        } else {
            field.classList.add('hidden');
        }
    }

    // Close Modals on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('statusModal').classList.add('hidden');
            document.getElementById('cancelJobModal').classList.add('hidden');
            document.getElementById('editJobModal').classList.add('hidden');
        }
    });

    // Initialize logic
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('inlineServiceSelect')) {
            updateInlineServicePrice();
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>