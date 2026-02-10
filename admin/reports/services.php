<?php
/**
 * Services Report - รายงานบริการ
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

$pageTitle = 'รายงานบริการ';
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

// Get all services with stats (use actual job price, not standard_price)
$services = $pdo->prepare("
    SELECT s.*, 
        COALESCE(SUM(js.quantity), 0) as total_qty,
        COALESCE(SUM(js.quantity * js.price), 0) as total_revenue
    FROM service_items s
    LEFT JOIN job_services js ON s.service_id = js.service_id
    LEFT JOIN job_orders jo ON js.job_id = jo.job_id 
        AND DATE(jo.opened_date) BETWEEN ? AND ?
    GROUP BY s.service_id
    ORDER BY total_revenue DESC
");
$services->execute([$startDate, $endDate]);
$services = $services->fetchAll();

// Total stats
$totalServices = count($services);
$totalRevenue = array_sum(array_column($services, 'total_revenue'));
$totalQty = array_sum(array_column($services, 'total_qty'));

// Top 5 for pie chart
$top5 = array_slice(array_filter($services, fn($s) => $s['total_qty'] > 0), 0, 5);
?>

<!-- Report Selector -->
<div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-wrap items-center gap-4">
    <span class="font-medium text-gray-700">📊 เลือกรายงาน:</span>
    <select id="reportSelector" onchange="if(this.value) window.location.href=this.value"
        class="flex-1 min-w-[200px] px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        <option value="index.php">📋 ภาพรวม</option>
        <option value="revenue.php">💰 รายงานรายได้</option>
        <option value="jobs.php">🔧 รายงานงานซ่อม/บริการ</option>
        <option value="members.php">👥 รายงานสมาชิก</option>
        <option value="services.php" selected>⭐ รายงานบริการ</option>
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
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-orange-100 text-sm font-medium mb-1">รายการบริการ</div>
        <div class="text-3xl font-bold">
            <?php echo $totalServices; ?>
        </div>
    </div>
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-blue-100 text-sm font-medium mb-1">ให้บริการแล้ว</div>
        <div class="text-3xl font-bold">
            <?php echo number_format($totalQty); ?> ครั้ง
        </div>
    </div>
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-green-100 text-sm font-medium mb-1">รายได้จากบริการ</div>
        <div class="text-3xl font-bold">฿
            <?php echo number_format($totalRevenue, 0); ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Services Pie Chart -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📊 สัดส่วนบริการ (Top 5)</h3>
        <?php if (empty($top5)): ?>
            <div class="h-64 flex items-center justify-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="h-64 flex items-center justify-center">
                <canvas id="servicesPieChart" style="max-height: 240px;"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Revenue Bar Chart -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">💰 รายได้ต่อบริการ</h3>
        <?php if (empty($top5)): ?>
            <div class="h-64 flex items-center justify-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="h-64">
                <canvas id="revenueBarChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Services Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b bg-gray-50 font-semibold">📋 รายละเอียดบริการทั้งหมด</div>
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">บริการ</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">ราคา</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">ให้บริการ</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">รายได้</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php foreach ($services as $s): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium">
                        <?php echo htmlspecialchars($s['service_name']); ?>
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600">฿
                        <?php echo number_format($s['standard_price'], 0); ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                            <?php echo $s['total_qty']; ?> ครั้ง
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-green-600">฿
                        <?php echo number_format($s['total_revenue'], 0); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Chart.js Scripts -->
<script>
    <?php if (!empty($top5)): ?>
        const colors = ['rgb(249, 115, 22)', 'rgb(59, 130, 246)', 'rgb(34, 197, 94)', 'rgb(168, 85, 247)', 'rgb(236, 72, 153)'];

        new Chart(document.getElementById('servicesPieChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function ($s) {
                    return "'" . addslashes($s['service_name']) . "'";
                }, $top5)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($top5, 'total_qty')); ?>],
                    backgroundColor: colors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        new Chart(document.getElementById('revenueBarChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function ($s) {
                    return "'" . addslashes($s['service_name']) . "'";
                }, $top5)); ?>],
                datasets: [{
                    label: 'รายได้',
                    data: [<?php echo implode(',', array_column($top5, 'total_revenue')); ?>],
                    backgroundColor: colors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { callback: v => '฿' + v.toLocaleString() } } }
            }
        });
    <?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>