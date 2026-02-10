<?php
/**
 * Technician Dashboard - รายการงานที่ได้รับมอบหมาย
 */

require_once '../config/database.php';
require_once '../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();
$currentUser = getCurrentUser();
$technicianId = $currentUser['user_id'];

// Get filter
$filter = $_GET['filter'] ?? 'all';
$category = $_GET['category'] ?? '';
$jobType = $_GET['type'] ?? '';

// Build query
$whereClause = "WHERE jo.assigned_to = ?";
$params = [$technicianId];

// Status filter
if ($filter === 'new') {
    $whereClause .= " AND jo.status = 'RECEIVED'";
} elseif ($filter === 'working') {
    $whereClause .= " AND jo.status = 'IN_PROGRESS'";
} elseif ($filter === 'done') {
    $whereClause .= " AND jo.status IN ('COMPLETED', 'DELIVERED')";
}

// Category filter
if ($category === 'repair') {
    $whereClause .= " AND jo.job_category = 'repair'";
} elseif ($category === 'service') {
    $whereClause .= " AND jo.job_category = 'service'";
}

// Job type filter
if ($jobType === 'urgent') {
    $whereClause .= " AND jo.job_type = 'urgent'";
} elseif ($jobType === 'appointment') {
    $whereClause .= " AND jo.job_type = 'appointment'";
}

// Get jobs
$sql = "SELECT jo.*, 
    v.license_plate, v.brand, v.model,
    m.first_name as member_first_name, m.last_name as member_last_name, m.phone as member_phone,
    js.status_name
    FROM job_orders jo
    LEFT JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN members m ON jo.member_id = m.member_id
    LEFT JOIN job_status js ON jo.status = js.status_code AND jo.job_category = js.job_category
    $whereClause
    ORDER BY 
        CASE jo.job_type 
            WHEN 'urgent' THEN 1 
            WHEN 'appointment' THEN 2 
            ELSE 3 
        END,
        jo.opened_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Count by status
$countStmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status = 'RECEIVED' THEN 1 ELSE 0 END) as new_count,
        SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as working_count,
        SUM(CASE WHEN status IN ('COMPLETED', 'DELIVERED') THEN 1 ELSE 0 END) as done_count,
        COUNT(*) as total
    FROM job_orders WHERE assigned_to = ?
");
$countStmt->execute([$technicianId]);
$counts = $countStmt->fetch();

$pageTitle = 'งานของฉัน';
require_once 'includes/header.php';
?>

<!-- Stats Cards -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <a href="?filter=new"
        class="bg-white rounded-xl shadow p-4 text-center hover:shadow-lg transition <?php echo $filter === 'new' ? 'ring-2 ring-orange-500' : ''; ?>">
        <div class="text-3xl font-bold text-orange-600">
            <?php echo $counts['new_count']; ?>
        </div>
        <div class="text-sm text-gray-500">งานใหม่</div>
    </a>
    <a href="?filter=working"
        class="bg-white rounded-xl shadow p-4 text-center hover:shadow-lg transition <?php echo $filter === 'working' ? 'ring-2 ring-blue-500' : ''; ?>">
        <div class="text-3xl font-bold text-blue-600">
            <?php echo $counts['working_count']; ?>
        </div>
        <div class="text-sm text-gray-500">กำลังทำ</div>
    </a>
    <a href="?filter=done"
        class="bg-white rounded-xl shadow p-4 text-center hover:shadow-lg transition <?php echo $filter === 'done' ? 'ring-2 ring-green-500' : ''; ?>">
        <div class="text-3xl font-bold text-green-600">
            <?php echo $counts['done_count']; ?>
        </div>
        <div class="text-sm text-gray-500">เสร็จแล้ว</div>
    </a>
</div>

<!-- Filter Tabs -->
<div class="flex items-center gap-2 mb-4 overflow-x-auto pb-2">
    <a href="?"
        class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap <?php echo !$filter && !$category && !$jobType ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'; ?>">
        ทั้งหมด
    </a>
    <span class="text-gray-300">|</span>
    <a href="?category=repair"
        class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap <?php echo $category === 'repair' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'; ?>">
        🔧 งานซ่อม
    </a>
    <a href="?category=service"
        class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap <?php echo $category === 'service' ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'; ?>">
        🔄 งานบริการ
    </a>
    <span class="text-gray-300">|</span>
    <a href="?type=urgent"
        class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap <?php echo $jobType === 'urgent' ? 'bg-red-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'; ?>">
        🔥 ด่วน
    </a>
    <a href="?type=appointment"
        class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap <?php echo $jobType === 'appointment' ? 'bg-purple-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'; ?>">
        📅 นัดหมาย
    </a>
</div>

<!-- Job List -->
<?php
// Separate jobs into active and completed
$activeJobs = [];
$completedJobs = [];
foreach ($jobs as $job) {
    if (in_array($job['status'], ['COMPLETED', 'DELIVERED'])) {
        $completedJobs[] = $job;
    } else {
        $activeJobs[] = $job;
    }
}
?>

<?php if (empty($jobs)): ?>
    <div class="bg-white rounded-xl shadow p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
            </path>
        </svg>
        <p class="text-gray-500">ไม่มีงานที่ได้รับมอบหมาย</p>
    </div>
