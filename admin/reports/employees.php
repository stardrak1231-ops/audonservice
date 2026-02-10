<?php
/**
 * Employee Performance Report - ประสิทธิภาพพนักงาน
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

$pageTitle = 'ประสิทธิภาพพนักงาน';
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

// Get technicians with stats
$technicians = $pdo->prepare("
    SELECT 
        u.user_id,
        u.first_name,
        u.last_name,
        u.profile_image_url,
        COUNT(jo.job_id) as total_jobs,
        SUM(CASE WHEN jo.status = 'DELIVERED' THEN 1 ELSE 0 END) as completed_jobs,
        SUM(CASE WHEN jo.job_category = 'repair' THEN 1 ELSE 0 END) as repair_jobs,
        SUM(CASE WHEN jo.job_category = 'service' THEN 1 ELSE 0 END) as service_jobs,
        COALESCE(SUM(inv.net_amount), 0) as total_revenue
    FROM users u
    LEFT JOIN job_orders jo ON u.user_id = jo.assigned_to AND DATE(jo.opened_date) BETWEEN ? AND ?
    LEFT JOIN invoices inv ON jo.job_id = inv.job_id AND inv.payment_status = 'paid'
    WHERE u.role = 'technician' AND u.status = 'active'
    GROUP BY u.user_id
    ORDER BY total_revenue DESC
");
$technicians->execute([$startDate, $endDate]);
$technicians = $technicians->fetchAll();

// Get overall stats
$totalJobs = array_sum(array_column($technicians, 'total_jobs'));
$totalCompleted = array_sum(array_column($technicians, 'completed_jobs'));
$totalRevenue = array_sum(array_column($technicians, 'total_revenue'));
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
        <option value="services.php">⭐ รายงานบริการ</option>
        <option value="employees.php" selected>👨‍🔧 ประสิทธิภาพพนักงาน</option>
    </select>
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
    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="text-gray-500 text-sm mb-1">ช่างทั้งหมด</div>
        <div class="text-3xl font-bold text-gray-900">
            <?php echo count($technicians); ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="text-gray-500 text-sm mb-1">งานที่รับทั้งหมด</div>
        <div class="text-3xl font-bold text-blue-600">
            <?php echo $totalJobs; ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="text-gray-500 text-sm mb-1">งานเสร็จสมบูรณ์</div>
        <div class="text-3xl font-bold text-green-600">
            <?php echo $totalCompleted; ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-md p-5">
        <div class="text-gray-500 text-sm mb-1">รายได้รวม</div>
        <div class="text-3xl font-bold text-purple-600">฿
            <?php echo number_format($totalRevenue, 0); ?>
        </div>
    </div>
</div>

<!-- Technician Performance Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-5 border-b">
        <h3 class="font-semibold">ประสิทธิภาพรายบุคคล</h3>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">อันดับ</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ช่าง</th>
                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">งานทั้งหมด</th>
                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">สำเร็จ</th>
                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">ซ่อม / บริการ</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">รายได้</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($technicians)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">ไม่มีข้อมูลช่าง</td>
                </tr>
            <?php else: ?>
                <?php foreach ($technicians as $i => $tech): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <?php if ($i === 0 && $tech['total_revenue'] > 0): ?>
                                <span class="text-2xl">🥇</span>
                            <?php elseif ($i === 1 && $tech['total_revenue'] > 0): ?>
                                <span class="text-2xl">🥈</span>
                            <?php elseif ($i === 2 && $tech['total_revenue'] > 0): ?>
                                <span class="text-2xl">🥉</span>
                            <?php else: ?>
                                <span class="text-gray-400 font-medium">
                                    <?php echo $i + 1; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <?php if ($tech['profile_image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($tech['profile_image_url']); ?>"
                                        class="w-10 h-10 rounded-full object-cover">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500">
                                        <?php echo mb_substr($tech['first_name'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="font-semibold text-lg">
                                <?php echo $tech['total_jobs']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">
                                <?php echo $tech['completed_jobs']; ?>
                            </span>
                            <?php if ($tech['total_jobs'] > 0): ?>
                                <div class="text-xs text-gray-400 mt-1">
                                    <?php echo number_format(($tech['completed_jobs'] / $tech['total_jobs']) * 100, 0); ?>%
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-orange-600">
                                <?php echo $tech['repair_jobs']; ?>
                            </span>
                            <span class="text-gray-400 mx-1">/</span>
                            <span class="text-blue-600">
                                <?php echo $tech['service_jobs']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="font-semibold text-lg text-green-600">฿
                                <?php echo number_format($tech['total_revenue'], 0); ?>
                            </div>
                            <?php if ($totalRevenue > 0): ?>
                                <div class="text-xs text-gray-400">
                                    <?php echo number_format(($tech['total_revenue'] / $totalRevenue) * 100, 1); ?>% ของรวม
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Performance Chart -->
<?php if (!empty($technicians)): ?>
    <div class="bg-white rounded-xl shadow-md p-5 mt-6">
        <h3 class="font-semibold mb-4">เปรียบเทียบรายได้</h3>
        <div class="space-y-3">
            <?php
            $maxRevenue = max(array_column($technicians, 'total_revenue'));
            foreach ($technicians as $tech):
                $pct = $maxRevenue > 0 ? ($tech['total_revenue'] / $maxRevenue) * 100 : 0;
                ?>
                <div class="flex items-center gap-4">
                    <div class="w-24 text-sm text-gray-600 truncate">
                        <?php echo htmlspecialchars($tech['first_name']); ?>
                    </div>
                    <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-500 to-blue-600 rounded-full transition-all"
                            style="width: <?php echo $pct; ?>%"></div>
                    </div>
                    <div class="w-28 text-right font-medium">฿
                        <?php echo number_format($tech['total_revenue'], 0); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>