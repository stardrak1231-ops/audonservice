<?php
/**
 * Job Orders Management - จัดการใบงาน
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

$pageTitle = 'จัดการใบงาน';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Filters
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$jobType = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$where = [];
$params = [];

if ($category) {
    $where[] = "j.job_category = ?";
    $params[] = $category;
}
if ($status) {
    $where[] = "j.status = ?";
    $params[] = $status;
}
if ($jobType) {
    $where[] = "j.job_type = ?";
    $params[] = $jobType;
}
if ($search) {
    $where[] = "(v.license_plate LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? " WHERE " . implode(" AND ", $where) : "";

// Get all jobs
$sql = "SELECT j.*, 
        m.first_name, m.last_name, m.phone, m.member_code,
        v.license_plate, v.brand, v.model,
        u.first_name as tech_first_name, u.last_name as tech_last_name,
        js.status_name
        FROM job_orders j
        LEFT JOIN members m ON j.member_id = m.member_id
        LEFT JOIN vehicles v ON j.vehicle_id = v.vehicle_id
        LEFT JOIN users u ON j.assigned_to = u.user_id
        LEFT JOIN job_status js ON j.status = js.status_code AND j.job_category = js.job_category
        " . $whereClause . "
        ORDER BY 
            CASE j.job_type 
                WHEN 'urgent' THEN 1 
                WHEN 'appointment' THEN 2 
                ELSE 3 
            END,
            j.opened_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allJobs = $stmt->fetchAll();

// Separate active jobs from delivered/completed jobs
$activeJobs = [];
$deliveredJobs = [];
$completedStatuses = ['DELIVERED', 'CANCELLED'];

foreach ($allJobs as $job) {
    if (in_array($job['status'], $completedStatuses)) {
        $deliveredJobs[] = $job;
    } else {
        $activeJobs[] = $job;
    }
}

// Get status list
$statusList = $pdo->query("SELECT DISTINCT status_code, status_name FROM job_status ORDER BY sort_order")->fetchAll();

// Summary counts
$countPending = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status IN ('RECEIVED','INSPECTING','WAIT_PART')")->fetchColumn();
$countInProgress = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status = 'IN_PROGRESS'")->fetchColumn();
$countToday = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE DATE(completed_date) = CURDATE()")->fetchColumn();
$countWaitPayment = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status = 'WAIT_PAYMENT'")->fetchColumn();

// Category counts for tabs
$countAll = $pdo->query("SELECT COUNT(*) FROM job_orders")->fetchColumn();
$countService = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE job_category = 'service'")->fetchColumn();
$countRepair = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE job_category = 'repair'")->fetchColumn();
?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg bg-yellow-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900">
                    <?php echo $countPending; ?>
                </div>
                <div class="text-sm text-gray-500">รอดำเนินการ</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                    </path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900">
                    <?php echo $countInProgress; ?>
                </div>
                <div class="text-sm text-gray-500">กำลังทำงาน</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900">
                    <?php echo $countToday; ?>
                </div>
                <div class="text-sm text-gray-500">เสร็จวันนี้</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                    </path>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900">
                    <?php echo $countWaitPayment; ?>
                </div>
                <div class="text-sm text-gray-500">รอชำระเงิน</div>
            </div>
        </div>
    </div>
</div>

<!-- Category Tabs -->
<div class="mb-6">
    <div class="border-b border-gray-200">
        <nav class="flex gap-4" aria-label="Tabs">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => ''])); ?>"
                class="px-4 py-3 text-sm font-medium border-b-2 <?php echo $category === '' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                📋 ทั้งหมด
                <span
                    class="ml-2 px-2 py-0.5 rounded-full text-xs <?php echo $category === '' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600'; ?>">
                    <?php echo $countAll; ?>
                </span>
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => 'service'])); ?>"
                class="px-4 py-3 text-sm font-medium border-b-2 <?php echo $category === 'service' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                🔄 งานบริการ
                <span
                    class="ml-2 px-2 py-0.5 rounded-full text-xs <?php echo $category === 'service' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600'; ?>">
                    <?php echo $countService; ?>
                </span>
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => 'repair'])); ?>"
                class="px-4 py-3 text-sm font-medium border-b-2 <?php echo $category === 'repair' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                🔧 งานซ่อม
                <span
                    class="ml-2 px-2 py-0.5 rounded-full text-xs <?php echo $category === 'repair' ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-600'; ?>">
                    <?php echo $countRepair; ?>
                </span>
            </a>
        </nav>
    </div>
</div>

<!-- Header & Filters -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <p class="text-gray-500">
            <?php
            if ($category === 'service')
                echo 'รายการงานบริการ';
            elseif ($category === 'repair')
                echo 'รายการงานซ่อม';
            else
                echo 'รายการงานทั้งหมด';
            ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                placeholder="ค้นหาทะเบียน, ชื่อ..."
                class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 w-40">
            <select name="status" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($statusList as $s): ?>
                    <option value="<?php echo $s['status_code']; ?>" <?php echo $status === $s['status_code'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['status_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">ทุกลักษณะ</option>
                <option value="urgent" <?php echo $jobType === 'urgent' ? 'selected' : ''; ?>>🔥 ด่วน</option>
                <option value="appointment" <?php echo $jobType === 'appointment' ? 'selected' : ''; ?>>📅 นัดหมาย
                </option>
            </select>
            <button type="submit" class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg">ค้นหา</button>
        </form>
        <div class="flex gap-2">
            <a href="create.php?type=service"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                งานบริการ
            </a>
            <a href="create.php?type=repair"
                class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                งานซ่อม
            </a>
        </div>
    </div>
</div>

<!-- Jobs Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">งาน</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ลูกค้า/รถ</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ประเภท</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">สถานะ</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ช่าง</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($activeJobs)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        ไม่มีงานที่กำลังดำเนินการ
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($activeJobs as $job): ?>
                    <?php include '_job_row.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-4 mb-2 text-sm text-gray-500">รวมงานกำลังดำเนินการ <?php echo count($activeJobs); ?> งาน</div>

<!-- Delivered Jobs Section (Collapsible) -->
<?php if (!empty($deliveredJobs)): ?>
<div class="mt-8">
    <button onclick="toggleDelivered()" 
            class="w-full text-left mb-3 flex items-center justify-between hover:text-gray-800 transition-colors group">
        <h3 class="text-lg font-semibold text-gray-600 group-hover:text-gray-800 flex items-center gap-2">
            <span class="w-3 h-3 bg-green-500 rounded-full"></span>
            งานที่เสร็จสิ้นแล้ว
            <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-sm"><?php echo count($deliveredJobs); ?></span>
        </h3>
        <svg id="deliveredArrow" class="w-5 h-5 text-gray-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>
    <div id="deliveredSection" class="hidden bg-white rounded-xl shadow-md overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">งาน</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ลูกค้า/รถ</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ประเภท</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">สถานะ</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ช่าง</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($deliveredJobs as $job): ?>
                    <?php include '_job_row.php'; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleDelivered() {
    const section = document.getElementById('deliveredSection');
    const arrow = document.getElementById('deliveredArrow');
    section.classList.toggle('hidden');
    arrow.classList.toggle('rotate-180');
}
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>