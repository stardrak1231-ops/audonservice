<?php
/**
 * Admin Dashboard
 * หน้าหลักของระบบหลังบ้าน
 */

$pageTitle = 'Dashboard';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$pdo = getDBConnection();

// Get basic stats
$statsJobs = $pdo->query("SELECT COUNT(*) as total FROM job_orders WHERE DATE(opened_date) = CURDATE()")->fetch();
$statsInProgress = $pdo->query("SELECT COUNT(*) as total FROM job_orders WHERE status NOT IN ('DELIVERED', 'CANCELLED')")->fetch();
$statsCompleted = $pdo->query("SELECT COUNT(*) as total FROM job_orders WHERE status = 'DELIVERED'")->fetch();
$statsMembers = $pdo->query("SELECT COUNT(*) as total FROM members WHERE status = 'active'")->fetch();

// Today's revenue
$todayRevenue = $pdo->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM invoices WHERE payment_status = 'paid' AND DATE(issued_at) = CURDATE()")->fetch();

// Monthly revenue
$monthlyRevenue = $pdo->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM invoices WHERE payment_status = 'paid' AND MONTH(issued_at) = MONTH(CURDATE()) AND YEAR(issued_at) = YEAR(CURDATE())")->fetch();

// Last 7 days revenue (for chart)
$last7DaysRevenue = $pdo->query("
    SELECT DATE(issued_at) as date, COALESCE(SUM(net_amount), 0) as total
    FROM invoices
    WHERE payment_status = 'paid' AND DATE(issued_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(issued_at)
    ORDER BY date
")->fetchAll();

// Fill in missing dates
$revenueByDate = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $revenueByDate[$date] = 0;
}
foreach ($last7DaysRevenue as $row) {
    $revenueByDate[$row['date']] = (float) $row['total'];
}

// Stale jobs (> 3 days not delivered)
$staleJobs = $pdo->query("
    SELECT jo.*, v.license_plate, v.brand, v.model, m.first_name, m.last_name,
        DATEDIFF(CURDATE(), DATE(jo.opened_date)) as days_open
    FROM job_orders jo
    LEFT JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN members m ON jo.member_id = m.member_id
    WHERE jo.status NOT IN ('DELIVERED', 'CANCELLED')
    AND DATEDIFF(CURDATE(), DATE(jo.opened_date)) > 3
    ORDER BY jo.opened_date ASC
    LIMIT 5
")->fetchAll();

// Urgent jobs (not delivered)
$urgentJobs = $pdo->query("
    SELECT jo.*, v.license_plate, v.brand, v.model, m.first_name, m.last_name
    FROM job_orders jo
    LEFT JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN members m ON jo.member_id = m.member_id
    WHERE jo.job_type = 'urgent' AND jo.status NOT IN ('DELIVERED', 'CANCELLED')
    ORDER BY jo.opened_date DESC
    LIMIT 5
")->fetchAll();

// Today's appointments
$todayAppointments = $pdo->query("
    SELECT jo.*, v.license_plate, v.brand, v.model, m.first_name, m.last_name
    FROM job_orders jo
    LEFT JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN members m ON jo.member_id = m.member_id
    WHERE jo.job_type = 'appointment' AND DATE(jo.appointment_date) = CURDATE()
    ORDER BY jo.appointment_date ASC
    LIMIT 5
")->fetchAll();

// Low stock parts (less than 5)
$lowStockParts = $pdo->query("
    SELECT * FROM spare_parts
    WHERE stock_qty < 5 AND stock_qty > 0
    ORDER BY stock_qty ASC
    LIMIT 5
")->fetchAll();

// Out of stock parts
$outOfStock = $pdo->query("SELECT COUNT(*) as total FROM spare_parts WHERE stock_qty = 0")->fetch();
?>

<!-- Welcome Card with Revenue -->
<div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl shadow-lg p-6 mb-6 text-white">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-semibold">
                    สวัสดี, <?php echo htmlspecialchars($currentUser['first_name']); ?>!
                </h2>
                <p class="text-blue-100 text-sm">
                    <?php echo date('l, j F Y'); ?>
                </p>
            </div>
        </div>
        <div class="flex gap-6">
            <div class="text-center">
                <p class="text-blue-200 text-xs uppercase">วันนี้</p>
                <p class="text-2xl font-bold">฿<?php echo number_format($todayRevenue['total'], 0); ?></p>
            </div>
            <div class="text-center border-l border-white/20 pl-6">
                <p class="text-blue-200 text-xs uppercase">เดือนนี้</p>
                <p class="text-2xl font-bold">฿<?php echo number_format($monthlyRevenue['total'], 0); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">งานวันนี้</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo $statsJobs['total'] ?? 0; ?></p>
            </div>
            <div class="w-11 h-11 bg-blue-100 rounded-lg flex items-center justify-center">
                <span class="text-xl">📋</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">กำลังดำเนินการ</p>
                <p class="text-2xl font-bold text-yellow-600"><?php echo $statsInProgress['total'] ?? 0; ?></p>
            </div>
            <div class="w-11 h-11 bg-yellow-100 rounded-lg flex items-center justify-center">
                <span class="text-xl">⏳</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">ส่งมอบแล้ว</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $statsCompleted['total'] ?? 0; ?></p>
            </div>
            <div class="w-11 h-11 bg-green-100 rounded-lg flex items-center justify-center">
                <span class="text-xl">✅</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">สมาชิก</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo $statsMembers['total'] ?? 0; ?></p>
            </div>
            <div class="w-11 h-11 bg-purple-100 rounded-lg flex items-center justify-center">
                <span class="text-xl">👥</span>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Chart + Stale Jobs -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- 7 Days Revenue Chart -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800">📈 รายได้ 7 วันล่าสุด</h3>
            <a href="reports/revenue.php" class="text-blue-600 text-sm hover:underline">ดูรายงาน →</a>
        </div>
        <div class="h-56">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Stale Jobs -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 bg-amber-50 border-b border-amber-100 flex items-center justify-between">
            <h3 class="font-semibold text-amber-700 flex items-center gap-2">
                ⏰ งานค้าง > 3 วัน
                <?php if (count($staleJobs) > 0): ?>
                    <span
                        class="bg-amber-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo count($staleJobs); ?></span>
                <?php endif; ?>
            </h3>
        </div>
        <div class="divide-y max-h-56 overflow-y-auto">
            <?php if (empty($staleJobs)): ?>
                <div class="p-6 text-center text-gray-400">ไม่มีงานค้าง ✓</div>
            <?php else: ?>
                <?php foreach ($staleJobs as $job): ?>
                    <a href="jobs/view.php?id=<?php echo $job['job_id']; ?>" class="block p-3 hover:bg-amber-50">
                        <div class="flex items-center justify-between">
                            <span
                                class="font-mono font-bold text-amber-600"><?php echo htmlspecialchars($job['license_plate']); ?></span>
                            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">
                                <?php echo $job['days_open']; ?> วัน
                            </span>
                        </div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Alerts Section -->
<div class="grid md:grid-cols-3 gap-6 mb-6">
    <!-- Urgent Jobs -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 bg-red-50 border-b border-red-100 flex items-center justify-between">
            <h3 class="font-semibold text-red-700 flex items-center gap-2">
                🔥 งานด่วน
                <?php if (count($urgentJobs) > 0): ?>
                    <span
                        class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo count($urgentJobs); ?></span>
                <?php endif; ?>
            </h3>
            <a href="jobs/index.php?type=urgent" class="text-red-600 text-sm hover:underline">ดูทั้งหมด</a>
        </div>
        <div class="divide-y max-h-52 overflow-y-auto">
            <?php if (empty($urgentJobs)): ?>
                <div class="p-6 text-center text-gray-400">ไม่มีงานด่วน ✓</div>
            <?php else: ?>
                <?php foreach ($urgentJobs as $job): ?>
                    <a href="jobs/view.php?id=<?php echo $job['job_id']; ?>" class="block p-3 hover:bg-red-50">
                        <div class="flex items-center justify-between">
                            <span
                                class="font-mono font-bold text-red-600"><?php echo htmlspecialchars($job['license_plate']); ?></span>
                            <span
                                class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded"><?php echo $job['status']; ?></span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Today's Appointments -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 bg-purple-50 border-b border-purple-100 flex items-center justify-between">
            <h3 class="font-semibold text-purple-700 flex items-center gap-2">
                📅 นัดหมายวันนี้
                <?php if (count($todayAppointments) > 0): ?>
                    <span
                        class="bg-purple-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo count($todayAppointments); ?></span>
                <?php endif; ?>
            </h3>
            <a href="jobs/index.php?type=appointment" class="text-purple-600 text-sm hover:underline">ดูทั้งหมด</a>
        </div>
        <div class="divide-y max-h-52 overflow-y-auto">
            <?php if (empty($todayAppointments)): ?>
                <div class="p-6 text-center text-gray-400">ไม่มีนัดหมายวันนี้</div>
            <?php else: ?>
                <?php foreach ($todayAppointments as $job): ?>
                    <a href="jobs/view.php?id=<?php echo $job['job_id']; ?>" class="block p-3 hover:bg-purple-50">
                        <div class="flex items-center justify-between">
                            <span
                                class="font-mono font-bold text-purple-600"><?php echo htmlspecialchars($job['license_plate']); ?></span>
                            <span class="text-xs bg-purple-100 text-purple-600 px-2 py-0.5 rounded">
                                <?php echo date('H:i', strtotime($job['appointment_date'])); ?>
                            </span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 bg-orange-50 border-b border-orange-100 flex items-center justify-between">
            <h3 class="font-semibold text-orange-700 flex items-center gap-2">
                📦 อะไหล่ใกล้หมด
                <?php if ($outOfStock['total'] > 0): ?>
                    <span class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo $outOfStock['total']; ?>
                        หมด</span>
                <?php endif; ?>
            </h3>
            <a href="parts/index.php" class="text-orange-600 text-sm hover:underline">จัดการ</a>
        </div>
        <div class="divide-y max-h-52 overflow-y-auto">
            <?php if (empty($lowStockParts) && $outOfStock['total'] == 0): ?>
                <div class="p-6 text-center text-gray-400">Stock ปกติ ✓</div>
            <?php else: ?>
                <?php if ($outOfStock['total'] > 0): ?>
                    <div class="p-3 bg-red-50 text-red-600 text-sm">
                        ⚠️ อะไหล่หมด stock <?php echo $outOfStock['total']; ?> รายการ
                    </div>
                <?php endif; ?>
                <?php foreach ($lowStockParts as $part): ?>
                    <div class="p-3 hover:bg-orange-50">
                        <div class="flex items-center justify-between">
                            <div class="font-medium text-gray-800"><?php echo htmlspecialchars($part['part_name']); ?></div>
                            <span
                                class="text-sm font-bold <?php echo $part['stock_qty'] <= 2 ? 'text-red-600' : 'text-orange-600'; ?>">
                                เหลือ <?php echo $part['stock_qty']; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="grid md:grid-cols-3 gap-4">
    <a href="jobs/create.php"
        class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl p-4 flex items-center gap-3 transition-colors shadow-lg shadow-blue-200">
        <span class="text-2xl">➕</span>
        <span class="font-medium text-lg">เปิดงานใหม่</span>
    </a>
    <a href="members/index.php"
        class="bg-white hover:bg-gray-50 rounded-xl shadow p-4 flex items-center gap-3 transition-colors">
        <span class="text-2xl">👥</span>
        <span class="font-medium text-gray-800">จัดการสมาชิก</span>
    </a>
    <a href="reports/index.php"
        class="bg-white hover:bg-gray-50 rounded-xl shadow p-4 flex items-center gap-3 transition-colors">
        <span class="text-2xl">📊</span>
        <span class="font-medium text-gray-800">ดูรายงาน</span>
    </a>
</div>

<!-- Chart.js -->
<script>
    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(function ($d) {
                return "'" . date('D d', strtotime($d)) . "'";
            }, array_keys($revenueByDate))); ?>],
            datasets: [{
                label: 'รายได้',
                data: [<?php echo implode(',', array_values($revenueByDate)); ?>],
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'rgb(59, 130, 246)',
                pointRadius: 4
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
                        callback: value => '฿' + value.toLocaleString()
                    }
                }
            }
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>