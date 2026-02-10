<?php
/**
 * Create New Job Order - เปิดใบงานใหม่
 */

require_once '../../config/database.php';
require_once '../../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Get job type from URL (service or repair)
$jobType = $_GET['type'] ?? 'service';
if (!in_array($jobType, ['service', 'repair'])) {
    $jobType = 'service';
}

// Handle Create Job
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = $_POST['member_id'] ?? 0;
    $vehicleId = $_POST['vehicle_id'] ?? 0;
    $jobCategory = $_POST['job_category'] ?? 'service';
    $jobPriority = $_POST['job_type'] ?? 'normal';
    $appointmentDate = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
    $assignedTo = $_POST['assigned_to'] ?: null;
    $symptom = trim($_POST['symptom'] ?? '');
    $serviceId = $_POST['service_id'] ?? null;

    if ($memberId && $vehicleId) {
        $initialStatus = 'RECEIVED';
        $openedBy = $_SESSION['user_id'];

        $stmt = $pdo->prepare("INSERT INTO job_orders (member_id, vehicle_id, opened_by, assigned_to, job_category, job_type, appointment_date, status, symptom) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$memberId, $vehicleId, $openedBy, $assignedTo, $jobCategory, $jobPriority, $appointmentDate, $initialStatus, $symptom ?: null]);

        $jobId = $pdo->lastInsertId();

        // Log timeline with appropriate remark
        $remark = $jobCategory === 'service' ? 'เปิดงานบริการใหม่' : 'เปิดงานซ่อมใหม่';
        if ($symptom) {
            $remark .= ' - ' . $symptom;
        }
        $pdo->prepare("INSERT INTO job_timeline (job_id, new_status, changed_by, remark) VALUES (?, ?, ?, ?)")
            ->execute([$jobId, $initialStatus, $openedBy, $remark]);

        // If service job and service_id selected, add to job_services
        if ($jobCategory === 'service' && $serviceId) {
            $serviceStmt = $pdo->prepare("SELECT standard_price FROM service_items WHERE service_id = ?");
            $serviceStmt->execute([$serviceId]);
            $servicePrice = $serviceStmt->fetchColumn() ?: 0;

            $pdo->prepare("INSERT INTO job_services (job_id, service_id, price) VALUES (?, ?, ?)")
                ->execute([$jobId, $serviceId, $servicePrice]);
        }

        header('Location: view.php?id=' . $jobId);
        exit;
    }
}

