<?php
/**
 * Payments List - รายการชำระเงิน
 */

require_once '../config/database.php';
require_once '../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Filters
$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-d');
$method = $_GET['method'] ?? '';

// Build query
$whereClause = "WHERE DATE(p.paid_date) BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];

if ($method) {
    $whereClause .= " AND p.payment_method = ?";
    $params[] = $method;
}

// Get payments
$sql = "SELECT p.*, 
    CONCAT('INV-', LPAD(i.invoice_id, 5, '0')) as invoice_number, i.net_amount,
    jo.job_id,
    m.first_name as member_first_name, m.last_name as member_last_name
    FROM payments p
    LEFT JOIN invoices i ON p.invoice_id = i.invoice_id
    LEFT JOIN job_orders jo ON i.job_id = jo.job_id
    LEFT JOIN members m ON i.member_id = m.member_id
    $whereClause
    ORDER BY p.paid_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Summary by method
$summaryStmt = $pdo->prepare("
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(paid_amount) as total
    FROM payments p
    $whereClause
    GROUP BY payment_method
");
$summaryStmt->execute($params);
$summaryByMethod = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

$totalAmount = array_sum(array_column($payments, 'paid_amount'));

$pageTitle = 'รายการชำระเงิน';
require_once 'includes/header.php';
?>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6">
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">จากวันที่</label>
            <input type="date" name="from" value="<?php echo $dateFrom; ?>" class="px-4 py-2 border rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">ถึงวันที่</label>
            <input type="date" name="to" value="<?php echo $dateTo; ?>" class="px-4 py-2 border rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">ช่องทาง</label>
            <select name="method" class="px-4 py-2 border rounded-lg">
                <option value="">ทั้งหมด</option>
                <option value="cash" <?php echo $method === 'cash' ? 'selected' : ''; ?>>เงินสด</option>
                <option value="transfer" <?php echo $method === 'transfer' ? 'selected' : ''; ?>>โอนเงิน</option>
            </select>
        </div>
        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg">
            🔍 ค้นหา
        </button>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow p-4">
        <div class="text-gray-500 text-sm">💰 รวมทั้งหมด</div>
        <div class="text-2xl font-bold text-emerald-600">฿
            <?php echo number_format($totalAmount, 0); ?>
        </div>
        <div class="text-xs text-gray-400">
            <?php echo count($payments); ?> รายการ
        </div>
    </div>
    <?php
    $methodIcons = ['cash' => '💵', 'transfer' => '📱', 'card' => '💳'];
    $methodNames = ['cash' => 'เงินสด', 'transfer' => 'โอน', 'card' => 'บัตร'];
    foreach ($summaryByMethod as $s):
        ?>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-gray-500 text-sm">
                <?php echo $methodIcons[$s['payment_method']] ?? ''; ?>
                <?php echo $methodNames[$s['payment_method']] ?? $s['payment_method']; ?>
            </div>
            <div class="text-xl font-bold">฿
                <?php echo number_format($s['total'], 0); ?>
            </div>
            <div class="text-xs text-gray-400">
                <?php echo $s['count']; ?> รายการ
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Payments Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b bg-gray-50">
        <h2 class="font-semibold">💳 รายการชำระเงิน</h2>
    </div>

    <?php if (empty($payments)): ?>
        <div class="p-12 text-center text-gray-400">ไม่มีรายการชำระเงิน</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">วันที่/เวลา</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">ใบเสร็จ</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">ลูกค้า</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">ช่องทาง</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">จำนวน</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($payments as $p): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    <?php echo date('d/m/Y', strtotime($p['paid_date'])); ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?php echo date('H:i', strtotime($p['paid_date'])); ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <a href="invoice.php?job_id=<?php echo $p['job_id']; ?>"
                                    class="text-emerald-600 hover:underline font-mono">
                                    <?php echo htmlspecialchars($p['invoice_number']); ?>
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <?php echo htmlspecialchars($p['member_first_name'] . ' ' . $p['member_last_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php
                                $badges = [
                                    'cash' => 'bg-green-100 text-green-700',
                                    'transfer' => 'bg-blue-100 text-blue-700',
                                    'card' => 'bg-purple-100 text-purple-700'
                                ];
                                $badge = $badges[$p['payment_method']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $badge; ?>">
                                    <?php echo $methodIcons[$p['payment_method']] ?? ''; ?>
                                    <?php echo $methodNames[$p['payment_method']] ?? $p['payment_method']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600">
                                ฿
                                <?php echo number_format($p['paid_amount'], 0); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>