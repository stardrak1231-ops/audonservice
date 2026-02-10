<?php
/**
 * Jobs Report - รายงานงานซ่อม/บริการ
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

$pageTitle = 'รายงานงานซ่อม/บริการ';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Date range filter
$period = $_GET['period'] ?? 'month';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

if ($period === 'today') {
    $startDate = $endDate = date('Y-m-d');
} elseif ($period === 'week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
} elseif ($period === 'month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
} elseif ($period === 'year') {
    $startDate = date('Y-01-01');
    $endDate = date('Y-12-31');
}

// Job stats by category
$byCategory = $pdo->prepare("
    SELECT job_category, COUNT(*) as count
    FROM job_orders
    WHERE DATE(opened_date) BETWEEN ? AND ?
    GROUP BY job_category
");
$byCategory->execute([$startDate, $endDate]);
$byCategory = $byCategory->fetchAll();

// Job stats by status
$byStatus = $pdo->prepare("
    SELECT jo.status, js.status_name, COUNT(*) as count
    FROM job_orders jo
    LEFT JOIN job_status js ON jo.status = js.status_code AND jo.job_category = js.job_category
    WHERE DATE(jo.opened_date) BETWEEN ? AND ?
    GROUP BY jo.status
    ORDER BY count DESC
");
$byStatus->execute([$startDate, $endDate]);
$byStatus = $byStatus->fetchAll();

// Daily jobs
$dailyJobs = $pdo->prepare("
    SELECT DATE(opened_date) as date, COUNT(*) as count
    FROM job_orders
    WHERE DATE(opened_date) BETWEEN ? AND ?
    GROUP BY DATE(opened_date)
    ORDER BY date
");
$dailyJobs->execute([$startDate, $endDate]);
$dailyJobs = $dailyJobs->fetchAll();

// Total counts
$totalJobs = array_sum(array_column($byCategory, 'count'));
$repairCount = 0;
$serviceCount = 0;
foreach ($byCategory as $c) {
    if ($c['job_category'] === 'repair')
        $repairCount = $c['count'];
    if ($c['job_category'] === 'service')
        $serviceCount = $c['count'];
}

// Average completion time (use invoice date as fallback for completed_date)
$avgTime = $pdo->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, jo.opened_date, COALESCE(jo.completed_date, i.issued_at))) as avg_hours
    FROM job_orders jo
    LEFT JOIN invoices i ON jo.job_id = i.job_id
    WHERE jo.status IN ('COMPLETED', 'DELIVERED', 'WAIT_PAYMENT')
    AND (jo.completed_date IS NOT NULL OR i.issued_at IS NOT NULL)
    AND DATE(jo.opened_date) BETWEEN ? AND ?
");
$avgTime->execute([$startDate, $endDate]);
$avgTime = $avgTime->fetchColumn();

// Jobs by technician
$byTechnician = $pdo->prepare("
    SELECT u.first_name, u.last_name, COUNT(*) as count,
        SUM(CASE WHEN jo.status = 'DELIVERED' THEN 1 ELSE 0 END) as completed
    FROM job_orders jo
    JOIN users u ON jo.assigned_to = u.user_id
    WHERE DATE(jo.opened_date) BETWEEN ? AND ?
    GROUP BY jo.assigned_to
    ORDER BY count DESC
");
$byTechnician->execute([$startDate, $endDate]);
$byTechnician = $byTechnician->fetchAll();

$categoryNames = ['repair' => 'ซ่อม', 'service' => 'บริการ'];
?>

<!-- Report Selector -->
<div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-wrap items-center gap-4">
    <span class="font-medium text-gray-700">📊 เลือกรายงาน:</span>
    <select id="reportSelector" onchange="if(this.value) window.location.href=this.value"
        class="flex-1 min-w-[200px] px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        <option value="index.php">📋 ภาพรวม</option>
        <option value="revenue.php">💰 รายงานรายได้</option>
        <option value="jobs.php" selected>🔧 รายงานงานซ่อม/บริการ</option>
        <option value="members.php">👥 รายงานสมาชิก</option>
        <option value="services.php">⭐ รายงานบริการ</option>
        <option value="employees.php">👨‍🔧 ประสิทธิภาพพนักงาน</option>
    </select>
</div>

<!-- Period Filter -->
<div class="flex flex-wrap items-center gap-3 mb-6">
    <div class="flex bg-gray-100 rounded-lg p-1">
        <a href="?period=today"
            class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $period === 'today' ? 'bg-white shadow' : 'hover:bg-gray-200'; ?>">วันนี้</a>
        <a href="?period=week"
            class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $period === 'week' ? 'bg-white shadow' : 'hover:bg-gray-200'; ?>">สัปดาห์นี้</a>
        <a href="?period=month"
            class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $period === 'month' ? 'bg-white shadow' : 'hover:bg-gray-200'; ?>">เดือนนี้</a>
        <a href="?period=year"
            class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $period === 'year' ? 'bg-white shadow' : 'hover:bg-gray-200'; ?>">ปีนี้</a>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <input type="hidden" name="period" value="custom">
        <input type="date" name="start_date" value="<?php echo $startDate; ?>"
            class="px-3 py-2 border rounded-lg text-sm">
        <span class="text-gray-400">-</span>
        <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="px-3 py-2 border rounded-lg text-sm">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">ดู</button>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-purple-100 text-sm font-medium mb-1">งานทั้งหมด</div>
        <div class="text-3xl font-bold">
            <?php echo $totalJobs; ?>
        </div>
    </div>
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-orange-100 text-sm font-medium mb-1">งานซ่อม</div>
        <div class="text-3xl font-bold">
            <?php echo $repairCount; ?>
        </div>
    </div>
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-blue-100 text-sm font-medium mb-1">งานบริการ</div>
        <div class="text-3xl font-bold">
            <?php echo $serviceCount; ?>
        </div>
    </div>
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-green-100 text-sm font-medium mb-1">เวลาเฉลี่ย</div>
        <div class="text-3xl font-bold">
            <?php echo $avgTime ? round($avgTime) : 0; ?> ชม.
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Daily Jobs Bar Chart -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📊 จำนวนงานรายวัน</h3>
        <?php if (empty($dailyJobs)): ?>
            <div class="h-64 flex items-center justify-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="h-64">
                <canvas id="dailyChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Job Category Pie Chart -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📈 สัดส่วนประเภทงาน</h3>
        <?php if ($totalJobs == 0): ?>
            <div class="h-64 flex items-center justify-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="h-64 flex items-center justify-center">
                <canvas id="categoryChart" style="max-height: 240px;"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Status Breakdown -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📋 สถานะงาน</h3>
        <?php if (empty($byStatus)): ?>
            <div class="py-8 text-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="space-y-3">
                <?php
                $statusColors = [
                    'RECEIVED' => 'bg-blue-100 text-blue-700',
                    'IN_PROGRESS' => 'bg-yellow-100 text-yellow-700',
                    'WAIT_PART' => 'bg-orange-100 text-orange-700',
                    'COMPLETED' => 'bg-green-100 text-green-700',
                    'WAIT_PAYMENT' => 'bg-purple-100 text-purple-700',
                    'DELIVERED' => 'bg-gray-100 text-gray-700'
                ];
                foreach ($byStatus as $s):
                    $pct = $totalJobs > 0 ? ($s['count'] / $totalJobs) * 100 : 0;
                    ?>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm">
                                <?php echo htmlspecialchars($s['status_name'] ?? $s['status']); ?>
                            </span>
                            <span class="text-sm font-semibold">
                                <?php echo $s['count']; ?>
                            </span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Technician Performance -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">👨‍🔧 งานต่อช่าง</h3>
        <?php if (empty($byTechnician)): ?>
            <div class="py-8 text-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($byTechnician as $t): ?>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-sm font-medium">
                            <?php echo mb_substr($t['first_name'], 0, 1); ?>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium">
                                <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>
                            </div>
                            <div class="text-xs text-gray-500">เสร็จ
                                <?php echo $t['completed']; ?>/
                                <?php echo $t['count']; ?> งาน
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold">
                                <?php echo $t['count']; ?>
                            </div>
                            <div class="text-xs text-gray-500">งาน</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js Scripts -->
<script>
    <?php if (!empty($dailyJobs)): ?>
        new Chart(document.getElementById('dailyChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function ($d) {
                    return "'" . date('d/m', strtotime($d['date'])) . "'";
                }, $dailyJobs)); ?>],
                datasets: [{
                    label: 'จำนวนงาน',
                    data: [<?php echo implode(',', array_column($dailyJobs, 'count')); ?>],
                    backgroundColor: 'rgb(147, 51, 234)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    <?php endif; ?>

    <?php if ($totalJobs > 0): ?>
        new Chart(document.getElementById('categoryChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['งานซ่อม', 'งานบริการ'],
                datasets: [{
                    data: [<?php echo $repairCount; ?>, <?php echo $serviceCount; ?>],
                    backgroundColor: ['rgb(249, 115, 22)', 'rgb(59, 130, 246)']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    <?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>