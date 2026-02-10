<?php
/**
 * Member Job Detail - รายละเอียดงาน
 */

$jobId = $_GET['id'] ?? 0;

if (!$jobId) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'รายละเอียดงาน';
require_once 'includes/header.php';

// Get job details
$job = $pdo->prepare("SELECT jo.*, 
    v.license_plate, v.brand, v.model, v.year,
    js.status_name,
    u.first_name as tech_name,
    i.invoice_id, i.net_amount, i.payment_status
    FROM job_orders jo
    JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN job_status js ON jo.status = js.status_code AND jo.job_category = js.job_category
    LEFT JOIN users u ON jo.assigned_to = u.user_id
    LEFT JOIN invoices i ON jo.job_id = i.job_id
    WHERE jo.job_id = ? AND jo.member_id = ?");
$job->execute([$jobId, $currentMember['member_id']]);
$job = $job->fetch();

if (!$job) {
    header('Location: index.php');
    exit;
}

// Get services
$services = $pdo->prepare("SELECT js.*, s.service_name, s.standard_price FROM job_services js JOIN service_items s ON js.service_id = s.service_id WHERE js.job_id = ?");
$services->execute([$jobId]);
$services = $services->fetchAll();

// Get parts
$parts = $pdo->prepare("SELECT jp.*, p.part_name, p.sell_price FROM job_parts jp JOIN spare_parts p ON jp.part_id = p.part_id WHERE jp.job_id = ?");
$parts->execute([$jobId]);
$parts = $parts->fetchAll();

// Get photos
$photos = $pdo->prepare("SELECT * FROM job_images WHERE job_id = ? ORDER BY image_type, uploaded_at");
$photos->execute([$jobId]);
$photos = $photos->fetchAll();

// Get timeline
$timeline = $pdo->prepare("SELECT t.*, 
    js_new.status_name as status_name
    FROM job_timeline t
    LEFT JOIN job_status js_new ON t.new_status = js_new.status_code AND ? = js_new.job_category
    WHERE t.job_id = ? 
    ORDER BY t.changed_at DESC");
$timeline->execute([$job['job_category'], $jobId]);
$timeline = $timeline->fetchAll();

// Calculate totals
$serviceTotal = 0;
foreach ($services as $s) {
    $serviceTotal += $s['standard_price'] * $s['quantity'];
}
$partsTotal = 0;
foreach ($parts as $p) {
    $partsTotal += $p['sell_price'] * $p['quantity'];
}

// Status colors
$statusColors = [
    'RECEIVED' => 'bg-gray-100 text-gray-700 border-gray-300',
    'INSPECTING' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
    'WAIT_PART' => 'bg-orange-100 text-orange-700 border-orange-300',
    'IN_PROGRESS' => 'bg-blue-100 text-blue-700 border-blue-300',
    'COMPLETED' => 'bg-green-100 text-green-700 border-green-300',
    'WAIT_PAYMENT' => 'bg-purple-100 text-purple-700 border-purple-300',
    'DELIVERED' => 'bg-emerald-100 text-emerald-700 border-emerald-300',
];
?>

<!-- Back -->
<a href="history.php" class="text-blue-600 hover:text-blue-700 text-sm mb-4 inline-flex items-center gap-1">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
    </svg>
    กลับประวัติงาน
</a>

<!-- Header -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
    <div class="p-6 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm opacity-80">งาน #
                    <?php echo str_pad($job['job_id'], 5, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="text-2xl font-bold font-mono">
                    <?php echo htmlspecialchars($job['license_plate']); ?>
                </div>
                <div class="text-sm opacity-80">
                    <?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?>
                </div>
            </div>
            <div class="text-right">
                <div
                    class="inline-block px-4 py-2 rounded-lg <?php echo $statusColors[$job['status']] ?? 'bg-white/20'; ?> font-medium">
                    <?php echo htmlspecialchars($job['status_name']); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <div>
            <div class="text-gray-400 text-sm">ประเภท</div>
            <div class="font-medium">
                <?php echo $job['job_category'] === 'repair' ? '🔧 ซ่อม' : '🛠️ บริการ'; ?>
            </div>
        </div>
        <div>
            <div class="text-gray-400 text-sm">เปิดงาน</div>
            <div class="font-medium">
                <?php echo date('d/m/Y', strtotime($job['opened_date'])); ?>
            </div>
        </div>
        <div>
            <div class="text-gray-400 text-sm">ช่าง</div>
            <div class="font-medium">
                <?php echo $job['tech_name'] ? htmlspecialchars($job['tech_name']) : '-'; ?>
            </div>
        </div>
        <div>
            <div class="text-gray-400 text-sm">เสร็จสิ้น</div>
            <div class="font-medium">
                <?php echo $job['completed_date'] ? date('d/m/Y', strtotime($job['completed_date'])) : '-'; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Services -->
        <?php if (!empty($services)): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-4 border-b bg-gray-50 font-semibold">🛠️ บริการ</div>
                <div class="divide-y">
                    <?php foreach ($services as $s): ?>
                        <div class="p-4 flex justify-between">
                            <div>
                                <div class="font-medium">
                                    <?php echo htmlspecialchars($s['service_name']); ?>
                                </div>
                                <div class="text-sm text-gray-400">x
                                    <?php echo $s['quantity']; ?>
                                </div>
                            </div>
                            <div class="font-medium">฿
                                <?php echo number_format($s['standard_price'] * $s['quantity'], 0); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Parts -->
        <?php if (!empty($parts)): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-4 border-b bg-gray-50 font-semibold">🔩 อะไหล่</div>
                <div class="divide-y">
                    <?php foreach ($parts as $p): ?>
                        <div class="p-4 flex justify-between">
                            <div>
                                <div class="font-medium">
                                    <?php echo htmlspecialchars($p['part_name']); ?>
                                </div>
                                <div class="text-sm text-gray-400">x
                                    <?php echo $p['quantity']; ?>
                                </div>
                            </div>
                            <div class="font-medium">฿
                                <?php echo number_format($p['sell_price'] * $p['quantity'], 0); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Photos -->
        <?php if (!empty($photos)): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-4 border-b bg-gray-50 font-semibold">📷 รูปภาพ</div>
                <div class="p-4">
                    <?php
                    $photoTypes = ['before' => 'ก่อนซ่อม', 'during' => 'ระหว่างซ่อม', 'after' => 'หลังซ่อม'];
                    $grouped = [];
                    foreach ($photos as $photo) {
                        $grouped[$photo['image_type'] ?? 'other'][] = $photo;
                    }
                    ?>
                    <?php foreach ($photoTypes as $type => $label): ?>
                        <?php if (!empty($grouped[$type])): ?>
                            <div class="mb-4">
                                <div class="text-sm font-medium text-gray-500 mb-2">
                                    <?php echo $label; ?>
                                </div>
                                <div class="flex gap-2 overflow-x-auto">
                                    <?php foreach ($grouped[$type] as $photo): ?>
                                        <img src="<?php echo htmlspecialchars($photo['image_url']); ?>"
                                            class="w-24 h-24 object-cover rounded-lg" alt="<?php echo $label; ?>">
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Cost Summary -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gray-50 font-semibold">💰 สรุปค่าใช้จ่าย</div>
            <div class="p-4">
                <div class="flex justify-between text-sm mb-2">
                    <span>ค่าบริการ</span>
                    <span>฿
                        <?php echo number_format($serviceTotal, 0); ?>
                    </span>
                </div>
                <div class="flex justify-between text-sm mb-2">
                    <span>ค่าอะไหล่</span>
                    <span>฿
                        <?php echo number_format($partsTotal, 0); ?>
                    </span>
                </div>
                <div class="flex justify-between font-bold text-lg border-t pt-2 mt-2">
                    <span>รวม</span>
                    <span class="text-blue-600">฿
                        <?php echo number_format($serviceTotal + $partsTotal, 0); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <?php if (!empty($timeline)): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-4 border-b bg-gray-50 font-semibold">📋 ประวัติสถานะ</div>
                <div class="p-4 space-y-3">
                    <?php foreach ($timeline as $t): ?>
                        <div class="flex gap-3">
                            <div class="w-2 h-2 rounded-full bg-blue-500 mt-2 flex-shrink-0"></div>
                            <div class="flex-1">
                                <div class="text-sm font-medium">
                                    <?php echo htmlspecialchars($t['status_name'] ?? $t['new_status']); ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?php echo date('d/m/Y H:i', strtotime($t['changed_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Receipt -->
        <?php if ($job['invoice_id'] && $job['payment_status'] === 'paid'): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-4 border-b bg-green-50">
                    <div class="flex items-center gap-2 text-green-600 font-semibold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        ชำระเงินแล้ว
                    </div>
                </div>
                <div class="p-4">
                    <a href="receipt.php?job_id=<?php echo $job['job_id']; ?>"
                        class="w-full inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">
                        🧾 ดูใบเสร็จ
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>