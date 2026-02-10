<?php
/**
 * Technician Job Detail - รายละเอียดงานและจัดการ
 */

require_once '../config/database.php';
require_once '../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();
$currentUser = getCurrentUser();
$technicianId = $currentUser['user_id'];
$jobId = $_GET['id'] ?? 0;

if (!$jobId) {
    header('Location: index.php');
    exit;
}

// Create photos table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS job_photos (
    photo_id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    photo_type ENUM('before', 'during', 'after') NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    uploaded_by INT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES job_orders(job_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create notes table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS job_notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    note_text TEXT NOT NULL,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES job_orders(job_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        if ($newStatus) {
            // Get old status
            $oldStatusStmt = $pdo->prepare("SELECT status FROM job_orders WHERE job_id = ?");
            $oldStatusStmt->execute([$jobId]);
            $oldStatus = $oldStatusStmt->fetchColumn();

            $stmt = $pdo->prepare("UPDATE job_orders SET status = ?, completed_date = IF(? IN ('COMPLETED','DELIVERED'), NOW(), completed_date) WHERE job_id = ? AND assigned_to = ?");
            $stmt->execute([$newStatus, $newStatus, $jobId, $technicianId]);

            // Add timeline entry
            $pdo->prepare("INSERT INTO job_timeline (job_id, old_status, new_status, changed_by, remark) VALUES (?, ?, ?, ?, ?)")
                ->execute([$jobId, $oldStatus, $newStatus, $technicianId, 'เปลี่ยนสถานะโดยช่าง']);
        }
        header('Location: job.php?id=' . $jobId . '&success=status_updated');
        exit;
    }

    if ($_POST['action'] === 'add_note') {
        $noteText = trim($_POST['note_text'] ?? '');
        if ($noteText) {
            $stmt = $pdo->prepare("INSERT INTO job_notes (job_id, note_text, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$jobId, $noteText, $technicianId]);
        }
        header('Location: job.php?id=' . $jobId . '&success=note_added');
        exit;
    }

    if ($_POST['action'] === 'upload_photo') {
        $photoType = $_POST['photo_type'] ?? 'during';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/jobs/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $filename = 'job_' . $jobId . '_' . $photoType . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                    $imageUrl = '/model01/uploads/jobs/' . $filename;
                    $stmt = $pdo->prepare("INSERT INTO job_photos (job_id, photo_type, image_url, uploaded_by) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$jobId, $photoType, $imageUrl, $technicianId]);
                }
            }
        }
        header('Location: job.php?id=' . $jobId . '&success=photo_uploaded');
        exit;
    }

    if ($_POST['action'] === 'use_part') {
        $partId = $_POST['part_id'] ?? 0;
        $quantity = intval($_POST['quantity'] ?? 1);
        if ($partId && $quantity > 0) {
            // Check stock
            $part = $pdo->prepare("SELECT * FROM spare_parts WHERE part_id = ? AND stock_qty >= ?");
            $part->execute([$partId, $quantity]);
            if ($part->fetch()) {
                // Add to job_parts
                $stmt = $pdo->prepare("INSERT INTO job_parts (job_id, part_id, quantity) VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE quantity = quantity + ?");
                $stmt->execute([$jobId, $partId, $quantity, $quantity]);

                // Reduce stock
                $pdo->prepare("UPDATE spare_parts SET stock_qty = stock_qty - ? WHERE part_id = ?")->execute([$quantity, $partId]);
            }
        }
        header('Location: job.php?id=' . $jobId . '&success=part_used');
        exit;
    }

    if ($_POST['action'] === 'add_service') {
        $serviceId = $_POST['service_id'] ?? 0;
        $quantity = intval($_POST['quantity'] ?? 1);
        $price = floatval($_POST['custom_price'] ?? 0);

        if ($serviceId && $quantity > 0) {
            $pdo->prepare("INSERT INTO job_services (job_id, service_id, quantity, price) VALUES (?, ?, ?, ?)")
                ->execute([$jobId, $serviceId, $quantity, $price]);

            // Log to timeline
            $serviceName = $pdo->query("SELECT service_name FROM service_items WHERE service_id = $serviceId")->fetchColumn();
            $pdo->prepare("INSERT INTO job_timeline (job_id, new_status, changed_by, remark) VALUES (?, (SELECT status FROM job_orders WHERE job_id = ?), ?, ?)")
                ->execute([$jobId, $jobId, $technicianId, 'เพิ่มบริการ: ' . $serviceName]);
        }
        header('Location: job.php?id=' . $jobId . '&success=service_added');
        exit;
    }
}

