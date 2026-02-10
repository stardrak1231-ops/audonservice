<?php
/**
 * Purchase Order & Supplier Management - จัดซื้ออะไหล่
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Get active tab
$tab = $_GET['tab'] ?? 'po';

// ==================== SUPPLIER HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['module']) && $_POST['module'] === 'supplier') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $supplierName = trim($_POST['supplier_name'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($supplierName) {
            $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, contact_name, phone, email, address, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$supplierName, $contactName, $phone, $email, $address, $notes]);
            header('Location: index.php?tab=suppliers&success=supplier_created');
            exit;
        }
    }
    
    if ($action === 'edit') {
        $supplierId = $_POST['supplier_id'] ?? 0;
        $supplierName = trim($_POST['supplier_name'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($supplierId && $supplierName) {
            $stmt = $pdo->prepare("UPDATE suppliers SET supplier_name = ?, contact_name = ?, phone = ?, email = ?, address = ?, notes = ?, status = ? WHERE supplier_id = ?");
            $stmt->execute([$supplierName, $contactName, $phone, $email, $address, $notes, $status, $supplierId]);
            header('Location: index.php?tab=suppliers&success=supplier_updated');
            exit;
        }
    }
    
    if ($action === 'toggle_status') {
        $supplierId = $_POST['supplier_id'] ?? 0;
        $newStatus = $_POST['new_status'] ?? 'inactive';
        if ($supplierId) {
            $stmt = $pdo->prepare("UPDATE suppliers SET status = ? WHERE supplier_id = ?");
            $stmt->execute([$newStatus, $supplierId]);
            $successMsg = $newStatus === 'inactive' ? 'supplier_suspended' : 'supplier_activated';
            header('Location: index.php?tab=suppliers&success=' . $successMsg);
            exit;
        }
    }
}

$pageTitle = 'จัดซื้ออะไหล่';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get data based on tab
if ($tab === 'suppliers') {
    $search = $_GET['search'] ?? '';
    $filter = $_GET['filter'] ?? '';
    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(supplier_name LIKE ? OR contact_name LIKE ? OR phone LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    if ($filter === 'active') {
        $where[] = "status = 'active'";
    } elseif ($filter === 'inactive') {
        $where[] = "status = 'inactive'";
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare("SELECT * FROM suppliers $whereClause ORDER BY supplier_name");
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll();
} else {
    // PO tab
    $search = $_GET['search'] ?? '';
    $filter = $_GET['filter'] ?? '';
    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(po.po_number LIKE ? OR s.supplier_name LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%"]);
    }
    if ($filter && in_array($filter, ['draft', 'ordered', 'received', 'cancelled'])) {
        $where[] = "po.status = ?";
        $params[] = $filter;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare("
        SELECT po.*, s.supplier_name, 
               CONCAT(u1.first_name, ' ', u1.last_name) as ordered_by_name, 
               CONCAT(u2.first_name, ' ', u2.last_name) as received_by_name
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN users u1 ON po.ordered_by = u1.user_id
        LEFT JOIN users u2 ON po.received_by = u2.user_id
        $whereClause
        ORDER BY po.created_at DESC
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Count by status
    $statusCounts = [];
    $countStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM purchase_orders GROUP BY status");
    while ($row = $countStmt->fetch()) {
        $statusCounts[$row['status']] = $row['cnt'];
    }
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo [
            'created' => 'สร้างใบสั่งซื้อสำเร็จ',
            'ordered' => 'บันทึกสถานะสั่งซื้อแล้ว',
            'received' => 'รับของและอัพเดทสต็อกสำเร็จ',
            'cancelled' => 'ยกเลิกใบสั่งซื้อสำเร็จ',
            'supplier_created' => 'เพิ่มผู้จำหน่ายสำเร็จ',
            'supplier_updated' => 'แก้ไขข้อมูลสำเร็จ',
            'supplier_suspended' => 'ระงับผู้จำหน่ายสำเร็จ',
            'supplier_activated' => 'เปิดใช้งานผู้จำหน่ายสำเร็จ'
        ][$success] ?? 'ดำเนินการสำเร็จ'; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <?php echo [
            'has_po' => 'ไม่สามารถลบได้ เนื่องจากมีใบสั่งซื้อที่เกี่ยวข้อง'
        ][$error] ?? 'เกิดข้อผิดพลาด'; ?>
    </div>
<?php endif; ?>

<!-- Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">จัดซื้ออะไหล่</h1>
        <p class="text-gray-500">จัดการใบสั่งซื้อและผู้จำหน่าย</p>
    </div>
</div>

<!-- Tabs -->
<div class="bg-white rounded-xl shadow-md mb-6">
    <div class="border-b flex">
        <a href="?tab=po" 
            class="px-6 py-4 font-medium border-b-2 transition-colors <?php echo $tab === 'po' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                ใบสั่งซื้อ (PO)
            </div>
        </a>
        <a href="?tab=suppliers" 
            class="px-6 py-4 font-medium border-b-2 transition-colors <?php echo $tab === 'suppliers' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                ผู้จำหน่าย
            </div>
        </a>
    </div>

    <!-- Tab Content -->
    <div class="p-6">
        <?php if ($tab === 'suppliers'): ?>
            <!-- ==================== SUPPLIERS TAB ==================== -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="suppliers">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="ค้นหาผู้จำหน่าย..." class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 w-48">
                    <select name="filter" class="px-3 py-2 border rounded-lg">
                        <option value="">ทั้งหมด</option>
                        <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>ใช้งาน</option>
                        <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>ไม่ใช้งาน</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg">ค้นหา</button>
                </form>
                <button onclick="document.getElementById('supplierModal').classList.remove('hidden'); document.getElementById('supplierModalTitle').textContent = 'เพิ่มผู้จำหน่ายใหม่'; document.getElementById('supplierForm').reset(); document.getElementById('supplier_action').value = 'create';"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    เพิ่มผู้จำหน่าย
                </button>
            </div>

            <!-- Suppliers Table -->
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ผู้จำหน่าย</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ผู้ติดต่อ</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">เบอร์โทร</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">สถานะ</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">ยังไม่มีข้อมูลผู้จำหน่าย</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $s): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium"><?php echo htmlspecialchars($s['supplier_name']); ?></div>
                                    <?php if ($s['notes']): ?><div class="text-xs text-gray-500"><?php echo htmlspecialchars($s['notes']); ?></div><?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($s['contact_name'] ?: '-'); ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($s['phone']): ?>
                                        <a href="tel:<?php echo $s['phone']; ?>" class="text-blue-600 hover:underline"><?php echo htmlspecialchars($s['phone']); ?></a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $s['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'; ?>">
                                        <?php echo $s['status'] === 'active' ? 'ใช้งาน' : 'ไม่ใช้งาน'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button onclick="editSupplier(<?php echo htmlspecialchars(json_encode($s)); ?>)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <?php if ($s['status'] === 'active'): ?>
                                    <button onclick="toggleStatus(<?php echo $s['supplier_id']; ?>, '<?php echo htmlspecialchars($s['supplier_name']); ?>', 'inactive')" class="p-1.5 text-orange-600 hover:bg-orange-50 rounded-lg" title="ระงับ">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                    </button>
                                    <?php else: ?>
                                    <button onclick="toggleStatus(<?php echo $s['supplier_id']; ?>, '<?php echo htmlspecialchars($s['supplier_name']); ?>', 'active')" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg" title="เปิดใช้งาน">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php else: ?>
            <!-- ==================== PO TAB ==================== -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="po">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="ค้นหาเลข PO..." class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 w-48">
                    <select name="filter" class="px-3 py-2 border rounded-lg">
                        <option value="">ทั้งหมด</option>
                        <option value="draft" <?php echo $filter === 'draft' ? 'selected' : ''; ?>>ร่าง</option>
                        <option value="ordered" <?php echo $filter === 'ordered' ? 'selected' : ''; ?>>สั่งซื้อแล้ว</option>
                        <option value="received" <?php echo $filter === 'received' ? 'selected' : ''; ?>>รับครบแล้ว</option>
                        <option value="cancelled" <?php echo $filter === 'cancelled' ? 'selected' : ''; ?>>ยกเลิก</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg">ค้นหา</button>
                </form>
                <a href="create.php" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    สร้าง PO ใหม่
                </a>
            </div>

            <!-- Status Summary -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold"><?php echo $statusCounts['draft'] ?? 0; ?></div>
                    <div class="text-xs text-gray-500">ร่าง</div>
                </div>
                <div class="bg-blue-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $statusCounts['ordered'] ?? 0; ?></div>
                    <div class="text-xs text-blue-600">รอรับของ</div>
                </div>
                <div class="bg-green-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-green-600"><?php echo $statusCounts['received'] ?? 0; ?></div>
                    <div class="text-xs text-green-600">รับครบแล้ว</div>
                </div>
                <div class="bg-red-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-red-600"><?php echo $statusCounts['cancelled'] ?? 0; ?></div>
                    <div class="text-xs text-red-600">ยกเลิก</div>
                </div>
            </div>

            <!-- PO Table -->
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">เลข PO</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ผู้จำหน่าย</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">ยอดรวม</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">สถานะ</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">วันที่</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                ยังไม่มีใบสั่งซื้อ <a href="create.php" class="text-blue-600 hover:underline">สร้าง PO ใหม่</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <?php
                            $sc = [
                                'draft' => 'bg-gray-100 text-gray-700',
                                'ordered' => 'bg-blue-100 text-blue-700',
                                'received' => 'bg-green-100 text-green-700',
                                'cancelled' => 'bg-red-100 text-red-700'
                            ][$o['status']] ?? 'bg-gray-100 text-gray-700';
                            $label = ['draft' => 'ร่าง', 'ordered' => 'สั่งซื้อแล้ว', 'received' => 'รับครบแล้ว', 'cancelled' => 'ยกเลิก'][$o['status']] ?? $o['status'];
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="view.php?id=<?php echo $o['po_id']; ?>" class="font-medium text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($o['po_number']); ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($o['supplier_name'] ?? '-'); ?></td>
                                <td class="px-4 py-3 text-right font-medium">฿<?php echo number_format($o['total_amount'], 2); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $sc; ?>"><?php echo $label; ?></span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-sm"><?php echo date('d/m/Y', strtotime($o['created_at'])); ?></td>
                                <td class="px-4 py-3 text-right">
                                    <a href="view.php?id=<?php echo $o['po_id']; ?>" class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm">ดู</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Supplier Modal -->
<div id="supplierModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('supplierModal').classList.add('hidden')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
            <div class="flex items-center justify-between p-6 border-b">
                <h2 id="supplierModalTitle" class="text-xl font-semibold">เพิ่มผู้จำหน่ายใหม่</h2>
                <button onclick="document.getElementById('supplierModal').classList.add('hidden')" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form id="supplierForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="module" value="supplier">
                <input type="hidden" name="action" id="supplier_action" value="create">
                <input type="hidden" name="supplier_id" id="supplier_id">
                <div>
                    <label class="block text-sm font-medium mb-1">ชื่อร้าน/บริษัท <span class="text-red-500">*</span></label>
                    <input type="text" name="supplier_name" id="supplier_name" required class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">ชื่อผู้ติดต่อ</label>
                        <input type="text" name="contact_name" id="contact_name" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">เบอร์โทร</label>
                        <input type="text" name="phone" id="supplier_phone" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">อีเมล</label>
                    <input type="email" name="email" id="supplier_email" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">ที่อยู่</label>
                    <textarea name="address" id="supplier_address" rows="2" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">หมายเหตุ</label>
                        <input type="text" name="notes" id="supplier_notes" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div id="statusField" class="hidden">
                        <label class="block text-sm font-medium mb-1">สถานะ</label>
                        <select name="status" id="supplier_status" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="active">ใช้งาน</option>
                            <option value="inactive">ไม่ใช้งาน</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">บันทึก</button>
                    <button type="button" onclick="document.getElementById('supplierModal').classList.add('hidden')" class="px-8 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Modal -->
<div id="toggleModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('toggleModal').classList.add('hidden')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div id="toggleIcon" class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"></div>
                <h3 id="toggleTitle" class="text-lg font-semibold mb-2"></h3>
                <p class="text-gray-600 mb-6">คุณแน่ใจหรือไม่ที่จะ<span id="toggleAction"></span> "<span id="toggle_name" class="font-medium"></span>"?</p>
                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="module" value="supplier">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="supplier_id" id="toggle_supplier_id">
                    <input type="hidden" name="new_status" id="toggle_new_status">
                    <button type="button" onclick="document.getElementById('toggleModal').classList.add('hidden')" class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                    <button type="submit" id="toggleSubmit" class="flex-1 px-4 py-2.5 rounded-lg font-medium">ยืนยัน</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editSupplier(s) {
    document.getElementById('supplierModalTitle').textContent = 'แก้ไขผู้จำหน่าย';
    document.getElementById('supplier_action').value = 'edit';
    document.getElementById('supplier_id').value = s.supplier_id;
    document.getElementById('supplier_name').value = s.supplier_name || '';
    document.getElementById('contact_name').value = s.contact_name || '';
    document.getElementById('supplier_phone').value = s.phone || '';
    document.getElementById('supplier_email').value = s.email || '';
    document.getElementById('supplier_address').value = s.address || '';
    document.getElementById('supplier_notes').value = s.notes || '';
    document.getElementById('supplier_status').value = s.status || 'active';
    document.getElementById('statusField').classList.remove('hidden');
    document.getElementById('supplierModal').classList.remove('hidden');
}

function toggleStatus(id, name, newStatus) {
    document.getElementById('toggle_supplier_id').value = id;
    document.getElementById('toggle_name').textContent = name;
    document.getElementById('toggle_new_status').value = newStatus;
    
    const iconDiv = document.getElementById('toggleIcon');
    const submitBtn = document.getElementById('toggleSubmit');
    
    if (newStatus === 'inactive') {
        document.getElementById('toggleTitle').textContent = 'ยืนยันการระงับ';
        document.getElementById('toggleAction').textContent = 'ระงับ';
        iconDiv.className = 'w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4';
        iconDiv.innerHTML = '<svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>';
        submitBtn.className = 'flex-1 px-4 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium';
        submitBtn.textContent = 'ระงับ';
    } else {
        document.getElementById('toggleTitle').textContent = 'ยืนยันเปิดใช้งาน';
        document.getElementById('toggleAction').textContent = 'เปิดใช้งาน';
        iconDiv.className = 'w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4';
        iconDiv.innerHTML = '<svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        submitBtn.className = 'flex-1 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium';
        submitBtn.textContent = 'เปิดใช้งาน';
    }
    
    document.getElementById('toggleModal').classList.remove('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('supplierModal').classList.add('hidden');
        document.getElementById('toggleModal').classList.add('hidden');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>