<?php else: ?>

    <!-- Active Jobs Section -->
    <?php if (!empty($activeJobs)): ?>
        <div class="mb-8">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-3 h-3 bg-blue-500 rounded-full animate-pulse"></div>
                <h2 class="text-lg font-semibold text-gray-800">งานที่ต้องทำ</h2>
                <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full"><?php echo count($activeJobs); ?>
                    งาน</span>
            </div>
            <div class="space-y-3">
                <?php foreach ($activeJobs as $job): ?>
                    <?php
                    $isUrgent = $job['job_type'] === 'urgent';
                    $isAppointment = $job['job_type'] === 'appointment';
                    $statusColors = [
                        'RECEIVED' => 'bg-orange-100 text-orange-700 border-orange-200',
                        'INSPECTING' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                        'WAIT_PART' => 'bg-purple-100 text-purple-700 border-purple-200',
                        'IN_PROGRESS' => 'bg-blue-100 text-blue-700 border-blue-200',
                    ];
                    $statusColor = $statusColors[$job['status']] ?? 'bg-gray-100 text-gray-700';
                    ?>
                    <a href="job.php?id=<?php echo $job['job_id']; ?>"
                        class="block bg-white rounded-xl shadow hover:shadow-lg transition p-4 <?php echo $isUrgent ? 'border-l-4 border-red-500' : ''; ?>">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <!-- Job Header -->
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="font-mono font-bold text-gray-800">#
                                        <?php echo str_pad($job['job_id'], 5, '0', STR_PAD_LEFT); ?>
                                    </span>
                                    <?php if ($isUrgent): ?>
                                        <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-medium rounded-full">🔥
                                            ด่วน</span>
                                    <?php elseif ($isAppointment): ?>
                                        <span class="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-medium rounded-full">📅
                                            นัดหมาย</span>
                                    <?php endif; ?>
                                    <span
                                        class="px-2 py-0.5 text-xs font-medium rounded-full <?php echo $job['job_category'] === 'repair' ? 'bg-orange-50 text-orange-600' : 'bg-blue-50 text-blue-600'; ?>">
                                        <?php echo $job['job_category'] === 'repair' ? '🔧 ซ่อม' : '🔄 บริการ'; ?>
                                    </span>
                                </div>

                                <!-- Vehicle -->
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded text-sm">
                                        <?php echo htmlspecialchars($job['license_plate']); ?>
                                    </span>
                                    <span class="text-gray-600 text-sm">
                                        <?php echo htmlspecialchars(($job['brand'] ?? '') . ' ' . ($job['model'] ?? '')); ?>
                                    </span>
                                </div>

                                <!-- Customer -->
                                <div class="text-sm text-gray-500">
                                    👤 <?php echo htmlspecialchars($job['member_first_name'] . ' ' . $job['member_last_name']); ?>
                                    <span class="text-gray-400 ml-2">📞 <?php echo htmlspecialchars($job['member_phone']); ?></span>
                                </div>
                            </div>

                            <!-- Status & Arrow -->
                            <div class="flex flex-col items-end gap-2">
                                <span class="px-3 py-1 rounded-full text-xs font-medium border <?php echo $statusColor; ?>">
                                    <?php echo htmlspecialchars($job['status_name'] ?? $job['status']); ?>
                                </span>
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Completed Jobs Section -->
    <?php if (!empty($completedJobs)): ?>
        <div class="opacity-80">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <h2 class="text-lg font-semibold text-gray-600">งานส่งมอบแล้ว</h2>
                <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full"><?php echo count($completedJobs); ?>
                    งาน</span>
            </div>
            <div class="space-y-3">
                <?php foreach ($completedJobs as $job): ?>
                    <?php
                    $statusColors = [
                        'COMPLETED' => 'bg-green-100 text-green-700 border-green-200',
                        'DELIVERED' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                    ];
                    $statusColor = $statusColors[$job['status']] ?? 'bg-gray-100 text-gray-700';
                    ?>
                    <a href="job.php?id=<?php echo $job['job_id']; ?>"
                        class="block bg-white rounded-xl shadow hover:shadow-lg transition p-4 border-l-4 border-green-400">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <!-- Job Header -->
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="font-mono font-bold text-gray-600">#
                                        <?php echo str_pad($job['job_id'], 5, '0', STR_PAD_LEFT); ?>
                                    </span>
                                    <span
                                        class="px-2 py-0.5 text-xs font-medium rounded-full <?php echo $job['job_category'] === 'repair' ? 'bg-orange-50 text-orange-600' : 'bg-blue-50 text-blue-600'; ?>">
                                        <?php echo $job['job_category'] === 'repair' ? '🔧 ซ่อม' : '🔄 บริการ'; ?>
                                    </span>
                                </div>

                                <!-- Vehicle -->
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded text-sm">
                                        <?php echo htmlspecialchars($job['license_plate']); ?>
                                    </span>
                                    <span class="text-gray-600 text-sm">
                                        <?php echo htmlspecialchars(($job['brand'] ?? '') . ' ' . ($job['model'] ?? '')); ?>
                                    </span>
                                </div>

                                <!-- Customer -->
                                <div class="text-sm text-gray-500">
                                    👤 <?php echo htmlspecialchars($job['member_first_name'] . ' ' . $job['member_last_name']); ?>
                                    <span class="text-gray-400 ml-2">📞 <?php echo htmlspecialchars($job['member_phone']); ?></span>
                                </div>
                            </div>

                            <!-- Status & Arrow -->
                            <div class="flex flex-col items-end gap-2">
                                <span class="px-3 py-1 rounded-full text-xs font-medium border <?php echo $statusColor; ?>">
                                    ✓ <?php echo htmlspecialchars($job['status_name'] ?? $job['status']); ?>
                                </span>
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>