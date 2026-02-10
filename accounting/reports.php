<?php
/**
 * Financial Reports - รายงานการเงิน
 */

require_once '../config/database.php';
require_once '../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Period filter
$period = $_GET['period'] ?? 'month';
if ($period === 'today') {
    $dateFrom = date('Y-m-d');
    $dateTo = date('Y-m-d');
} elseif ($period === 'week') {
    $dateFrom = date('Y-m-d', strtotime('monday this week'));
    $dateTo = date('Y-m-d');
} elseif ($period === 'year') {
    $dateFrom = date('Y-01-01');
    $dateTo = date('Y-m-d');
} else {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-d');
}

// Income summary
$incomeStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(p.paid_amount), 0) as total_income,
        COUNT(DISTINCT p.payment_id) as payment_count,
        COUNT(DISTINCT i.invoice_id) as invoice_count
    FROM payments p
    LEFT JOIN invoices i ON p.invoice_id = i.invoice_id
    WHERE DATE(p.paid_date) BETWEEN ? AND ?
");
$incomeStmt->execute([$dateFrom, $dateTo]);
$income = $incomeStmt->fetch();

// Expense summary (parts cost) - Only for PAID invoices in this period
$expenseStmt = $pdo->prepare("
    SELECT COALESCE(SUM(sp.cost_price * jp.quantity), 0) as parts_cost
    FROM job_parts jp
    JOIN spare_parts sp ON jp.part_id = sp.part_id
    JOIN invoices i ON jp.job_id = i.job_id
    WHERE i.payment_status = 'paid'
    AND i.invoice_id IN (
        SELECT invoice_id FROM payments p WHERE DATE(p.paid_date) BETWEEN ? AND ?
    )
");
$expenseStmt->execute([$dateFrom, $dateTo]);
$expense = $expenseStmt->fetch();

$profit = $income['total_income'] - $expense['parts_cost'];

// Daily breakdown
$dailyStmt = $pdo->prepare("
    SELECT 
        DATE(p.paid_date) as date,
        SUM(p.paid_amount) as income
    FROM payments p
    WHERE DATE(p.paid_date) BETWEEN ? AND ?
    GROUP BY DATE(p.paid_date)
    ORDER BY date
");
$dailyStmt->execute([$dateFrom, $dateTo]);
$dailyData = $dailyStmt->fetchAll();

// Payment method breakdown
$methodStmt = $pdo->prepare("
    SELECT 
        payment_method,
        SUM(paid_amount) as total,
        COUNT(*) as count
    FROM payments
    WHERE DATE(paid_date) BETWEEN ? AND ?
    GROUP BY payment_method
");
$methodStmt->execute([$dateFrom, $dateTo]);
$methodData = $methodStmt->fetchAll();

// Top services
$topServicesStmt = $pdo->prepare("
    SELECT s.service_name, SUM(js.quantity) as total_qty, SUM(s.standard_price * js.quantity) as total_revenue
    FROM job_services js
    JOIN service_items s ON js.service_id = s.service_id
    JOIN job_orders jo ON js.job_id = jo.job_id
    WHERE DATE(jo.opened_date) BETWEEN ? AND ?
    GROUP BY js.service_id
    ORDER BY total_revenue DESC
    LIMIT 5
");
$topServicesStmt->execute([$dateFrom, $dateTo]);
$topServices = $topServicesStmt->fetchAll();

$pageTitle = 'รายงานการเงิน';
require_once 'includes/header.php';
?>

<!-- Period Filter -->
<div class="flex items-center gap-2 mb-6">
    <?php
    $periods = [
        'today' => 'วันนี้',
        'week' => 'สัปดาห์นี้',
        'month' => 'เดือนนี้',
        'year' => 'ปีนี้'
    ];
    foreach ($periods as $key => $label):
        ?>
        <a href="?period=<?php echo $key; ?>"
            class="px-4 py-2 rounded-lg font-medium <?php echo $period === $key ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'; ?>">
            <?php echo $label; ?>
        </a>
    <?php endforeach; ?>
    <span class="text-sm text-gray-500 ml-4">
        <?php echo date('d/m/Y', strtotime($dateFrom)); ?> -
        <?php echo date('d/m/Y', strtotime($dateTo)); ?>
    </span>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow p-5">
        <div class="text-gray-500 text-sm mb-1">💰 รายรับ</div>
        <div class="text-3xl font-bold text-green-600">฿
            <?php echo number_format($income['total_income'], 0); ?>
        </div>
        <div class="text-xs text-gray-400">
            <?php echo $income['payment_count']; ?> รายการ
        </div>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <div class="text-gray-500 text-sm mb-1">📦 ต้นทุนอะไหล่</div>
        <div class="text-3xl font-bold text-red-600">฿
            <?php echo number_format($expense['parts_cost'], 0); ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <div class="text-gray-500 text-sm mb-1">📈 กำไรขั้นต้น</div>
        <div class="text-3xl font-bold <?php echo $profit >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>">
            ฿
            <?php echo number_format($profit, 0); ?>
        </div>
        <?php if ($income['total_income'] > 0): ?>
            <div class="text-xs text-gray-400">
                Margin:
                <?php echo number_format(($profit / $income['total_income']) * 100, 1); ?>%
            </div>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <div class="text-gray-500 text-sm mb-1">🧾 ใบเสร็จ</div>
        <div class="text-3xl font-bold text-blue-600">
            <?php echo $income['invoice_count']; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Payment Method Breakdown -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 border-b bg-gray-50 font-semibold">💳 สรุปตามช่องทาง</div>
        <div class="p-4">
            <?php if (empty($methodData)): ?>
                <div class="text-gray-400 text-center py-8">ไม่มีข้อมูล</div>
            <?php else: ?>
                <?php
                $methodIcons = ['cash' => '💵', 'transfer' => '📱', 'card' => '💳'];
                $methodNames = ['cash' => 'เงินสด', 'transfer' => 'โอน', 'card' => 'บัตร'];
                $maxAmount = max(array_column($methodData, 'total'));
                foreach ($methodData as $m):
                    $percent = $maxAmount > 0 ? ($m['total'] / $maxAmount) * 100 : 0;
                    ?>
                    <div class="mb-4">
                        <div class="flex justify-between mb-1">
                            <span>
                                <?php echo $methodIcons[$m['payment_method']] ?? ''; ?>
                                <?php echo $methodNames[$m['payment_method']] ?? $m['payment_method']; ?>
                            </span>
                            <span class="font-medium">฿
                                <?php echo number_format($m['total'], 0); ?>
                            </span>
                        </div>
                        <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo $percent; ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            <?php echo $m['count']; ?> รายการ
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Services -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 border-b bg-gray-50 font-semibold">✨ บริการยอดนิยม</div>
        <?php if (empty($topServices)): ?>
            <div class="p-8 text-gray-400 text-center">ไม่มีข้อมูล</div>
        <?php else: ?>
            <div class="divide-y">
                <?php foreach ($topServices as $i => $s): ?>
                    <div class="p-3 flex items-center gap-3">
                        <div
                            class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                            <?php echo $i + 1; ?>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium">
                                <?php echo htmlspecialchars($s['service_name']); ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?php echo $s['total_qty']; ?> ครั้ง
                            </div>
                        </div>
                        <div class="font-bold text-emerald-600">฿
                            <?php echo number_format($s['total_revenue'], 0); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Daily Revenue Chart (Simple Table Version) -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mt-6">
    <div class="p-4 border-b bg-gray-50 font-semibold">📊 รายรับรายวัน</div>
    <?php if (empty($dailyData)): ?>
        <div class="p-8 text-gray-400 text-center">ไม่มีข้อมูล</div>
    <?php else: ?>
        <div class="p-4">
            <div class="flex gap-2 overflow-x-auto pb-2">
                <?php
                $maxDaily = max(array_column($dailyData, 'income'));
                foreach ($dailyData as $d):
                    $height = $maxDaily > 0 ? ($d['income'] / $maxDaily) * 100 : 0;
                    ?>
                    <div class="flex flex-col items-center min-w-[60px]">
                        <div class="text-xs font-medium text-emerald-600 mb-1">฿
                            <?php echo number_format($d['income'] / 1000, 0); ?>k
                        </div>
                        <div class="w-8 bg-gray-100 rounded-t-lg relative" style="height: 100px;">
                            <div class="absolute bottom-0 w-full bg-emerald-500 rounded-t-lg"
                                style="height: <?php echo $height; ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            <?php echo date('d', strtotime($d['date'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>