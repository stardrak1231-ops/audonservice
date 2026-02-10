<?php
/**
 * View Purchase Order - ดูและจัดการใบสั่งซื้อ
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

$poId = $_GET['id'] ?? 0;
if (!$poId) {
    header('Location: index.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Mark as Ordered
    if ($action === 'order') {
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'ordered', ordered_by = ?, ordered_at = NOW() WHERE po_id = ? AND status = 'draft'");
        $stmt->execute([$_SESSION['user_id'], $poId]);
        header('Location: view.php?id=' . $poId . '&success=ordered');
        exit;
    }

    // Receive Items (Update Stock)
    if ($action === 'receive') {
        // Get PO items
        $itemsStmt = $pdo->prepare("
            SELECT poi.*, sp.conversion_rate, sp.unit_sell, sp.unit_purchase
            FROM purchase_order_items poi
            JOIN spare_parts sp ON poi.part_id = sp.part_id
            WHERE poi.po_id = ?
        ");
        $itemsStmt->execute([$poId]);
        $items = $itemsStmt->fetchAll();

        // Update stock for each item
        foreach ($items as $item) {
            $qtyToAdd = $item['qty_ordered'] * $item['conversion_rate'];

            // Update spare_parts stock
            $pdo->prepare("UPDATE spare_parts SET stock_qty = stock_qty + ? WHERE part_id = ?")
                ->execute([$qtyToAdd, $item['part_id']]);

            // Log stock movement
            $pdo->prepare("INSERT INTO stock_movements (part_id, movement_type, quantity, reference_id) VALUES (?, 'IN', ?, ?)")
                ->execute([$item['part_id'], $qtyToAdd, $poId]);

            // Update qty_received
            $pdo->prepare("UPDATE purchase_order_items SET qty_received = qty_ordered WHERE item_id = ?")
                ->execute([$item['item_id']]);
        }

        // Update PO status
        $pdo->prepare("UPDATE purchase_orders SET status = 'received', received_by = ?, received_at = NOW() WHERE po_id = ?")
            ->execute([$_SESSION['user_id'], $poId]);

        header('Location: index.php?success=received');
        exit;
    }

    // Cancel PO
    if ($action === 'cancel') {
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE po_id = ? AND status IN ('draft', 'ordered')");
        $stmt->execute([$poId]);
        header('Location: index.php?success=cancelled');
        exit;
    }
}

// Get PO details
$stmt = $pdo->prepare("
    SELECT po.*, s.supplier_name, s.contact_name, s.phone, s.address,
           CONCAT(u1.first_name, ' ', u1.last_name) as ordered_by_name, 
           CONCAT(u2.first_name, ' ', u2.last_name) as received_by_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN users u1 ON po.ordered_by = u1.user_id
    LEFT JOIN users u2 ON po.received_by = u2.user_id
    WHERE po.po_id = ?
");
$stmt->execute([$poId]);
$po = $stmt->fetch();

if (!$po) {
    header('Location: index.php');
    exit;
}

// Get PO items
$itemsStmt = $pdo->prepare("
    SELECT poi.*, sp.part_name, sp.unit_sell, sp.unit_purchase, sp.conversion_rate
    FROM purchase_order_items poi
    JOIN spare_parts sp ON poi.part_id = sp.part_id
    WHERE poi.po_id = ?
");
$itemsStmt->execute([$poId]);
$items = $itemsStmt->fetchAll();

$pageTitle = 'ใบสั่งซื้อ ' . $po['po_number'];
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$success = $_GET['success'] ?? '';

// Status config
$statusConfig = [
    'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => 'ร่าง'],
    'ordered' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'สั่งซื้อแล้ว'],
    'partial' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'label' => 'รับบางส่วน'],
    'received' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'label' => 'รับครบแล้ว'],
    'cancelled' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'label' => 'ยกเลิก']
];
$sc = $statusConfig[$po['status']] ?? $statusConfig['draft'];
?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo [
            'created' => 'สร้างใบสั่งซื้อสำเร็จ',
            'ordered' => 'บันทึกสถานะสั่งซื้อแล้ว'
        ][$success] ?? 'ดำเนินการสำเร็จ'; ?>
    </div>
<?php endif; ?>

<!-- Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div class="flex items-center gap-4">
        <a href="index.php" class="p-2 hover:bg-gray-100 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                </path>
            </svg>
        </a>
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-800">
                    <?php echo htmlspecialchars($po['po_number']); ?>
                </h1>
                <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $sc['bg'] . ' ' . $sc['text']; ?>">
                    <?php echo $sc['label']; ?>
                </span>
            </div>
            <p class="text-gray-500">สร้างเมื่อ
                <?php echo date('d/m/Y H:i', strtotime($po['created_at'])); ?>
            </p>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex items-center gap-2">
        <?php if ($po['status'] === 'draft'): ?>
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="order">
                <button type="submit"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    ส่งคำสั่งซื้อ
                </button>
            </form>
        <?php endif; ?>

        <?php if ($po['status'] === 'ordered'): ?>
            <button type="button" onclick="document.getElementById('receiveModal').classList.remove('hidden')"
                class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                รับของแล้ว
            </button>
        <?php endif; ?>

        <?php if (in_array($po['status'], ['draft', 'ordered'])): ?>
            <button type="button" onclick="document.getElementById('cancelModal').classList.remove('hidden')"
                class="flex items-center gap-2 px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                ยกเลิก
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Items Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">รายการอะไหล่</h3>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">อะไหล่</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">จำนวน</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">ราคา/หน่วย</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">รวม</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium">
                                    <?php echo htmlspecialchars($item['part_name']); ?>
                                </div>
                                <?php if ($item['conversion_rate'] > 1): ?>
                                    <div class="text-xs text-gray-500">
                                        =
                                        <?php echo $item['qty_ordered'] * $item['conversion_rate']; ?>
                                        <?php echo htmlspecialchars($item['unit_sell']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php echo $item['qty_ordered']; ?>
                                <?php echo htmlspecialchars($item['unit_purchase']); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                ฿
                                <?php echo number_format($item['unit_price'], 2); ?>
                            </td>
                            <td class="px-6 py-4 text-right font-medium">
                                ฿
                                <?php echo number_format($item['qty_ordered'] * $item['unit_price'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-right font-semibold">ยอดรวมทั้งหมด</td>
                        <td class="px-6 py-4 text-right text-lg font-bold text-blue-600">
                            ฿
                            <?php echo number_format($po['total_amount'], 2); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Sidebar Info -->
    <div class="space-y-6">
        <!-- Supplier Info -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                    </path>
                </svg>
                ผู้จำหน่าย
            </h3>
            <div class="space-y-3">
                <div>
                    <div class="text-sm text-gray-500">ชื่อร้าน/บริษัท</div>
                    <div class="font-medium">
                        <?php echo htmlspecialchars($po['supplier_name'] ?? 'ไม่ระบุ'); ?>
                    </div>
                </div>
                <?php if ($po['contact_name']): ?>
                    <div>
                        <div class="text-sm text-gray-500">ผู้ติดต่อ</div>
                        <div class="font-medium">
                            <?php echo htmlspecialchars($po['contact_name']); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($po['phone']): ?>
                    <div>
                        <div class="text-sm text-gray-500">เบอร์โทร</div>
                        <a href="tel:<?php echo htmlspecialchars($po['phone']); ?>"
                            class="font-medium text-blue-600 hover:underline">
                            <?php echo htmlspecialchars($po['phone']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timeline -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">ประวัติ</h3>
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium">สร้างใบสั่งซื้อ</div>
                        <div class="text-sm text-gray-500">
                            <?php echo date('d/m/Y H:i', strtotime($po['created_at'])); ?>
                        </div>
                    </div>
                </div>

                <?php if ($po['ordered_at']): ?>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="font-medium">ส่งคำสั่งซื้อ</div>
                            <div class="text-sm text-gray-500">
                                <?php echo date('d/m/Y H:i', strtotime($po['ordered_at'])); ?>
                                <?php if ($po['ordered_by_name']): ?>
                                    โดย
                                    <?php echo htmlspecialchars($po['ordered_by_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($po['received_at']): ?>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <div class="font-medium">รับของครบแล้ว</div>
                            <div class="text-sm text-gray-500">
                                <?php echo date('d/m/Y H:i', strtotime($po['received_at'])); ?>
                                <?php if ($po['received_by_name']): ?>
                                    โดย
                                    <?php echo htmlspecialchars($po['received_by_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($po['status'] === 'cancelled'): ?>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="font-medium text-red-600">ยกเลิก</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes -->
        <?php if ($po['notes']): ?>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-3">หมายเหตุ</h3>
                <p class="text-gray-600">
                    <?php echo nl2br(htmlspecialchars($po['notes'])); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Receive Modal -->
<?php if ($po['status'] === 'ordered'): ?>
    <div id="receiveModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('receiveModal').classList.add('hidden')">
        </div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
                <div class="p-6 text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">ยืนยันรับของ?</h3>
                    <p class="text-gray-500 mb-6">ระบบจะอัพเดทสต็อกอัตโนมัติ<br>ไม่สามารถย้อนกลับได้</p>
                    <div class="flex gap-3">
                        <button type="button" onclick="document.getElementById('receiveModal').classList.add('hidden')"
                            class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                        <form method="POST" class="flex-1">
                            <input type="hidden" name="action" value="receive">
                            <button type="submit"
                                class="w-full px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">ยืนยันรับของ</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Cancel Modal -->
<?php if (in_array($po['status'], ['draft', 'ordered'])): ?>
    <div id="cancelModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('cancelModal').classList.add('hidden')">
        </div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
                <div class="p-6 text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">ยืนยันยกเลิก?</h3>
                    <p class="text-gray-500 mb-6">ใบสั่งซื้อ
                        <?php echo htmlspecialchars($po['po_number']); ?><br>จะถูกยกเลิกและไม่สามารถกู้คืนได้</p>
                    <div class="flex gap-3">
                        <button type="button" onclick="document.getElementById('cancelModal').classList.add('hidden')"
                            class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ปิด</button>
                        <form method="POST" class="flex-1">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit"
                                class="w-full px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">ยืนยันยกเลิก</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('receiveModal')?.classList.add('hidden');
            document.getElementById('cancelModal')?.classList.add('hidden');
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>