$pageTitle = $jobType === 'service' ? 'เปิดงานบริการ' : 'เปิดงานซ่อม';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get members with vehicles
$members = $pdo->query("SELECT m.*, 
    (SELECT COUNT(*) FROM vehicles v WHERE v.member_id = m.member_id) as vehicle_count
    FROM members m WHERE m.status = 'active' ORDER BY m.first_name")->fetchAll();

// Get technicians
$technicians = $pdo->query("SELECT * FROM users WHERE role = 'technician' AND status = 'active'")->fetchAll();

// Get service items for service jobs
$serviceItems = $pdo->query("SELECT * FROM service_items ORDER BY service_name")->fetchAll();

// Get all vehicles for JavaScript
$vehicles = $pdo->query("SELECT v.*, m.member_id, m.first_name, m.last_name FROM vehicles v JOIN members m ON v.member_id = m.member_id")->fetchAll();
?>

<div class="max-w-2xl mx-auto">
    <!-- Type Switcher -->
    <div class="flex gap-2 mb-6">
        <a href="?type=service"
            class="flex-1 py-3 px-4 rounded-xl font-medium text-center transition-all <?php echo $jobType === 'service' ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-gray-600 hover:bg-blue-50 border'; ?>">
            🔄 งานบริการ
        </a>
        <a href="?type=repair"
            class="flex-1 py-3 px-4 rounded-xl font-medium text-center transition-all <?php echo $jobType === 'repair' ? 'bg-orange-600 text-white shadow-lg' : 'bg-white text-gray-600 hover:bg-orange-50 border'; ?>">
            🔧 งานซ่อม
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div
            class="p-6 border-b <?php echo $jobType === 'service' ? 'bg-gradient-to-r from-blue-600 to-blue-700' : 'bg-gradient-to-r from-orange-600 to-orange-700'; ?>">
            <h2 class="text-xl font-semibold text-white">
                <?php echo $jobType === 'service' ? '🔄 เปิดงานบริการ' : '🔧 เปิดงานซ่อม'; ?>
            </h2>
            <p class="text-white/80 text-sm mt-1">
                <?php echo $jobType === 'service' ? 'เลือกรายการบริการจากรายการมาตรฐาน' : 'กรอกอาการเบื้องต้น ตรวจสอบและประเมินราคาภายหลัง'; ?>
            </p>
        </div>

        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="job_category" value="<?php echo $jobType; ?>">

            <!-- Member Selection -->
            <div>
                <label class="block text-sm font-medium mb-1">สมาชิก <span class="text-red-500">*</span></label>
                <input type="text" id="member_search" list="memberList"
                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                    placeholder="พิมพ์ชื่อหรือเบอร์โทร..." autocomplete="off" onchange="selectMember(this)">
                <input type="hidden" name="member_id" id="member_id" required>
                <datalist id="memberList">
                    <?php foreach ($members as $m): ?>
                        <option
                            value="<?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name'] . ' - ' . $m['phone']); ?>"
                            data-id="<?php echo $m['member_id']; ?>">
                        </option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Vehicle Selection -->
            <div>
                <label class="block text-sm font-medium mb-1">รถยนต์ <span class="text-red-500">*</span></label>
                <select name="vehicle_id" id="vehicle_id" required
                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">-- เลือกสมาชิกก่อน --</option>
                </select>
            </div>

            <?php if ($jobType === 'service'): ?>
                <!-- Service Selection (for service jobs) -->
                <div>
                    <label class="block text-sm font-medium mb-1">รายการบริการ <span class="text-red-500">*</span></label>
                    <select name="service_id" required
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">-- เลือกบริการ --</option>
                        <?php foreach ($serviceItems as $service): ?>
                            <option value="<?php echo $service['service_id']; ?>">
                                <?php echo htmlspecialchars($service['service_name']); ?>
                                - ฿<?php echo number_format($service['standard_price'], 0); ?>
                                <?php if ($service['estimated_minutes']): ?>
                                    (≈<?php echo $service['estimated_minutes']; ?> นาที)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">สามารถเพิ่มบริการเพิ่มเติมได้หลังเปิดงาน</p>
                </div>
            <?php else: ?>
                <!-- Symptom Input (for repair jobs) -->
                <div>
                    <label class="block text-sm font-medium mb-1">อาการเบื้องต้น <span class="text-red-500">*</span></label>
                    <textarea name="symptom" required rows="3"
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500"
                        placeholder="เช่น: เครื่องยนต์มีเสียงดัง, เบรคไม่อยู่, แอร์ไม่เย็น..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">ช่างจะตรวจสอบและประเมินราคาหลังจากรับรถแล้ว</p>
                </div>
            <?php endif; ?>

            <!-- Job Priority -->
            <div>
                <label class="block text-sm font-medium mb-2">ลักษณะงาน</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="job_type" value="normal" checked class="text-blue-600"
                            onchange="toggleAppointmentDate()">
                        <span>ปกติ</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="job_type" value="urgent" class="text-red-600"
                            onchange="toggleAppointmentDate()">
                        <span class="text-red-600 font-medium">🔥 ด่วน</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="job_type" value="appointment" class="text-purple-600"
                            onchange="toggleAppointmentDate()">
                        <span class="text-purple-600">📅 นัดหมาย</span>
                    </label>
                </div>
            </div>

            <!-- Appointment Date (shown when appointment selected) -->
            <div id="appointmentDateField" class="hidden">
                <label class="block text-sm font-medium mb-1">วันเวลานัดหมาย <span class="text-red-500">*</span></label>
                <input type="datetime-local" name="appointment_date" id="appointment_date"
                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>

            <!-- Technician Assignment -->
            <div>
                <label class="block text-sm font-medium mb-1">มอบหมายช่าง (ไม่บังคับ)</label>
                <select name="assigned_to"
                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">-- มอบหมายภายหลัง --</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['user_id']; ?>">
                            <?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Submit -->
            <div class="flex gap-3 pt-4">
                <button type="submit"
                    class="flex-1 <?php echo $jobType === 'service' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-orange-600 hover:bg-orange-700'; ?> text-white py-3 rounded-lg font-medium text-lg">
                    เปิด<?php echo $jobType === 'service' ? 'งานบริการ' : 'งานซ่อม'; ?>
                </button>
                <a href="index.php" class="px-8 py-3 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium text-center">
                    ยกเลิก
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    const membersData = <?php echo json_encode(array_map(function ($m) {
        return ['id' => $m['member_id'], 'label' => $m['first_name'] . ' ' . $m['last_name'] . ' - ' . $m['phone']];
    }, $members)); ?>;

    const vehiclesData = <?php echo json_encode($vehicles); ?>;

    function selectMember(input) {
        const value = input.value;
        const member = membersData.find(m => m.label === value);
        if (member) {
            document.getElementById('member_id').value = member.id;
            updateVehicleOptions(member.id);
        }
    }

    function updateVehicleOptions(memberId) {
        const select = document.getElementById('vehicle_id');
        const memberVehicles = vehiclesData.filter(v => v.member_id == memberId);

        select.innerHTML = '<option value="">-- เลือกรถ --</option>';
        memberVehicles.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.vehicle_id;
            opt.textContent = v.license_plate + ' - ' + (v.brand || '') + ' ' + (v.model || '');
            select.appendChild(opt);
        });

        if (memberVehicles.length === 1) {
            select.value = memberVehicles[0].vehicle_id;
        }
    }

    // Toggle appointment date field
    function toggleAppointmentDate() {
        const jobType = document.querySelector('input[name="job_type"]:checked').value;
        const dateField = document.getElementById('appointmentDateField');
        const dateInput = document.getElementById('appointment_date');

        if (jobType === 'appointment') {
            dateField.classList.remove('hidden');
            dateInput.required = true;
        } else {
            dateField.classList.add('hidden');
            dateInput.required = false;
            dateInput.value = '';
        }

        // Style job_type radio buttons
        document.querySelectorAll('input[name="job_type"]').forEach(r => {
            r.closest('label').classList.remove('border-red-500', 'border-purple-500', 'border-blue-500', 'bg-red-50', 'bg-purple-50', 'bg-blue-50');
        });
        const selected = document.querySelector('input[name="job_type"]:checked');
        if (selected.value === 'urgent') {
            selected.closest('label').classList.add('border-red-500', 'bg-red-50');
        } else if (selected.value === 'appointment') {
            selected.closest('label').classList.add('border-purple-500', 'bg-purple-50');
        } else {
            selected.closest('label').classList.add('border-blue-500', 'bg-blue-50');
        }
    }

    // Initialize
    toggleAppointmentDate();
</script>

<?php require_once '../includes/footer.php'; ?>