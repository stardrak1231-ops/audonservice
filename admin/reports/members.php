<?php
/**
 * Members Report - รายงานสมาชิก
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

$pageTitle = 'รายงานสมาชิก';
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

// Total members
$totalMembers = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();

// New members in period
$newMembers = $pdo->prepare("
    SELECT COUNT(*) FROM members
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$newMembers->execute([$startDate, $endDate]);
$newMembers = $newMembers->fetchColumn();

// Members by month (for chart)
$monthlyMembers = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
    FROM members
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
")->fetchAll();

// Top members by spending
$topMembers = $pdo->prepare("
    SELECT m.*, 
        COALESCE(SUM(i.net_amount), 0) as total_spent,
        COUNT(DISTINCT jo.job_id) as job_count
    FROM members m
    LEFT JOIN invoices i ON m.member_id = i.member_id AND i.payment_status = 'paid'
    LEFT JOIN job_orders jo ON m.member_id = jo.member_id
    WHERE (i.issued_at IS NULL OR DATE(i.issued_at) BETWEEN ? AND ?)
    GROUP BY m.member_id
    ORDER BY total_spent DESC
    LIMIT 10
");
$topMembers->execute([$startDate, $endDate]);
$topMembers = $topMembers->fetchAll();

// Members with most visits
$frequentMembers = $pdo->prepare("
    SELECT m.*, COUNT(jo.job_id) as visit_count
    FROM members m
    JOIN job_orders jo ON m.member_id = jo.member_id
    WHERE DATE(jo.opened_date) BETWEEN ? AND ?
    GROUP BY m.member_id
    ORDER BY visit_count DESC
    LIMIT 10
");
$frequentMembers->execute([$startDate, $endDate]);
$frequentMembers = $frequentMembers->fetchAll();

// Total vehicles
$totalVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
?>

<!-- Report Selector -->
<div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-wrap items-center gap-4">
    <span class="font-medium text-gray-700">📊 เลือกรายงาน:</span>
    <select id="reportSelector" onchange="if(this.value) window.location.href=this.value"
        class="flex-1 min-w-[200px] px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        <option value="index.php">📋 ภาพรวม</option>
        <option value="revenue.php">💰 รายงานรายได้</option>
        <option value="jobs.php">🔧 รายงานงานซ่อม/บริการ</option>
        <option value="members.php" selected>👥 รายงานสมาชิก</option>
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
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-purple-100 text-sm font-medium mb-1">สมาชิกทั้งหมด</div>
        <div class="text-3xl font-bold">
            <?php echo number_format($totalMembers); ?>
        </div>
    </div>
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-green-100 text-sm font-medium mb-1">สมาชิกใหม่</div>
        <div class="text-3xl font-bold">
            <?php echo number_format($newMembers); ?>
        </div>
        <div class="text-green-100 text-sm mt-1">ในช่วงเวลานี้</div>
    </div>
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-blue-100 text-sm font-medium mb-1">รถยนต์ทั้งหมด</div>
        <div class="text-3xl font-bold">
            <?php echo number_format($totalVehicles); ?>
        </div>
    </div>
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-orange-100 text-sm font-medium mb-1">เฉลี่ยรถ/สมาชิก</div>
        <div class="text-3xl font-bold">
            <?php echo $totalMembers > 0 ? number_format($totalVehicles / $totalMembers, 1) : 0; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Monthly New Members Chart -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📈 สมาชิกใหม่รายเดือน</h3>
        <?php if (empty($monthlyMembers)): ?>
            <div class="h-64 flex items-center justify-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="h-64">
                <canvas id="monthlyChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Spenders -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">💰 ลูกค้ายอดใช้จ่ายสูงสุด</h3>
        <?php if (empty($topMembers) || $topMembers[0]['total_spent'] == 0): ?>
            <div class="py-8 text-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="space-y-3 max-h-64 overflow-y-auto">
                <?php foreach ($topMembers as $i => $m):
                    if ($m['total_spent'] == 0)
                        continue; ?>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-6 h-6 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center text-sm font-bold">
                            <?php echo $i + 1; ?>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium">
                                <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo $m['job_count']; ?> งาน
                            </div>
                        </div>
                        <div class="font-bold text-green-600">฿
                            <?php echo number_format($m['total_spent'], 0); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Frequent Visitors -->
    <div class="bg-white rounded-xl shadow-md p-5 lg:col-span-2">
        <h3 class="font-semibold mb-4">🔄 ลูกค้าประจำ (มาใช้บริการบ่อยสุด)</h3>
        <?php if (empty($frequentMembers)): ?>
            <div class="py-8 text-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <?php foreach ($frequentMembers as $i => $m): ?>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div
                            class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 font-bold">
                            <?php echo mb_substr($m['first_name'], 0, 1); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium truncate">
                                <?php echo htmlspecialchars($m['first_name']); ?>
                            </div>
                            <div class="text-sm text-purple-600 font-semibold">
                                <?php echo $m['visit_count']; ?> ครั้ง
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js Scripts -->
<script>
    <?php if (!empty($monthlyMembers)): ?>
        new Chart(document.getElementById('monthlyChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function ($m) {
                    $d = DateTime::createFromFormat('Y-m', $m['month']);
                    return "'" . ($d ? $d->format('M Y') : $m['month']) . "'";
                }, $monthlyMembers)); ?>],
                datasets: [{
                    label: 'สมาชิกใหม่',
                    data: [<?php echo implode(',', array_column($monthlyMembers, 'count')); ?>],
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
</script>

<?php require_once '../includes/footer.php'; ?>