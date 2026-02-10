<?php
/**
 * Member History - ประวัติงานทั้งหมด
 */

$pageTitle = 'ประวัติงาน';
require_once 'includes/header.php';

// Filter
$vehicleFilter = $_GET['vehicle'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

// Build query
$sql = "SELECT jo.*, 
    v.license_plate, v.brand, v.model,
    js.status_name,
    i.invoice_id, i.net_amount, i.payment_status
    FROM job_orders jo
    JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN job_status js ON jo.status = js.status_code AND jo.job_category = js.job_category
    LEFT JOIN invoices i ON jo.job_id = i.job_id
    WHERE jo.member_id = ?";
$params = [$currentMember['member_id']];

if ($vehicleFilter) {
    $sql .= " AND jo.vehicle_id = ?";
    $params[] = $vehicleFilter;
}
if ($categoryFilter) {
    $sql .= " AND jo.job_category = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY jo.opened_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Status colors
$statusColors = [
    'RECEIVED' => 'bg-gray-100 text-gray-700',
    'INSPECTING' => 'bg-yellow-100 text-yellow-700',
    'WAIT_PART' => 'bg-orange-100 text-orange-700',
    'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
    'COMPLETED' => 'bg-green-100 text-green-700',
    'WAIT_PAYMENT' => 'bg-purple-100 text-purple-700',
    'DELIVERED' => 'bg-emerald-100 text-emerald-700',
    'CANCELLED' => 'bg-red-100 text-red-700',
];
?>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6">
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">รถ</label>
            <select name="vehicle" class="px-4 py-2 border rounded-lg">
                <option value="">ทั้งหมด</option>
                <?php foreach ($memberVehicles as $v): ?>
                    <option value="<?php echo $v['vehicle_id']; ?>" <?php echo $vehicleFilter == $v['vehicle_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($v['license_plate'] . ' - ' . $v['brand']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">ประเภท</label>
            <select name="category" class="px-4 py-2 border rounded-lg">
                <option value="">ทั้งหมด</option>
                <option value="repair" <?php echo $categoryFilter === 'repair' ? 'selected' : ''; ?>>🔧 ซ่อม</option>
                <option value="service" <?php echo $categoryFilter === 'service' ? 'selected' : ''; ?>>🛠️ บริการ
                </option>
            </select>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
            🔍 ค้นหา
        </button>
    </form>
</div>

<!-- Jobs List -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b">
        <h2 class="font-semibold text-lg">📜 ประวัติงานทั้งหมด (
            <?php echo count($jobs); ?> รายการ)
        </h2>
    </div>

    <?php if (empty($jobs)): ?>
        <div class="p-12 text-center text-gray-400">
            ไม่พบประวัติงาน
        </div>
    <?php else: ?>
        <div class="divide-y">
            <?php foreach ($jobs as $job): ?>
                <a href="job.php?id=<?php echo $job['job_id']; ?>" class="block p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="text-2xl">
                                <?php echo $job['job_category'] === 'repair' ? '🔧' : '🛠️'; ?>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-blue-600">
                                        <?php echo htmlspecialchars($job['license_plate']); ?>
                                    </span>
                                    <span class="text-gray-400">·</span>
                                    <span class="text-gray-500 text-sm">
                                        <?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-400">
                                    <?php echo date('d/m/Y', strtotime($job['opened_date'])); ?>
                                    <?php if ($job['completed_date']): ?>
                                        →
                                        <?php echo date('d/m/Y', strtotime($job['completed_date'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span
                                class="inline-block px-3 py-1 rounded-full text-xs font-medium <?php echo $statusColors[$job['status']] ?? 'bg-gray-100 text-gray-700'; ?>">
                                <?php echo htmlspecialchars($job['status_name']); ?>
                            </span>
                            <?php if ($job['invoice_id'] && $job['payment_status'] === 'paid'): ?>
                                <div class="text-sm font-medium text-emerald-600 mt-1">฿
                                    <?php echo number_format($job['net_amount'], 0); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>