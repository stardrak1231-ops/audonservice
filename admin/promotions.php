<?php
/**
 * Promotions Management - จัดการข่าวสาร/โปรโมชั่น
 */

require_once '../config/database.php';
require_once '../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();
$currentUser = getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $promoId = $_POST['promo_id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $promoType = $_POST['promo_type'] ?? 'news';
        $startDate = $_POST['start_date'] ?: null;
        $endDate = $_POST['end_date'] ?: null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Handle image upload
        $imageUrl = $_POST['existing_image'] ?? '';
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = '../uploads/promotions/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $filename = 'promo_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $imageUrl = '/model01/uploads/promotions/' . $filename;
            }
        }

        if (empty($title)) {
            $error = 'กรุณากรอกหัวข้อ';
        } else {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO promotions (title, content, image_url, promo_type, start_date, end_date, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $imageUrl, $promoType, $startDate, $endDate, $isActive, $currentUser['user_id']]);
                $message = 'เพิ่มข่าวสาร/โปรโมชั่นเรียบร้อย';
            } else {
                $stmt = $pdo->prepare("UPDATE promotions SET title = ?, content = ?, image_url = ?, promo_type = ?, start_date = ?, end_date = ?, is_active = ? WHERE promo_id = ?");
                $stmt->execute([$title, $content, $imageUrl, $promoType, $startDate, $endDate, $isActive, $promoId]);
                $message = 'แก้ไขข่าวสาร/โปรโมชั่นเรียบร้อย';
            }
        }
    }

    if ($action === 'delete') {
        $promoId = $_POST['promo_id'] ?? 0;
        $pdo->prepare("DELETE FROM promotions WHERE promo_id = ?")->execute([$promoId]);
        $message = 'ลบข่าวสาร/โปรโมชั่นเรียบร้อย';
    }

    if ($action === 'toggle') {
        $promoId = $_POST['promo_id'] ?? 0;
        $pdo->prepare("UPDATE promotions SET is_active = NOT is_active WHERE promo_id = ?")->execute([$promoId]);
        $message = 'เปลี่ยนสถานะเรียบร้อย';
    }
}

// Filter
$filter = $_GET['type'] ?? '';
$whereClause = "";
if ($filter === 'news') {
    $whereClause = "WHERE promo_type = 'news'";
} elseif ($filter === 'promotion') {
    $whereClause = "WHERE promo_type = 'promotion'";
}

// Get all promotions
$promotions = $pdo->query("SELECT p.*, u.first_name, u.last_name FROM promotions p LEFT JOIN users u ON p.created_by = u.user_id $whereClause ORDER BY p.created_at DESC")->fetchAll();

// Get edit item if specified
$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE promo_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editItem = $stmt->fetch();
}

$pageTitle = 'ข่าวสาร/โปรโมชั่น';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<?php if ($message): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Header & Actions -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="flex bg-white rounded-lg shadow p-1">
            <a href="?"
                class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo !$filter ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
                ทั้งหมด
            </a>
            <a href="?type=news"
                class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter === 'news' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
                📰 ข่าวสาร
            </a>
            <a href="?type=promotion"
                class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter === 'promotion' ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
                🎉 โปรโมชั่น
            </a>
        </div>
        <span class="text-gray-500 text-sm"><?php echo count($promotions); ?> รายการ</span>
    </div>
    <button onclick="openModal()"
        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium flex items-center gap-2 shadow-lg shadow-blue-200">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        เพิ่มใหม่
    </button>
</div>