// Get job details
$job = $pdo->prepare("SELECT jo.*, 
    v.license_plate, v.brand, v.model, v.color, v.year, v.vehicle_image_url,
    m.first_name as member_first_name, m.last_name as member_last_name, m.phone as member_phone,
    js.status_name
    FROM job_orders jo
    LEFT JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN members m ON jo.member_id = m.member_id
    LEFT JOIN job_status js ON jo.status = js.status_code AND jo.job_category = js.job_category
    WHERE jo.job_id = ?");
$job->execute([$jobId]);
$job = $job->fetch();

if (!$job) {
    header('Location: index.php');
    exit;
}

// Check if assigned to this technician
$isAssigned = ($job['assigned_to'] == $technicianId);

// Get services
$services = $pdo->prepare("SELECT js.*, s.service_name, s.standard_price 
    FROM job_services js 
    JOIN service_items s ON js.service_id = s.service_id 
    WHERE js.job_id = ?");
$services->execute([$jobId]);
$services = $services->fetchAll();

// Get parts used
$parts = $pdo->prepare("SELECT jp.*, p.part_name, p.sell_price 
    FROM job_parts jp 
    JOIN spare_parts p ON jp.part_id = p.part_id 
    WHERE jp.job_id = ?");
$parts->execute([$jobId]);
$parts = $parts->fetchAll();

// Get photos
$photos = $pdo->prepare("SELECT * FROM job_photos WHERE job_id = ? ORDER BY uploaded_at");
$photos->execute([$jobId]);
$photos = $photos->fetchAll();

// Get notes
$notes = $pdo->prepare("SELECT n.*, u.first_name, u.last_name FROM job_notes n 
    LEFT JOIN users u ON n.created_by = u.user_id 
    WHERE n.job_id = ? ORDER BY n.created_at DESC");
$notes->execute([$jobId]);
$notes = $notes->fetchAll();

// Get available parts for dropdown
$availableParts = $pdo->query("SELECT part_id, part_name, stock_qty FROM spare_parts WHERE stock_qty > 0 ORDER BY part_name")->fetchAll();

// Get available services for dropdown
$availableServices = $pdo->query("SELECT service_id, service_name, standard_price FROM service_items ORDER BY service_name")->fetchAll();

// Status workflow per category from database
$statusFlowStmt = $pdo->prepare("SELECT status_code, status_name, sort_order FROM job_status WHERE job_category = ? ORDER BY sort_order");
$statusFlowStmt->execute([$job['job_category']]);
$allStatuses = $statusFlowStmt->fetchAll();

// Build next status map for technician
// Technician can update status up to COMPLETED only
// After COMPLETED (WAIT_PAYMENT, WAIT_PICKUP, DELIVERED) is handled by accounting/admin
$statusFlow = [];
$technicianStopAt = ['COMPLETED', 'CANCELLED']; // Technician's job ends here

for ($i = 0; $i < count($allStatuses) - 1; $i++) {
    $current = $allStatuses[$i]['status_code'];
    $next = $allStatuses[$i + 1];

    // Skip if current status is COMPLETED or later (technician's job is done)
    if (in_array($current, ['COMPLETED', 'WAIT_PAYMENT', 'WAIT_PICKUP', 'DELIVERED'])) {
        continue;
    }

    // Skip CANCELLED as next status
    if ($next['status_code'] === 'CANCELLED') {
        continue;
    }

    $statusFlow[$current] = [
        'next' => $next['status_code'],
        'label' => $next['status_name'],
        'color' => 'bg-blue-600'
    ];
}

$pageTitle = 'งาน #' . str_pad($jobId, 5, '0', STR_PAD_LEFT);
require_once 'includes/header.php';

$success = $_GET['success'] ?? '';
?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo [
            'status_updated' => 'อัพเดตสถานะสำเร็จ',
            'note_added' => 'เพิ่มบันทึกสำเร็จ',
            'photo_uploaded' => 'อัพโหลดรูปสำเร็จ',
            'part_used' => 'เบิกอะไหล่สำเร็จ'
        ][$success] ?? 'ดำเนินการสำเร็จ'; ?>
    </div>
<?php endif; ?>

<!-- Back Link -->
<a href="index.php" class="text-blue-600 hover:text-blue-700 text-sm mb-4 inline-flex items-center gap-1">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
    </svg>
    กลับรายการงาน
</a>

<!-- Job Header Card -->
<div
    class="bg-white rounded-xl shadow-md p-5 mb-6 <?php echo $job['job_type'] === 'urgent' ? 'border-l-4 border-red-500' : ''; ?>">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="text-2xl font-bold text-gray-800">#
                    <?php echo str_pad($job['job_id'], 5, '0', STR_PAD_LEFT); ?>
                </span>
                <?php if ($job['job_type'] === 'urgent'): ?>
                    <span class="px-2 py-0.5 bg-red-100 text-red-700 text-sm font-medium rounded-full">🔥 ด่วน</span>
                <?php elseif ($job['job_type'] === 'appointment'): ?>
                    <span class="px-2 py-0.5 bg-purple-100 text-purple-700 text-sm font-medium rounded-full">📅
                        นัดหมาย</span>
                <?php endif; ?>
                <span
                    class="px-3 py-1 rounded-full text-sm font-medium <?php echo $job['job_category'] === 'repair' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'; ?>">
                    <?php echo $job['job_category'] === 'repair' ? '🔧 งานซ่อม' : '🔄 งานบริการ'; ?>
                </span>
            </div>

            <!-- Vehicle Info -->
            <div class="flex items-center gap-3 mb-2">
                <span class="font-mono font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded text-lg">
                    <?php echo htmlspecialchars($job['license_plate']); ?>
                </span>
                <span class="text-gray-600">
                    <?php echo htmlspecialchars(($job['brand'] ?? '') . ' ' . ($job['model'] ?? '')); ?>
                    <?php if ($job['color']): ?>
                        <span class="text-gray-400">สี
                            <?php echo htmlspecialchars($job['color']); ?>
                        </span>
                    <?php endif; ?>
                </span>
            </div>

            <!-- Customer -->
            <div class="text-gray-600">
                👤
                <?php echo htmlspecialchars($job['member_first_name'] . ' ' . $job['member_last_name']); ?>
                <a href="tel:<?php echo $job['member_phone']; ?>" class="text-blue-600 ml-2">📞
                    <?php echo htmlspecialchars($job['member_phone']); ?>
                </a>
            </div>

            <?php if ($job['job_category'] === 'repair' && !empty($job['symptom'])): ?>
                <!-- Symptom -->
                <div class="mt-3 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                    <div class="text-xs text-orange-600 font-medium mb-1">🔍 อาการเบื้องต้น</div>
                    <div class="text-gray-700"><?php echo htmlspecialchars($job['symptom']); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Status & Action -->
        <div class="text-right">
            <div class="text-sm text-gray-500 mb-1">สถานะปัจจุบัน</div>
            <div class="text-xl font-bold text-blue-600 mb-3">
                <?php echo htmlspecialchars($job['status_name'] ?? $job['status']); ?>
            </div>

            <?php if ($isAssigned && isset($statusFlow[$job['status']])): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="status" value="<?php echo $statusFlow[$job['status']]['next']; ?>">
                    <button type="submit"
                        class="<?php echo $statusFlow[$job['status']]['color']; ?> hover:opacity-90 text-white px-6 py-2.5 rounded-lg font-medium">
                        ✓
                        <?php echo $statusFlow[$job['status']]['label']; ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($job['description'] ?? '')): ?>
        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
            <div class="text-sm text-gray-500 mb-1">รายละเอียดงาน</div>
            <div class="text-gray-700">
                <?php echo nl2br(htmlspecialchars($job['description'])); ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Left Column -->
    <div class="space-y-6">
        <!-- Services -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
                <span class="font-semibold">
                    <?php echo $job['job_category'] === 'repair' ? '🔧 ค่าแรงและรายการซ่อม' : '✨ รายการบริการ'; ?>
                </span>
            </div>

            <?php if (!empty($services)): ?>
                <div class="divide-y">
                    <?php foreach ($services as $s): ?>
                        <div class="p-3 flex justify-between items-center">
                            <span>
                                <?php echo htmlspecialchars($s['service_name']); ?>
                                <span class="text-gray-500">x<?php echo $s['quantity']; ?></span>
                            </span>
                            <span
                                class="text-green-600 font-medium">฿<?php echo number_format($s['price'] * $s['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($isAssigned && !in_array($job['status'], ['CANCELLED', 'COMPLETED', 'DELIVERED', 'WAIT_PICKUP'])): ?>
                <!-- Inline Add Service Form -->
                <form method="POST" class="p-3 bg-blue-50 border-t">
                    <input type="hidden" name="action" value="add_service">
                    <div class="flex gap-2 items-end">
                        <div class="flex-1">
                            <select name="service_id" id="inlineServiceSelect" required
                                class="w-full px-3 py-2 border rounded-lg text-sm" onchange="updateInlineServicePrice()">
                                <option value="">+ เลือกบริการ/ค่าแรง...</option>
                                <?php foreach ($availableServices as $s): ?>
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
            <?php endif; ?>
        </div>

        <!-- Parts Used -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <span class="font-semibold">🔩 อะไหล่ที่ใช้</span>
            </div>

            <?php if (!empty($parts)): ?>
                <div class="divide-y">
                    <?php foreach ($parts as $p): ?>
                        <div class="p-3 flex justify-between items-center">
                            <span>
                                <?php echo htmlspecialchars($p['part_name']); ?>
                                <span class="text-gray-500">x<?php echo $p['quantity']; ?></span>
                            </span>
                            <span
                                class="text-green-600 font-medium">฿<?php echo number_format($p['sell_price'] * $p['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($isAssigned && !in_array($job['status'], ['CANCELLED', 'COMPLETED', 'DELIVERED', 'WAIT_PICKUP'])): ?>
                <!-- Inline Add Part Form -->
                <form method="POST" class="p-3 bg-orange-50 border-t rounded-b-xl">
                    <input type="hidden" name="action" value="use_part">
                    <input type="hidden" name="part_id" id="techPartId">
                    <div class="flex gap-2 items-end">
                        <div class="flex-1 relative">
                            <input type="text" id="techPartSearch" placeholder="พิมพ์ชื่ออะไหล่เพื่อค้นหา..."
                                class="w-full px-3 py-2 border rounded-lg text-sm" autocomplete="off"
                                oninput="searchTechParts(this.value)">
                            <div id="techPartResults"
                                class="absolute z-10 w-full bg-white border rounded-lg shadow-xl mt-1 max-h-48 overflow-y-auto hidden">
                            </div>
                        </div>
                        <div class="w-20">
                            <input type="number" name="quantity" value="1" min="1" placeholder="จำนวน"
                                class="w-full px-3 py-2 border rounded-lg text-sm text-center">
                        </div>
                        <button type="submit" id="btnTechAddPart" disabled
                            class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                            เบิก
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Notes -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
                <span class="font-semibold">📝 บันทึกการทำงาน</span>
            </div>

            <?php if (!empty($notes)): ?>
                <div class="divide-y max-h-48 overflow-y-auto">
                    <?php foreach ($notes as $n): ?>
                        <div class="px-4 py-2 flex items-start gap-2">
                            <span class="text-gray-400">•</span>
                            <div class="flex-1">
                                <span class="text-gray-700"><?php echo htmlspecialchars($n['note_text']); ?></span>
                                <span class="text-xs text-gray-400 ml-2">
                                    - <?php echo htmlspecialchars($n['first_name']); ?>
                                    <?php echo date('d/m H:i', strtotime($n['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($isAssigned && !in_array($job['status'], ['CANCELLED', 'COMPLETED', 'DELIVERED', 'WAIT_PICKUP'])): ?>
                <!-- Inline Add Note Form -->
                <form method="POST" class="p-3 bg-gray-50 border-t">
                    <input type="hidden" name="action" value="add_note">
                    <div class="flex gap-2">
                        <input type="text" name="note_text" placeholder="+ เพิ่มบันทึก..." required
                            class="flex-1 px-3 py-2 border rounded-lg text-sm">
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            บันทึก
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column - Photos -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 border-b bg-gray-50 font-semibold">📷 รูปภาพงาน</div>

        <?php if ($isAssigned && !in_array($job['status'], ['CANCELLED', 'COMPLETED', 'DELIVERED', 'WAIT_PICKUP'])): ?>
            <form method="POST" enctype="multipart/form-data" class="p-4 border-b">
                <input type="hidden" name="action" value="upload_photo">
                <div class="flex flex-wrap items-center gap-3">
                    <select name="photo_type" class="px-3 py-2 border rounded-lg text-sm">
                        <option value="before">ก่อนทำ</option>
                        <option value="during" selected>ระหว่างทำ</option>
                        <option value="after">หลังทำ</option>
                    </select>
                    <input type="file" name="photo" accept="image/*" required class="flex-1 min-w-0 text-sm">
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm whitespace-nowrap">อัพโหลด</button>
                </div>
            </form>
        <?php endif; ?>

        <!-- Photo Gallery -->
        <?php
        $photosByType = ['before' => [], 'during' => [], 'after' => []];
        foreach ($photos as $p) {
            $photosByType[$p['photo_type']][] = $p;
        }
        ?>

        <div class="p-4 space-y-4">
            <?php foreach (['before' => 'ก่อนทำ', 'during' => 'ระหว่างทำ', 'after' => 'หลังทำ'] as $type => $label): ?>
                <div>
                    <div class="text-sm font-medium text-gray-600 mb-2">
                        <?php echo $label; ?>
                    </div>
                    <?php if (empty($photosByType[$type])): ?>
                        <div class="text-xs text-gray-400">ไม่มีรูป</div>
                    <?php else: ?>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($photosByType[$type] as $photo): ?>
                                <a href="<?php echo htmlspecialchars($photo['image_url']); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($photo['image_url']); ?>"
                                        class="w-20 h-20 object-cover rounded-lg">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Part Modal -->
<div id="partModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('partModal').classList.add('hidden')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between p-5 border-b">
                <h2 class="text-lg font-semibold">เบิกอะไหล่</h2>
                <button onclick="document.getElementById('partModal').classList.add('hidden')"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="action" value="use_part">
                <div>
                    <label class="block text-sm font-medium mb-1">อะไหล่</label>
                    <select name="part_id" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="">เลือกอะไหล่...</option>
                        <?php foreach ($availableParts as $p): ?>
                            <option value="<?php echo $p['part_id']; ?>">
                                <?php echo htmlspecialchars($p['part_name'] . ' (' . $p['part_number'] . ') - คงเหลือ ' . $p['stock_qty']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">จำนวน</label>
                    <input type="number" name="quantity" value="1" min="1" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">เบิก</button>
                    <button type="button" onclick="document.getElementById('partModal').classList.add('hidden')"
                        class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Service Modal -->
<div id="addServiceModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('addServiceModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 class="text-lg font-semibold">
                    เพิ่ม<?php echo $job['job_category'] === 'repair' ? 'ค่าแรง/บริการ' : 'บริการ'; ?></h3>
                <button onclick="document.getElementById('addServiceModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="action" value="add_service">
                <div>
                    <label class="block text-sm font-medium mb-1">บริการ/ค่าแรง</label>
                    <select name="service_id" id="techServiceSelect" required class="w-full px-4 py-2 border rounded-lg"
                        onchange="updateTechServicePrice()">
                        <option value="">เลือก...</option>
                        <?php foreach ($availableServices as $s): ?>
                            <option value="<?php echo $s['service_id']; ?>"
                                data-price="<?php echo $s['standard_price']; ?>">
                                <?php echo htmlspecialchars($s['service_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">ราคา</label>
                    <input type="number" name="custom_price" id="techServicePrice" step="0.01" min="0" required
                        class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">จำนวน</label>
                    <input type="number" name="quantity" value="1" min="1" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">เพิ่ม</button>
                    <button type="button" onclick="document.getElementById('addServiceModal').classList.add('hidden')"
                        class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function updateInlineServicePrice() {
        const select = document.getElementById('inlineServiceSelect');
        const priceInput = document.getElementById('inlineServicePrice');
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.value) {
            priceInput.value = selectedOption.getAttribute('data-price');
        } else {
            priceInput.value = '';
        }
    }

    // Technician Part Search Logic
    const techParts = [
        <?php foreach ($availableParts as $p): ?>
                            { id: <?php echo $p['part_id']; ?>, name: "<?php echo addslashes($p['part_name']); ?>", stock: <?php echo $p['stock_qty']; ?> },
        <?php endforeach; ?>
    ];

    function searchTechParts(query) {
        const results = document.getElementById('techPartResults');
        if (query.length < 1) {
            results.classList.add('hidden');
            return;
        }

        const filtered = techParts.filter(p => p.name.toLowerCase().includes(query.toLowerCase()));

        if (filtered.length === 0) {
            results.innerHTML = '<div class="p-3 text-gray-500 text-center text-sm">ไม่พบอะไหล่</div>';
        } else {
            results.innerHTML = filtered.map(p =>
                `<div class="p-3 hover:bg-blue-50 cursor-pointer border-b last:border-0 text-sm" 
                      onclick="selectTechPart(${p.id}, '${p.name}', ${p.stock})">
                    <div class="font-medium">${p.name}</div>
                    <div class="text-xs text-gray-500">คงเหลือ ${p.stock}</div>
                </div>`
            ).join('');
        }
        results.classList.remove('hidden');
    }

    function selectTechPart(id, name, stock) {
        document.getElementById('techPartId').value = id;
        document.getElementById('techPartSearch').value = name; // Show name in input
        document.getElementById('techPartResults').classList.add('hidden');
        document.getElementById('btnTechAddPart').disabled = false;

        // Focus quantity
        document.querySelector('input[name="quantity"]').focus();
    }

    // Hide results on click outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#techPartSearch') && !e.target.closest('#techPartResults')) {
            document.getElementById('techPartResults')?.classList.add('hidden');
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>