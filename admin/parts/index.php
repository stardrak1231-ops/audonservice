<?php
/**
 * Parts Inventory Management - จัดการคลังอะไหล่
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Handle Create Part
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $partName = trim($_POST['part_name'] ?? '');
    $costPrice = $_POST['cost_price'] ?? 0;
    $sellPrice = $_POST['sell_price'] ?? 0;
    $stockQty = $_POST['stock_qty'] ?? 0;
    $reorderPoint = $_POST['reorder_point'] ?? 0;
    $unitSell = trim($_POST['unit_sell'] ?? 'ชิ้น');
    $unitPurchase = trim($_POST['unit_purchase'] ?? 'ชิ้น');
    $conversionRate = max(1, (int) ($_POST['conversion_rate'] ?? 1));

    if ($partName) {
        // Handle image upload
        $partImageUrl = null;
        if (isset($_FILES['part_image']) && $_FILES['part_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/parts/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['part_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $filename = 'part_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['part_image']['tmp_name'], $uploadDir . $filename)) {
                    $partImageUrl = '/model01/uploads/parts/' . $filename;
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO spare_parts (part_name, part_image_url, cost_price, sell_price, stock_qty, reorder_point, unit_sell, unit_purchase, conversion_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$partName, $partImageUrl, $costPrice, $sellPrice, $stockQty, $reorderPoint, $unitSell, $unitPurchase, $conversionRate]);
        header('Location: index.php?success=created');
        exit;
    }
}

// Handle Edit Part
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $partId = $_POST['part_id'] ?? 0;
    $partName = trim($_POST['part_name'] ?? '');
    $costPrice = $_POST['cost_price'] ?? 0;
    $sellPrice = $_POST['sell_price'] ?? 0;
    $stockQty = $_POST['stock_qty'] ?? 0;
    $reorderPoint = $_POST['reorder_point'] ?? 0;
    $unitSell = trim($_POST['unit_sell'] ?? 'ชิ้น');
    $unitPurchase = trim($_POST['unit_purchase'] ?? 'ชิ้น');
    $conversionRate = max(1, (int) ($_POST['conversion_rate'] ?? 1));

    if ($partId && $partName) {
        // Get current image
        $currentStmt = $pdo->prepare("SELECT part_image_url FROM spare_parts WHERE part_id = ?");
        $currentStmt->execute([$partId]);
        $current = $currentStmt->fetch();
        $partImageUrl = $current['part_image_url'];

        // Handle new image upload
        if (isset($_FILES['part_image']) && $_FILES['part_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/parts/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['part_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $filename = 'part_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['part_image']['tmp_name'], $uploadDir . $filename)) {
                    $partImageUrl = '/model01/uploads/parts/' . $filename;
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE spare_parts SET part_name = ?, part_image_url = ?, cost_price = ?, sell_price = ?, stock_qty = ?, reorder_point = ?, unit_sell = ?, unit_purchase = ?, conversion_rate = ? WHERE part_id = ?");
        $stmt->execute([$partName, $partImageUrl, $costPrice, $sellPrice, $stockQty, $reorderPoint, $unitSell, $unitPurchase, $conversionRate, $partId]);
        header('Location: index.php?success=updated');
        exit;
    }
}

// Handle Delete Part
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $partId = $_POST['part_id'] ?? 0;
    if ($partId) {
        $stmt = $pdo->prepare("DELETE FROM spare_parts WHERE part_id = ?");
        $stmt->execute([$partId]);
        header('Location: index.php?success=deleted');
        exit;
    }
}

$pageTitle = 'จัดการคลังอะไหล่';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Search & Filter
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';
$where = [];
$params = [];

if ($search) {
    $where[] = "part_name LIKE ?";
    $params[] = "%$search%";
}
if ($filter === 'low_stock') {
    $where[] = "stock_qty <= reorder_point";
}

$whereClause = $where ? " WHERE " . implode(" AND ", $where) : "";

// Get all parts - เรียง stock น้อยก่อน แล้วค่อยเรียงตามชื่อ
$sql = "SELECT * FROM spare_parts" . $whereClause . " ORDER BY stock_qty ASC, part_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parts = $stmt->fetchAll();

// Count low stock items
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM spare_parts WHERE stock_qty <= reorder_point")->fetchColumn();

$success = $_GET['success'] ?? '';
?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo [
            'created' => 'เพิ่มอะไหล่สำเร็จ',
            'updated' => 'แก้ไขข้อมูลสำเร็จ',
            'deleted' => 'ลบอะไหล่สำเร็จ',
            'stock_updated' => 'ปรับสต็อกสำเร็จ'
        ][$success] ?? 'ดำเนินการสำเร็จ'; ?>
    </div>
<?php endif; ?>

<!-- Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <p class="text-gray-500">จัดการข้อมูลอะไหล่และสต็อก</p>
    </div>
    <div class="flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                placeholder="ค้นหาอะไหล่..." class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 w-48">
            <select name="filter" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">ทั้งหมด</option>
                <option value="low_stock" <?php echo $filter === 'low_stock' ? 'selected' : ''; ?>>สต็อกต่ำ</option>
            </select>
            <button type="submit" class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg">ค้นหา</button>
        </form>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                </path>
            </svg>
            เพิ่มอะไหล่
        </button>
    </div>
</div>

<!-- Low Stock Alert -->
<?php if ($lowStockCount > 0): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
            </path>
        </svg>
        มีอะไหล่ <strong>
            <?php echo $lowStockCount; ?>
        </strong> รายการที่ต้องสั่งซื้อเพิ่ม
        <a href="?filter=low_stock" class="underline ml-2">ดูรายการ</a>
    </div>
<?php endif; ?>

<!-- Parts Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">อะไหล่</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">ราคาทุน</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">ราคาขาย</th>
                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">สต็อก</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($parts)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        <?php echo $search ? 'ไม่พบอะไหล่' : 'ยังไม่มีข้อมูลอะไหล่'; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($parts as $part): ?>
                    <?php $isLowStock = $part['stock_qty'] <= $part['reorder_point']; ?>
                    <tr class="hover:bg-gray-50 <?php echo $isLowStock ? 'bg-orange-50' : ''; ?>">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <?php if ($part['part_image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($part['part_image_url']); ?>"
                                        class="w-12 h-12 rounded-lg object-cover">
                                <?php else: ?>
                                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($part['part_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">#
                                        <?php echo $part['part_id']; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right text-gray-600">
                            ฿
                            <?php echo number_format($part['cost_price'], 2); ?>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold text-green-600">
                            ฿
                            <?php echo number_format($part['sell_price'], 2); ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium <?php echo $isLowStock ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'; ?>">
                                <?php echo $part['stock_qty']; ?>         <?php echo htmlspecialchars($part['unit_sell'] ?? 'ชิ้น'); ?>
                            </span>
                            <?php if ($part['conversion_rate'] > 1): ?>
                                <div class="text-xs text-gray-500 mt-1">
                                    ซื้อ: <?php echo htmlspecialchars($part['unit_purchase']); ?>
                                    (×<?php echo $part['conversion_rate']; ?>)
                                </div>
                            <?php endif; ?>
                            <?php if ($isLowStock): ?>
                                <div class="text-xs text-red-500 mt-1">ต้องสั่งซื้อ (ขั้นต่ำ:
                                    <?php echo $part['reorder_point']; ?>)
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($part)); ?>)"
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="แก้ไข">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                        </path>
                                    </svg>
                                </button>
                                <button
                                    onclick="confirmDelete(<?php echo $part['part_id']; ?>, '<?php echo htmlspecialchars($part['part_name'], ENT_QUOTES); ?>')"
                                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="ลบ">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-6 text-sm text-gray-500">รวมทั้งหมด
    <?php echo count($parts); ?> รายการ
</div>

<!-- Create Part Modal -->
<div id="createModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('createModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-xl font-semibold">เพิ่มอะไหล่ใหม่</h2>
                <button onclick="document.getElementById('createModal').classList.add('hidden')"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="block text-sm font-medium mb-1">ชื่ออะไหล่ <span class="text-red-500">*</span></label>
                    <input type="text" name="part_name" required
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="กรองน้ำมันเครื่อง">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">ราคาทุน (บาท)</label>
                        <input type="number" name="cost_price" step="0.01" min="0"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="100.00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">ราคาขาย (บาท)</label>
                        <input type="number" name="sell_price" step="0.01" min="0"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="150.00">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">สต็อกเริ่มต้น</label>
                        <input type="number" name="stock_qty" min="0" value="0"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">จุดสั่งซื้อขั้นต่ำ</label>
                        <input type="number" name="reorder_point" min="0" value="5"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <!-- Unit Settings -->
                <div class="border-t pt-4 mt-4">
                    <div class="text-sm font-medium text-gray-700 mb-3">ตั้งค่าหน่วยนับ</div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1">หน่วยขาย/ใช้งาน</label>
                            <input type="text" name="unit_sell" value="ชิ้น"
                                class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                placeholder="ขวด, ชิ้น">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">หน่วยสั่งซื้อ</label>
                            <input type="text" name="unit_purchase" value="ชิ้น"
                                class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                placeholder="กล่อง, โหล">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">อัตราแปลง</label>
                            <input type="number" name="conversion_rate" min="1" value="1"
                                class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                placeholder="1">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">เช่น 1 กล่อง = 12 ขวด (อัตราแปลง = 12)</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">รูปภาพ</label>
                    <input type="file" name="part_image" accept="image/*" class="w-full text-sm">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">บันทึก</button>
                    <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                        class="px-8 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Part Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('editModal').classList.add('hidden')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b sticky top-0 bg-white z-10">
                <h2 class="text-xl font-semibold">แก้ไขอะไหล่</h2>
                <button onclick="document.getElementById('editModal').classList.add('hidden')"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="part_id" id="edit_part_id">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Left Column: Image -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium mb-2">รูปภาพ</label>
                        <div id="edit_image_preview"
                            class="w-full aspect-square bg-gray-100 rounded-xl flex items-center justify-center mb-3 overflow-hidden">
                            <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                        </div>
                        <input type="file" name="part_image" accept="image/*" class="w-full text-sm">
                        <p class="text-xs text-gray-500 mt-1">เว้นว่างถ้าไม่เปลี่ยน</p>
                    </div>

                    <!-- Right Column: Details -->
                    <div class="md:col-span-2 space-y-4">
                        <!-- Part Name -->
                        <div>
                            <label class="block text-sm font-medium mb-1">ชื่ออะไหล่ <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="part_name" id="edit_part_name" required
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Prices -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">ราคาทุน (บาท)</label>
                                <input type="number" name="cost_price" id="edit_cost_price" step="0.01" min="0"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">ราคาขาย (บาท)</label>
                                <input type="number" name="sell_price" id="edit_sell_price" step="0.01" min="0"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>

                        <!-- Stock -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">สต็อกปัจจุบัน</label>
                                <input type="number" name="stock_qty" id="edit_stock_qty" min="0"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">จุดสั่งซื้อขั้นต่ำ</label>
                                <input type="number" name="reorder_point" id="edit_reorder_point" min="0"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>

                        <!-- Unit Settings -->
                        <div class="bg-gray-50 rounded-xl p-4">
                            <div class="text-sm font-medium text-gray-700 mb-3">ตั้งค่าหน่วยนับ</div>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium mb-1">หน่วยขาย/ใช้งาน</label>
                                    <input type="text" name="unit_sell" id="edit_unit_sell"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                        placeholder="ขวด, ชิ้น">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">หน่วยสั่งซื้อ</label>
                                    <input type="text" name="unit_purchase" id="edit_unit_purchase"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                        placeholder="กล่อง, โหล">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">อัตราแปลง</label>
                                    <input type="number" name="conversion_rate" id="edit_conversion_rate" min="1"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                        placeholder="1">
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">เช่น 1 กล่อง = 12 ขวด (อัตราแปลง = 12)</p>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-6 mt-6 border-t">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">บันทึก</button>
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                        class="px-8 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('deleteModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <svg class="w-16 h-16 mx-auto text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
                <h3 class="text-lg font-semibold mb-2">ยืนยันการลบ?</h3>
                <p class="text-gray-500 mb-6">ต้องการลบอะไหล่ "<span id="delete_name" class="font-medium"></span>"?</p>
                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="part_id" id="delete_part_id">
                    <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')"
                        class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                    <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">ลบ</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openEditModal(part) {
        document.getElementById('edit_part_id').value = part.part_id;
        document.getElementById('edit_part_name').value = part.part_name;
        document.getElementById('edit_cost_price').value = part.cost_price || '';
        document.getElementById('edit_sell_price').value = part.sell_price || '';
        document.getElementById('edit_stock_qty').value = part.stock_qty || 0;
        document.getElementById('edit_reorder_point').value = part.reorder_point || 0;
        document.getElementById('edit_unit_sell').value = part.unit_sell || 'ชิ้น';
        document.getElementById('edit_unit_purchase').value = part.unit_purchase || 'ชิ้น';
        document.getElementById('edit_conversion_rate').value = part.conversion_rate || 1;

        const preview = document.getElementById('edit_image_preview');
        if (part.part_image_url) {
            preview.innerHTML = '<img src="' + part.part_image_url + '" class="w-full h-full object-cover">';
        } else {
            preview.innerHTML = '<svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
        }

        document.getElementById('editModal').classList.remove('hidden');
    }

    function confirmDelete(partId, partName) {
        document.getElementById('delete_part_id').value = partId;
        document.getElementById('delete_name').textContent = partName;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('createModal').classList.add('hidden');
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.add('hidden');
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>