<?php
/**
 * Admin Reports Dashboard - รายงานระดับบริหาร
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

$pageTitle = 'รายงานระดับบริหาร';
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

// Revenue stats
$revenueQuery = $pdo->prepare("
    SELECT 
        COALESCE(SUM(i.net_amount), 0) as total_revenue,
        COUNT(DISTINCT i.invoice_id) as invoice_count
    FROM invoices i
    WHERE i.payment_status = 'paid'
    AND DATE(i.issued_at) BETWEEN ? AND ?
");
$revenueQuery->execute([$startDate, $endDate]);
$revenue = $revenueQuery->fetch();

// Parts cost (from paid invoices in period)
$partsCostQuery = $pdo->prepare("
    SELECT COALESCE(SUM(jp.quantity * sp.cost_price), 0) as parts_cost
    FROM job_parts jp
    JOIN spare_parts sp ON jp.part_id = sp.part_id
    JOIN job_orders jo ON jp.job_id = jo.job_id
    JOIN invoices i ON jo.job_id = i.job_id
    WHERE i.payment_status = 'paid'
    AND DATE(i.issued_at) BETWEEN ? AND ?
");
$partsCostQuery->execute([$startDate, $endDate]);
$partsCost = $partsCostQuery->fetchColumn();

$profit = $revenue['total_revenue'] - $partsCost;

// Job stats
$jobStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_jobs,
        SUM(CASE WHEN job_category = 'repair' THEN 1 ELSE 0 END) as repair_count,
        SUM(CASE WHEN job_category = 'service' THEN 1 ELSE 0 END) as service_count,
        SUM(CASE WHEN status = 'DELIVERED' THEN 1 ELSE 0 END) as completed_count
    FROM job_orders
    WHERE DATE(opened_date) BETWEEN ? AND ?
");
$jobStats->execute([$startDate, $endDate]);
$jobStats = $jobStats->fetch();

// Top services
$topServices = $pdo->prepare("
    SELECT s.service_name, SUM(js.quantity) as total_qty, SUM(js.quantity * s.standard_price) as total_value
    FROM job_services js
    JOIN service_items s ON js.service_id = s.service_id
    JOIN job_orders jo ON js.job_id = jo.job_id
    WHERE DATE(jo.opened_date) BETWEEN ? AND ?
    GROUP BY js.service_id
    ORDER BY total_value DESC
    LIMIT 5
");
$topServices->execute([$startDate, $endDate]);
$topServices = $topServices->fetchAll();

// Top parts sold
$topParts = $pdo->prepare("
    SELECT p.part_name, SUM(jp.quantity) as total_qty, SUM(jp.quantity * p.sell_price) as total_value
    FROM job_parts jp
    JOIN spare_parts p ON jp.part_id = p.part_id
    JOIN job_orders jo ON jp.job_id = jo.job_id
    WHERE DATE(jo.opened_date) BETWEEN ? AND ?
    GROUP BY jp.part_id
    ORDER BY total_value DESC
    LIMIT 5
");
$topParts->execute([$startDate, $endDate]);
$topParts = $topParts->fetchAll();

// Revenue by day (for chart)
$dailyRevenue = $pdo->prepare("
    SELECT DATE(issued_at) as date, SUM(net_amount) as amount
    FROM invoices
    WHERE payment_status = 'paid' AND DATE(issued_at) BETWEEN ? AND ?
    GROUP BY DATE(issued_at)
    ORDER BY date
");
$dailyRevenue->execute([$startDate, $endDate]);
$dailyRevenue = $dailyRevenue->fetchAll();
?>

<!-- Report Selector -->
<div class="bg-white rounded-xl shadow p-4 mb-4 flex flex-wrap items-center gap-4">
    <span class="font-medium text-gray-700">📊 เลือกรายงาน:</span>
    <select id="reportSelector" onchange="if(this.value) window.location.href=this.value"
        class="flex-1 min-w-[200px] px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        <option value="">-- ภาพรวม (หน้าปัจจุบัน) --</option>
        <option value="revenue.php">💰 รายงานรายได้</option>
        <option value="jobs.php">🔧 รายงานงานซ่อม/บริการ</option>
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
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-green-100 text-sm font-medium mb-1">รายได้รวม</div>
        <div class="text-3xl font-bold">฿
            <?php echo number_format($revenue['total_revenue'], 0); ?>
        </div>
        <div class="text-green-100 text-sm mt-2">
            <?php echo $revenue['invoice_count']; ?> ใบเสร็จ
        </div>
    </div>
    <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-red-100 text-sm font-medium mb-1">ต้นทุนอะไหล่</div>
        <div class="text-3xl font-bold">฿
            <?php echo number_format($partsCost, 0); ?>
        </div>
    </div>
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-blue-100 text-sm font-medium mb-1">กำไรขั้นต้น</div>
        <div class="text-3xl font-bold">฿
            <?php echo number_format($profit, 0); ?>
        </div>
        <?php if ($revenue['total_revenue'] > 0): ?>
            <div class="text-blue-100 text-sm mt-2">
                <?php echo number_format(($profit / $revenue['total_revenue']) * 100, 1); ?>% margin
            </div>
        <?php endif; ?>
    </div>
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-purple-100 text-sm font-medium mb-1">จำนวนงาน</div>
        <div class="text-3xl font-bold">
            <?php echo $jobStats['total_jobs']; ?>
        </div>
        <div class="text-purple-100 text-sm mt-2">
            🔧
            <?php echo $jobStats['repair_count']; ?> ซ่อม | 🔄
            <?php echo $jobStats['service_count']; ?> บริการ
        </div>
    </div>
</div>


<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Revenue Line Chart -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📈 รายได้รายวัน</h3>
        <?php if (empty($dailyRevenue)): ?>
            <div class="h-64 flex items-center justify-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Job Type Pie Chart -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📊 สัดส่วนประเภทงาน</h3>
        <?php if ($jobStats['total_jobs'] == 0): ?>
            <div class="h-64 flex items-center justify-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="h-64 flex items-center justify-center">
                <canvas id="jobTypeChart" style="max-height: 240px;"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Services -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">⭐ บริการยอดนิยม</h3>
        <?php if (empty($topServices)): ?>
            <div class="py-8 text-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($topServices as $i => $s): ?>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-medium">
                            <?php echo $i + 1; ?>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium">
                                <?php echo htmlspecialchars($s['service_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo $s['total_qty']; ?> ครั้ง
                            </div>
                        </div>
                        <div class="font-semibold text-green-600">฿
                            <?php echo number_format($s['total_value'], 0); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Parts -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📦 อะไหล่ขายดี</h3>
        <?php if (empty($topParts)): ?>
            <div class="py-8 text-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($topParts as $i => $p): ?>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-6 h-6 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-sm font-medium">
                            <?php echo $i + 1; ?>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium">
                                <?php echo htmlspecialchars($p['part_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo $p['total_qty']; ?> ชิ้น
                            </div>
                        </div>
                        <div class="font-semibold text-green-600">฿
                            <?php echo number_format($p['total_value'], 0); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js Scripts -->
<script>
    <?php if (!empty($dailyRevenue)): ?>
        // Revenue Line Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function ($d) {
                    return "'" . date('d/m', strtotime($d['date'])) . "'";
                }, $dailyRevenue)); ?>],
                datasets: [{
                    label: 'รายได้ (บาท)',
                    data: [<?php echo implode(',', array_column($dailyRevenue, 'amount')); ?>],
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return '฿' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    <?php endif; ?>

    <?php if ($jobStats['total_jobs'] > 0): ?>
        // Job Type Pie Chart
        const jobCtx = document.getElementById('jobTypeChart').getContext('2d');
        new Chart(jobCtx, {
            type: 'doughnut',
            data: {
                labels: ['งานซ่อม', 'งานบริการ'],
                datasets: [{
                    data: [<?php echo $jobStats['repair_count']; ?>, <?php echo $jobStats['service_count']; ?>],
                    backgroundColor: ['rgb(249, 115, 22)', 'rgb(59, 130, 246)'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    <?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>