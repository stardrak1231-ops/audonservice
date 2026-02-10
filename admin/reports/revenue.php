<?php
/**
 * Revenue Report - รายงานรายได้
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

$pageTitle = 'รายงานรายได้';
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

// Total revenue
$totalRevenue = $pdo->prepare("
    SELECT COALESCE(SUM(net_amount), 0) as total 
    FROM invoices 
    WHERE payment_status = 'paid' AND DATE(issued_at) BETWEEN ? AND ?
");
$totalRevenue->execute([$startDate, $endDate]);
$totalRevenue = $totalRevenue->fetchColumn();

// Revenue by day
$dailyRevenue = $pdo->prepare("
    SELECT DATE(issued_at) as date, SUM(net_amount) as amount, COUNT(*) as count
    FROM invoices
    WHERE payment_status = 'paid' AND DATE(issued_at) BETWEEN ? AND ?
    GROUP BY DATE(issued_at)
    ORDER BY date
");
$dailyRevenue->execute([$startDate, $endDate]);
$dailyRevenue = $dailyRevenue->fetchAll();

// Revenue by payment method
$byMethod = $pdo->prepare("
    SELECT p.payment_method, SUM(p.paid_amount) as total, COUNT(*) as count
    FROM payments p
    WHERE DATE(p.paid_date) BETWEEN ? AND ?
    GROUP BY p.payment_method
");
$byMethod->execute([$startDate, $endDate]);
$byMethod = $byMethod->fetchAll();

// Parts cost (from paid invoices in period)
$partsCost = $pdo->prepare("
    SELECT COALESCE(SUM(jp.quantity * sp.cost_price), 0) as total
    FROM job_parts jp
    JOIN spare_parts sp ON jp.part_id = sp.part_id
    JOIN job_orders jo ON jp.job_id = jo.job_id
    JOIN invoices i ON jo.job_id = i.job_id
    WHERE i.payment_status = 'paid'
    AND DATE(i.issued_at) BETWEEN ? AND ?
");
$partsCost->execute([$startDate, $endDate]);
$partsCost = $partsCost->fetchColumn();

$profit = $totalRevenue - $partsCost;

// Recent payments
$recentPayments = $pdo->prepare("
    SELECT p.*, i.net_amount, i.job_id,
        m.first_name, m.last_name
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.invoice_id
    LEFT JOIN members m ON i.member_id = m.member_id
    WHERE DATE(p.paid_date) BETWEEN ? AND ?
    ORDER BY p.paid_date DESC
    LIMIT 20
");
$recentPayments->execute([$startDate, $endDate]);
$recentPayments = $recentPayments->fetchAll();

$methodNames = ['cash' => 'เงินสด', 'transfer' => 'โอนเงิน', 'card' => 'บัตร'];
?>

<!-- Report Selector -->
<div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-wrap items-center gap-4">
    <span class="font-medium text-gray-700">📊 เลือกรายงาน:</span>
    <select id="reportSelector" onchange="if(this.value) window.location.href=this.value"
        class="flex-1 min-w-[200px] px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        <option value="index.php">📋 ภาพรวม</option>
        <option value="revenue.php" selected>💰 รายงานรายได้</option>
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
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-5 text-white">
        <div class="text-green-100 text-sm font-medium mb-1">รายได้รวม</div>
        <div class="text-3xl font-bold">฿
            <?php echo number_format($totalRevenue, 0); ?>
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
        <?php if ($totalRevenue > 0): ?>
            <div class="text-blue-100 text-sm mt-1">
                <?php echo number_format(($profit / $totalRevenue) * 100, 1); ?>% margin
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Daily Revenue Chart -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📈 แนวโน้มรายได้</h3>
        <?php if (empty($dailyRevenue)): ?>
            <div class="h-64 flex items-center justify-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Revenue vs Cost Bar Chart -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📊 รายได้ vs ต้นทุน</h3>
        <div class="h-64">
            <canvas id="comparisonChart"></canvas>
        </div>
    </div>

    <!-- Payment Method Pie Chart -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">💳 ช่องทางการชำระ</h3>
        <?php if (empty($byMethod)): ?>
            <div class="h-64 flex items-center justify-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="h-64 flex items-center justify-center">
                <canvas id="methodChart" style="max-height: 240px;"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Revenue Summary -->
    <div class="bg-white rounded-xl shadow-md p-5">
        <h3 class="font-semibold mb-4">📋 สรุปตามช่องทาง</h3>
        <?php if (empty($byMethod)): ?>
            <div class="py-8 text-center text-gray-400">ยังไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($byMethod as $m): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">
                                <?php echo $m['payment_method'] === 'cash' ? '💵' : ($m['payment_method'] === 'transfer' ? '📱' : '💳'); ?>
                            </span>
                            <span>
                                <?php echo $methodNames[$m['payment_method']] ?? $m['payment_method']; ?>
                            </span>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-green-600">฿
                                <?php echo number_format($m['total'], 0); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo $m['count']; ?> รายการ
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Payments Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b bg-gray-50 font-semibold">🧾 รายการชำระเงินล่าสุด</div>
    <?php if (empty($recentPayments)): ?>
        <div class="p-8 text-center text-gray-400">ยังไม่มีรายการ</div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">วันที่</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ลูกค้า</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">ช่องทาง</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">ยอดชำระ</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($recentPayments as $p): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">
                            <?php echo date('d/m/Y H:i', strtotime($p['paid_date'])); ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">
                                <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                            </div>
                            <div class="text-xs text-gray-500">Job #
                                <?php echo $p['job_id']; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span
                                class="px-2 py-1 rounded-full text-xs <?php echo $p['payment_method'] === 'cash' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'; ?>">
                                <?php echo $methodNames[$p['payment_method']] ?? $p['payment_method']; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-green-600">฿
                            <?php echo number_format($p['paid_amount'], 0); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Chart.js Scripts -->
<script>
    <?php if (!empty($dailyRevenue)): ?>
        // Revenue Line Chart
        new Chart(document.getElementById('revenueChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function ($d) {
                    return "'" . date('d/m', strtotime($d['date'])) . "'";
                }, $dailyRevenue)); ?>],
                datasets: [{
                    label: 'รายได้',
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
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => '฿' + value.toLocaleString() }
                    }
                }
            }
        });
    <?php endif; ?>

    // Comparison Bar Chart
    new Chart(document.getElementById('comparisonChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['รายได้', 'ต้นทุน', 'กำไร'],
            datasets: [{
                data: [<?php echo $totalRevenue; ?>, <?php echo $partsCost; ?>, <?php echo $profit; ?>],
                backgroundColor: ['rgb(34, 197, 94)', 'rgb(239, 68, 68)', 'rgb(59, 130, 246)']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => '฿' + value.toLocaleString() }
                }
            }
        }
    });

    <?php if (!empty($byMethod)): ?>
        // Payment Method Pie Chart
        new Chart(document.getElementById('methodChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function ($m) use ($methodNames) {
                    return "'" . ($methodNames[$m['payment_method']] ?? $m['payment_method']) . "'";
                }, $byMethod)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($byMethod, 'total')); ?>],
                    backgroundColor: ['rgb(34, 197, 94)', 'rgb(59, 130, 246)', 'rgb(168, 85, 247)']
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