<?php
/**
 * Accounting Dashboard - งานรอออกบิล
 */

require_once '../config/database.php';
require_once '../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Get jobs ready for invoice (COMPLETED or DELIVERED without paid invoice)
$pendingJobs = $pdo->query("
    SELECT jo.*, 
        v.license_plate, v.brand, v.model,
        m.first_name as member_first_name, m.last_name as member_last_name, m.phone as member_phone,
        jst.status_name,
        i.invoice_id, i.net_amount, i.payment_status,
        (SELECT COALESCE(SUM(jsvc.price * jsvc.quantity), 0) FROM job_services jsvc WHERE jsvc.job_id = jo.job_id) as service_total,
        (SELECT COALESCE(SUM(sp.sell_price * jp.quantity), 0) FROM job_parts jp JOIN spare_parts sp ON jp.part_id = sp.part_id WHERE jp.job_id = jo.job_id) as parts_total
    FROM job_orders jo
    LEFT JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN members m ON jo.member_id = m.member_id
    LEFT JOIN job_status jst ON jo.status = jst.status_code AND jo.job_category = jst.job_category
    LEFT JOIN invoices i ON jo.job_id = i.job_id
    WHERE jo.status IN ('COMPLETED', 'DELIVERED')
    AND (i.invoice_id IS NULL OR i.payment_status != 'paid')
    ORDER BY jo.opened_date DESC
")->fetchAll();

// Get today's summary
$today = date('Y-m-d');
$todaySummary = $pdo->query("
    SELECT 
        COUNT(*) as total_invoices,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN net_amount ELSE 0 END), 0) as paid_amount,
        COALESCE(SUM(CASE WHEN payment_status != 'paid' THEN net_amount ELSE 0 END), 0) as pending_amount
    FROM invoices
    WHERE DATE(issued_at) = '$today'
")->fetch();

// Count by status
$noInvoice = 0;
$pendingPayment = 0;
foreach ($pendingJobs as $job) {
    if (!$job['invoice_id']) {
        $noInvoice++;
    } else {
        $pendingPayment++;
    }
}

$pageTitle = 'งานรอออกบิล';
require_once 'includes/header.php';
?>

<!-- Today Summary -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow p-4">
        <div class="text-gray-500 text-sm">ยังไม่ออกบิล</div>
        <div class="text-3xl font-bold text-orange-600">
            <?php echo $noInvoice; ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
        <div class="text-gray-500 text-sm">รอชำระเงิน</div>
        <div class="text-3xl font-bold text-blue-600">
            <?php echo $pendingPayment; ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
        <div class="text-gray-500 text-sm">รับเงินวันนี้</div>
        <div class="text-2xl font-bold text-green-600">฿
            <?php echo number_format($todaySummary['paid_amount'], 0); ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
        <div class="text-gray-500 text-sm">ค้างชำระวันนี้</div>
        <div class="text-2xl font-bold text-red-600">฿
            <?php echo number_format($todaySummary['pending_amount'], 0); ?>
        </div>
    </div>
</div>

<!-- Pending Jobs List -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b bg-gray-50">
        <h2 class="font-semibold text-lg">📋 งานรอดำเนินการ</h2>
    </div>

    <?php if (empty($pendingJobs)): ?>
        <div class="p-12 text-center text-gray-400">
            <svg class="w-16 h-16 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            ไม่มีงานรอดำเนินการ
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">งาน</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">ลูกค้า/รถ</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">บริการ</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">อะไหล่</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">รวม</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">สถานะบิล</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($pendingJobs as $job): ?>
                        <?php $total = $job['service_total'] + $job['parts_total']; ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-mono font-bold text-gray-800">#
                                    <?php echo str_pad($job['job_id'], 5, '0', STR_PAD_LEFT); ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?php echo date('d/m/Y', strtotime($job['opened_date'])); ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    <?php echo htmlspecialchars($job['member_first_name'] . ' ' . $job['member_last_name']); ?>
                                </div>
                                <div class="text-sm">
                                    <span class="font-mono text-blue-600">
                                        <?php echo htmlspecialchars($job['license_plate']); ?>
                                    </span>
                                    <span class="text-gray-400">
                                        <?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">฿
                                <?php echo number_format($job['service_total'], 0); ?>
                            </td>
                            <td class="px-4 py-3 text-right">฿
                                <?php echo number_format($job['parts_total'], 0); ?>
                            </td>
                            <td class="px-4 py-3 text-right font-bold">฿
                                <?php echo number_format($total, 0); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if (!$job['invoice_id']): ?>
                                    <span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs rounded-full">ยังไม่ออกบิล</span>
                                <?php elseif ($job['payment_status'] === 'pending'): ?>
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full">รอชำระ</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="invoice.php?job_id=<?php echo $job['job_id']; ?>"
                                    class="inline-flex items-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-sm">
                                    <?php echo $job['invoice_id'] ? '💳 รับชำระ' : '🧾 ออกบิล'; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>