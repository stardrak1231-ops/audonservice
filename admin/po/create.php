<?php
/**
 * Create Purchase Order - สร้างใบสั่งซื้อใหม่
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId = $_POST['supplier_id'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];
    $action = $_POST['submit_action'] ?? 'draft';

    if ($supplierId && !empty($items)) {
        // Generate PO number
        $year = date('Y');
        $countStmt = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE YEAR(created_at) = $year");
        $count = $countStmt->fetchColumn() + 1;
        $poNumber = sprintf("PO-%s-%04d", $year, $count);

        // Calculate total
        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += ($item['qty'] ?? 0) * ($item['price'] ?? 0);
        }

        // Set status based on action
        $status = ($action === 'order') ? 'ordered' : 'draft';
        $orderedAt = ($action === 'order') ? date('Y-m-d H:i:s') : null;
        $orderedBy = ($action === 'order') ? $_SESSION['user_id'] : null;

        // Insert PO
        $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, supplier_id, status, total_amount, notes, ordered_by, ordered_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$poNumber, $supplierId, $status, $totalAmount, $notes, $orderedBy, $orderedAt]);
        $poId = $pdo->lastInsertId();

        // Insert items
        $itemStmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, part_id, qty_ordered, unit_price) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            if (!empty($item['part_id']) && ($item['qty'] ?? 0) > 0) {
                $itemStmt->execute([$poId, $item['part_id'], $item['qty'], $item['price'] ?? 0]);
            }
        }

        header('Location: view.php?id=' . $poId . '&success=created');
        exit;
    }
}

$pageTitle = 'สร้างใบสั่งซื้อใหม่';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get active suppliers
$suppliers = $pdo->query("SELECT * FROM suppliers WHERE status = 'active' ORDER BY supplier_name")->fetchAll();

// Get parts (for adding to PO)
$parts = $pdo->query("SELECT part_id, part_name, cost_price, unit_sell, unit_purchase, conversion_rate, stock_qty, reorder_point FROM spare_parts ORDER BY part_name")->fetchAll();
?>

<!-- Header -->
<div class="flex items-center gap-4 mb-6">
    <a href="index.php" class="p-2 hover:bg-gray-100 rounded-lg">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
            </path>
        </svg>
    </a>
    <div>
        <h1 class="text-2xl font-bold text-gray-800">สร้างใบสั่งซื้อใหม่</h1>
        <p class="text-gray-500">เพิ่มรายการอะไหล่ที่ต้องการสั่งซื้อ</p>
    </div>
</div>

<form method="POST" id="poForm">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - PO Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Supplier Selection -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">ข้อมูลผู้จำหน่าย</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">เลือกผู้จำหน่าย <span
                                class="text-red-500">*</span></label>
                        <select name="supplier_id" id="supplier_id" required
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- เลือกผู้จำหน่าย --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo $s['supplier_id']; ?>">
                                    <?php echo htmlspecialchars($s['supplier_name']); ?>
                                    <?php if ($s['contact_name']): ?>(
                                        <?php echo htmlspecialchars($s['contact_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" id="btnAddNote" onclick="toggleNoteField()"
                            class="text-blue-600 hover:text-blue-800 text-sm font-medium py-3 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                </path>
                            </svg>
                            + เพิ่มหมายเหตุ
                        </button>
                        <div id="noteField" class="hidden w-full">
                            <label class="block text-sm font-medium mb-1">หมายเหตุ</label>
                            <div class="flex gap-2">
                                <input type="text" name="notes" id="noteInput"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="เช่น: ฝากวางบิลที่ป้อมยาม...">
                                <button type="button" onclick="toggleNoteField()"
                                    class="text-gray-400 hover:text-red-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Selection -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">รายการอะไหล่</h3>
                    <button type="button" onclick="addItem()"
                        class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        เพิ่มรายการ
                    </button>
                </div>

                <div id="itemsContainer" class="space-y-3">
                    <div class="text-center text-gray-500 py-8" id="emptyMessage">
                        กดปุ่ม "เพิ่มรายการ" เพื่อเพิ่มอะไหล่ที่ต้องการสั่งซื้อ
                    </div>
                </div>

                <!-- Items Total -->
                <div class="border-t mt-4 pt-4">
                    <div class="flex justify-between items-center text-lg font-semibold">
                        <span>ยอดรวมทั้งหมด</span>
                        <span id="totalAmount" class="text-blue-600">฿0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Low Stock Suggestions -->
        <div class="space-y-6">
            <!-- Quick Add from Low Stock -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 bg-orange-500 rounded-full"></span>
                    อะไหล่ที่ต้องสั่งซื้อ
                </h3>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    <?php
                    $lowStockParts = array_filter($parts, fn($p) => $p['stock_qty'] <= $p['reorder_point']);
                    if (empty($lowStockParts)):
                        ?>
                        <div class="text-gray-500 text-sm py-4 text-center">ไม่มีอะไหล่ที่ต้องสั่งซื้อ</div>
                    <?php else: ?>
                        <?php foreach ($lowStockParts as $p): ?>
                            <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-sm">
                                        <?php echo htmlspecialchars($p['part_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        คงเหลือ:
                                        <?php echo $p['stock_qty']; ?>
                                        <?php echo htmlspecialchars($p['unit_sell']); ?>
                                        / ขั้นต่ำ:
                                        <?php echo $p['reorder_point']; ?>
                                    </div>
                                </div>
                                <button type="button" onclick="quickAddItem(<?php echo htmlspecialchars(json_encode($p)); ?>)"
                                    class="p-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-xl shadow-md p-6 space-y-3">
                <button type="submit" name="submit_action" value="draft"
                    class="w-full py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium">
                    บันทึกเป็นร่าง
                </button>
                <button type="submit" name="submit_action" value="order"
                    class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                    บันทึก + ส่งคำสั่งซื้อ
                </button>
                <a href="index.php"
                    class="block w-full py-3 text-center bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">
                    ยกเลิก
                </a>
            </div>
        </div>
    </div>
</form>

<!-- Item Template -->
<template id="itemTemplate">
    <div class="item-row bg-gray-50 rounded-lg p-4 relative">
        <div class="grid grid-cols-12 gap-3 items-center">
            <div class="col-span-12 md:col-span-5 relative">
                <input type="hidden" name="items[INDEX][part_id]" class="part-id-input">
                <input type="text" class="part-search-input w-full px-3 py-2 border rounded-lg text-sm bg-white"
                    placeholder="พิมพ์ชื่ออะไหล่เพื่อค้นหา..." autocomplete="off">
                <div
                    class="part-results absolute z-10 top-full left-0 w-full bg-white border border-gray-200 rounded-lg shadow-xl mt-1 max-h-48 overflow-y-auto hidden">
                </div>
            </div>
            <div class="col-span-4 md:col-span-2">
                <input type="number" name="items[INDEX][qty]"
                    class="qty-input w-full px-3 py-2 border rounded-lg text-sm text-center" min="1" value="1"
                    placeholder="จำนวน">
            </div>
            <div class="col-span-4 md:col-span-2">
                <input type="number" name="items[INDEX][price]"
                    class="price-input w-full px-3 py-2 border rounded-lg text-sm text-right" min="0" step="0.01"
                    placeholder="ต้นทุน">
            </div>
            <div class="col-span-3 md:col-span-2 text-right">
                <span class="item-total font-medium text-sm">฿0.00</span>
            </div>
            <div class="col-span-1 text-right">
                <button type="button" onclick="removeItem(this)" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
    let itemIndex = 0;
    const partsData = <?php echo json_encode($parts); ?>;

    function addItem() {
        const container = document.getElementById('itemsContainer');
        const template = document.getElementById('itemTemplate');
        const emptyMessage = document.getElementById('emptyMessage');

        if (emptyMessage) emptyMessage.style.display = 'none';

        const clone = template.content.cloneNode(true);
        const html = clone.querySelector('.item-row').outerHTML.replace(/INDEX/g, itemIndex);
        container.insertAdjacentHTML('beforeend', html);

        const row = container.lastElementChild;
        const searchInput = row.querySelector('.part-search-input');

        // Search Logic
        searchInput.addEventListener('input', function () {
            searchParts(this, row);
        });

        // Click outside to close results
        document.addEventListener('click', function (e) {
            if (!row.contains(e.target)) {
                row.querySelector('.part-results').classList.add('hidden');
            }
        });

        row.querySelector('.qty-input').addEventListener('input', () => calculateItemTotal(row));
        row.querySelector('.price-input').addEventListener('input', () => calculateItemTotal(row));

        itemIndex++;
        return row; // Return row for quickAddItem
    }

    function searchParts(input, row) {
        const query = input.value.toLowerCase();
        const resultsContainer = row.querySelector('.part-results');

        if (query.length < 1) {
            resultsContainer.classList.add('hidden');
            return;
        }

        const filtered = partsData.filter(p => p.part_name.toLowerCase().includes(query));

        if (filtered.length === 0) {
            resultsContainer.innerHTML = '<div class="p-3 text-gray-500 text-center text-xs">ไม่พบอะไหล่</div>';
        } else {
            resultsContainer.innerHTML = filtered.map(p =>
                `<div class="p-2 hover:bg-blue-50 cursor-pointer border-b last:border-0 text-sm" 
                      onclick='selectPart(this, ${JSON.stringify(p)})'>
                    <div class="font-medium">${p.part_name}</div>
                    <div class="text-xs text-gray-500">
                        ทุน: ฿${parseFloat(p.cost_price).toLocaleString()} | 
                        ซื้อ: ${p.unit_purchase} (1:${p.conversion_rate})
                    </div>
                </div>`
            ).join('');
        }
        resultsContainer.classList.remove('hidden');
    }

    function selectPart(element, part) {
        const row = element.closest('.item-row');

        row.querySelector('.part-id-input').value = part.part_id;
        row.querySelector('.part-search-input').value = part.part_name;
        row.querySelector('.price-input').value = part.cost_price || 0;

        row.querySelector('.part-results').classList.add('hidden');
        calculateItemTotal(row);
    }

    function quickAddItem(part) {
        const row = addItem();

        // Set values
        row.querySelector('.part-id-input').value = part.part_id;
        row.querySelector('.part-search-input').value = part.part_name;
        row.querySelector('.price-input').value = part.cost_price || 0;

        // Calculate suggested quantity
        const suggestedQty = Math.max(1, Math.ceil((part.reorder_point - part.stock_qty) / part.conversion_rate));
        row.querySelector('.qty-input').value = suggestedQty;

        calculateItemTotal(row);
    }

    function removeItem(btn) {
        const row = btn.closest('.item-row');
        row.remove();
        calculateGrandTotal();

        // Show empty message if no items
        if (document.querySelectorAll('.item-row').length === 0) {
            document.getElementById('emptyMessage').style.display = 'block';
        }
    }

    function calculateItemTotal(row) {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const total = qty * price;
        row.querySelector('.item-total').textContent = '฿' + total.toLocaleString('en-US', { minimumFractionDigits: 2 });
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let total = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            total += qty * price;
        });
        document.getElementById('totalAmount').textContent = '฿' + total.toLocaleString('en-US', { minimumFractionDigits: 2 });
    }

    // Form validation
    document.getElementById('poForm').addEventListener('submit', function (e) {
        const items = document.querySelectorAll('.item-row');
        if (items.length === 0) {
            e.preventDefault();
            alert('กรุณาเพิ่มรายการอะไหล่อย่างน้อย 1 รายการ');
            return;
        }

        let hasValidItem = false;
        items.forEach(row => {
            const partId = row.querySelector('.part-id-input').value;
            const qty = parseInt(row.querySelector('.qty-input').value) || 0;
            if (partId && qty > 0) hasValidItem = true;
        });

        if (!hasValidItem) {
            e.preventDefault();
            alert('กรุณาเลือกอะไหล่และระบุจำนวน');
        }
    });

    function toggleNoteField() {
        const btn = document.getElementById('btnAddNote');
        const field = document.getElementById('noteField');
        const input = document.getElementById('noteInput');

        if (field.classList.contains('hidden')) {
            field.classList.remove('hidden');
            btn.classList.add('hidden');
            input.focus();
        } else {
            field.classList.add('hidden');
            btn.classList.remove('hidden');
            input.value = ''; // Clear value when hiding
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>