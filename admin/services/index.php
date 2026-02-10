<?php
/**
 * Services Management - จัดการรายการบริการมาตรฐาน
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Handle Create Service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $serviceName = trim($_POST['service_name'] ?? '');
    $standardPrice = $_POST['standard_price'] ?? 0;
    $estimatedMinutes = $_POST['estimated_minutes'] ?? null;

    if ($serviceName) {
        $stmt = $pdo->prepare("INSERT INTO service_items (service_name, standard_price, estimated_minutes) VALUES (?, ?, ?)");
        $stmt->execute([$serviceName, $standardPrice, $estimatedMinutes ?: null]);
        header('Location: index.php?success=created');
        exit;
    }
}

// Handle Edit Service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $serviceId = $_POST['service_id'] ?? 0;
    $serviceName = trim($_POST['service_name'] ?? '');
    $standardPrice = $_POST['standard_price'] ?? 0;
    $estimatedMinutes = $_POST['estimated_minutes'] ?? null;

    if ($serviceId && $serviceName) {
        $stmt = $pdo->prepare("UPDATE service_items SET service_name = ?, standard_price = ?, estimated_minutes = ? WHERE service_id = ?");
        $stmt->execute([$serviceName, $standardPrice, $estimatedMinutes ?: null, $serviceId]);
        header('Location: index.php?success=updated');
        exit;
    }
}

// Handle Delete Service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $serviceId = $_POST['service_id'] ?? 0;
    if ($serviceId) {
        $stmt = $pdo->prepare("DELETE FROM service_items WHERE service_id = ?");
        $stmt->execute([$serviceId]);
        header('Location: index.php?success=deleted');
        exit;
    }
}

$pageTitle = 'จัดการรายการบริการ';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Search
$search = $_GET['search'] ?? '';
$searchWhere = $search ? " WHERE service_name LIKE ?" : '';
$searchParams = $search ? ["%$search%"] : [];

// Get all services
$sql = "SELECT * FROM service_items" . $searchWhere . " ORDER BY CONVERT(service_name USING tis620) ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($searchParams);
$services = $stmt->fetchAll();

$success = $_GET['success'] ?? '';
?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo ['created' => 'เพิ่มบริการสำเร็จ', 'updated' => 'แก้ไขข้อมูลสำเร็จ', 'deleted' => 'ลบบริการสำเร็จ'][$success] ?? 'ดำเนินการสำเร็จ'; ?>
    </div>
<?php endif; ?>

<!-- Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <p class="text-gray-500">รายการบริการมาตรฐานและราคา</p>
    </div>
    <div class="flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                placeholder="ค้นหาบริการ..." class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 w-48">
            <button type="submit" class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg">ค้นหา</button>
        </form>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                </path>
            </svg>
            เพิ่มบริการ
        </button>
    </div>
</div>

<!-- Services Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">รหัส</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ชื่อบริการ</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">ราคา (บาท)</th>
                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">เวลาประมาณ</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($services)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        <?php echo $search ? 'ไม่พบบริการ' : 'ยังไม่มีรายการบริการ'; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($services as $service): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <span class="font-mono text-sm text-gray-500">#
                                <?php echo $service['service_id']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">
                                <?php echo htmlspecialchars($service['service_name']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-semibold text-green-600">
                                ฿
                                <?php echo number_format($service['standard_price'], 2); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($service['estimated_minutes']): ?>
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <?php echo $service['estimated_minutes']; ?> นาที
                                </span>
                            <?php else: ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($service)); ?>)"
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="แก้ไข">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                        </path>
                                    </svg>
                                </button>
                                <button
                                    onclick="confirmDelete(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['service_name'], ENT_QUOTES); ?>')"
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
    <?php echo count($services); ?> รายการ
</div>

<!-- Create Service Modal -->
<div id="createModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('createModal').classList.add('hidden')">
    </div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-xl font-semibold">เพิ่มบริการใหม่</h2>
                <button onclick="document.getElementById('createModal').classList.add('hidden')"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="block text-sm font-medium mb-1">ชื่อบริการ <span class="text-red-500">*</span></label>
                    <input type="text" name="service_name" required
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="เปลี่ยนถ่ายน้ำมันเครื่อง">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">ราคา (บาท)</label>
                        <input type="number" name="standard_price" step="0.01" min="0"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="500.00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">เวลาประมาณ (นาที)</label>
                        <input type="number" name="estimated_minutes" min="1"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="30">
                    </div>
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

<!-- Edit Service Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('editModal').classList.add('hidden')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-xl font-semibold">แก้ไขบริการ</h2>
                <button onclick="document.getElementById('editModal').classList.add('hidden')"
                    class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="service_id" id="edit_service_id">
                <div>
                    <label class="block text-sm font-medium mb-1">ชื่อบริการ <span class="text-red-500">*</span></label>
                    <input type="text" name="service_name" id="edit_service_name" required
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">ราคา (บาท)</label>
                        <input type="number" name="standard_price" id="edit_standard_price" step="0.01" min="0"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">เวลาประมาณ (นาที)</label>
                        <input type="number" name="estimated_minutes" id="edit_estimated_minutes" min="1"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium">บันทึก</button>
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                        class="px-8 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">ยกเลิก</button>
                </div>
            </form>
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
                <p class="text-gray-500 mb-6">ต้องการลบบริการ "<span id="delete_name" class="font-medium"></span>"?</p>
                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="service_id" id="delete_service_id">
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
    function openEditModal(service) {
        document.getElementById('edit_service_id').value = service.service_id;
        document.getElementById('edit_service_name').value = service.service_name;
        document.getElementById('edit_standard_price').value = service.standard_price || '';
        document.getElementById('edit_estimated_minutes').value = service.estimated_minutes || '';
        document.getElementById('editModal').classList.remove('hidden');
    }

    function confirmDelete(serviceId, serviceName) {
        document.getElementById('delete_service_id').value = serviceId;
        document.getElementById('delete_name').textContent = serviceName;
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