<!-- Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <?php if (empty($promotions)): ?>
        <div class="p-12 text-center">
            <div class="text-6xl mb-4">📭</div>
            <p class="text-gray-500">ยังไม่มีข่าวสาร/โปรโมชั่น</p>
            <button onclick="openModal()" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg">+ เพิ่มรายการแรก</button>
        </div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ประเภท</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">หัวข้อ</th>
                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">ช่วงเวลา</th>
                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">สถานะ</th>
                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($promotions as $promo): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <?php if ($promo['promo_type'] === 'news'): ?>
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                                    📰 ข่าวสาร
                                </span>
                            <?php else: ?>
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-100 text-orange-700 rounded-full text-sm font-medium">
                                    🎉 โปรโมชั่น
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <?php if ($promo['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($promo['image_url']); ?>"
                                        class="w-12 h-12 object-cover rounded-lg">
                                <?php endif; ?>
                                <div>
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($promo['title']); ?>
                                    </div>
                                    <?php if ($promo['content']): ?>
                                        <div class="text-sm text-gray-500 truncate max-w-xs">
                                            <?php echo htmlspecialchars(mb_substr($promo['content'], 0, 50)); ?>...</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center text-sm">
                            <?php if ($promo['start_date'] && $promo['end_date']): ?>
                                <div class="text-gray-700">
                                    <?php echo date('d/m/Y', strtotime($promo['start_date'])); ?>
                                </div>
                                <div class="text-gray-400 text-xs">ถึง <?php echo date('d/m/Y', strtotime($promo['end_date'])); ?>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-400">ไม่จำกัด</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="promo_id" value="<?php echo $promo['promo_id']; ?>">
                                <button type="submit"
                                    class="<?php echo $promo['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?> px-3 py-1.5 rounded-full text-sm font-medium hover:opacity-80 transition">
                                    <?php echo $promo['is_active'] ? '✅ เปิดใช้งาน' : '⏸️ ปิดใช้งาน'; ?>
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <a href="?edit=<?php echo $promo['promo_id']; ?>"
                                    onclick="editPromo(<?php echo htmlspecialchars(json_encode($promo)); ?>); return false;"
                                    class="p-2 hover:bg-blue-50 rounded-lg text-blue-600 transition" title="แก้ไข">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                        </path>
                                    </svg>
                                </a>
                                <form method="POST" class="inline" onsubmit="return confirm('ยืนยันการลบ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="promo_id" value="<?php echo $promo['promo_id']; ?>">
                                    <button type="submit" class="p-2 hover:bg-red-50 rounded-lg text-red-600 transition"
                                        title="ลบ">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal -->
<div id="promoModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold" id="modalTitle">➕ เพิ่มข่าวสาร/โปรโมชั่น</h3>
            <button onclick="closeModal()" class="p-2 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-4 space-y-3">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="promo_id" id="promoId" value="">
            <input type="hidden" name="existing_image" id="existingImage" value="">

            <!-- Type Selection -->
            <div class="grid grid-cols-2 gap-2">
                <label class="relative">
                    <input type="radio" name="promo_type" value="news" id="typeNews" class="peer sr-only" checked>
                    <div class="p-3 border-2 rounded-lg cursor-pointer text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition">
                        <span class="text-xl mr-1">📰</span><span class="font-medium">ข่าวสาร</span>
                    </div>
                </label>
                <label class="relative">
                    <input type="radio" name="promo_type" value="promotion" id="typePromo" class="peer sr-only">
                    <div class="p-3 border-2 rounded-lg cursor-pointer text-center peer-checked:border-orange-500 peer-checked:bg-orange-50 transition">
                        <span class="text-xl mr-1">🎉</span><span class="font-medium">โปรโมชั่น</span>
                    </div>
                </label>
            </div>

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">หัวข้อ <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="promoTitle" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="ระบุหัวข้อ">
            </div>

            <!-- Content -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เนื้อหา</label>
                <textarea name="content" id="promoContent" rows="2"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
            </div>

            <!-- Image -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">รูปภาพ</label>
                <div id="imagePreview" class="hidden mb-2 relative">
                    <img id="previewImg" src="" class="w-full h-24 object-cover rounded-lg">
                    <button type="button" onclick="clearImage()"
                        class="absolute top-1 right-1 bg-red-500 text-white p-1 rounded-full hover:bg-red-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <input type="file" name="image" id="imageInput" accept="image/*"
                    class="w-full text-sm border border-gray-300 rounded-lg p-2 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </div>

            <!-- Date Range + Active Toggle -->
            <div class="grid grid-cols-5 gap-3 items-end">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">📅 วันเริ่ม</label>
                    <input type="date" name="start_date" id="startDate"
                        class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">📅 วันสิ้นสุด</label>
                    <input type="date" name="end_date" id="endDate"
                        class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div class="text-center">
                    <label class="block text-xs font-medium text-gray-700 mb-1">เปิดใช้</label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" id="isActive" class="sr-only peer" checked>
                        <div class="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
                    </label>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex gap-3">
                <button type="button" onclick="closeModal()"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg font-medium hover:bg-gray-50">
                    ยกเลิก
                </button>
                <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                    💾 บันทึก
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('promoModal');

    function openModal() {
        // Reset form
        document.getElementById('formAction').value = 'create';
        document.getElementById('promoId').value = '';
        document.getElementById('modalTitle').textContent = '➕ เพิ่มข่าวสาร/โปรโมชั่น';
        document.getElementById('promoTitle').value = '';
        document.getElementById('promoContent').value = '';
        document.getElementById('typeNews').checked = true;
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        document.getElementById('isActive').checked = true;
        document.getElementById('existingImage').value = '';
        document.getElementById('imagePreview').classList.add('hidden');

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function editPromo(promo) {
        document.getElementById('formAction').value = 'update';
        document.getElementById('promoId').value = promo.promo_id;
        document.getElementById('modalTitle').textContent = '✏️ แก้ไขข่าวสาร/โปรโมชั่น';
        document.getElementById('promoTitle').value = promo.title;
        document.getElementById('promoContent').value = promo.content || '';

        if (promo.promo_type === 'promotion') {
            document.getElementById('typePromo').checked = true;
        } else {
            document.getElementById('typeNews').checked = true;
        }

        document.getElementById('startDate').value = promo.start_date || '';
        document.getElementById('endDate').value = promo.end_date || '';
        document.getElementById('isActive').checked = promo.is_active == 1;
        document.getElementById('existingImage').value = promo.image_url || '';

        if (promo.image_url) {
            document.getElementById('previewImg').src = promo.image_url;
            document.getElementById('imagePreview').classList.remove('hidden');
        } else {
            document.getElementById('imagePreview').classList.add('hidden');
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function clearImage() {
        document.getElementById('existingImage').value = '';
        document.getElementById('imageInput').value = '';
        document.getElementById('imagePreview').classList.add('hidden');
    }

    // Preview uploaded image
    document.getElementById('imageInput').addEventListener('change', function (e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('previewImg').src = e.target.result;
                document.getElementById('imagePreview').classList.remove('hidden');
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    // Close modal on escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // Close modal on backdrop click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    // Auto open modal if edit param exists
    <?php if ($editItem): ?>
        editPromo(<?php echo json_encode($editItem); ?>);
    <?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>