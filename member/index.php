<?php
/**
 * Member Dashboard - งานปัจจุบัน
 */

$pageTitle = 'งานปัจจุบัน';
require_once 'includes/header.php';

// Get current/active jobs for member
$activeJobs = $pdo->prepare("
    SELECT jo.*, 
        v.license_plate, v.brand, v.model,
        js.status_name,
        u.first_name as tech_name
    FROM job_orders jo
    JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN job_status js ON jo.status = js.status_code AND jo.job_category = js.job_category
    LEFT JOIN users u ON jo.assigned_to = u.user_id
    WHERE jo.member_id = ? AND jo.status NOT IN ('DELIVERED', 'CANCELLED')
    ORDER BY jo.opened_date DESC
");
$activeJobs->execute([$currentMember['member_id']]);
$activeJobs = $activeJobs->fetchAll();

// Status colors
$statusColors = [
    'RECEIVED' => 'bg-gray-100 text-gray-700',
    'INSPECTING' => 'bg-yellow-100 text-yellow-700',
    'WAIT_PART' => 'bg-orange-100 text-orange-700',
    'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
    'COMPLETED' => 'bg-green-100 text-green-700',
    'WAIT_PAYMENT' => 'bg-purple-100 text-purple-700',
];
?>

<!-- Welcome Section -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">สวัสดีคุณ
        <?php echo htmlspecialchars($currentMember['first_name']); ?>
    </h1>
    <p class="text-gray-500">ติดตามงานซ่อมและบริการของคุณได้ที่นี่</p>
</div>

<!-- Active Jobs -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
    <div class="p-4 border-b bg-gradient-to-r from-blue-500 to-blue-600 text-white">
        <h2 class="font-semibold text-lg flex items-center gap-2">
            <span class="text-2xl">🔧</span>
            งานที่กำลังดำเนินการ
        </h2>
    </div>

    <?php if (empty($activeJobs)): ?>
        <div class="p-12 text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <div class="text-gray-500">ไม่มีงานที่กำลังดำเนินการ</div>
            <p class="text-gray-400 text-sm mt-1">รถของคุณพร้อมใช้งาน!</p>
        </div>
    <?php else: ?>
        <div class="divide-y">
            <?php foreach ($activeJobs as $job): ?>
                <a href="job.php?id=<?php echo $job['job_id']; ?>" class="block p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-2xl">
                                    <?php echo $job['job_category'] === 'repair' ? '🔧' : '🛠️'; ?>
                                </span>
                                <div>
                                    <div class="font-mono font-bold text-blue-600">
                                        <?php echo htmlspecialchars($job['license_plate']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-sm text-gray-500">
                                เปิดงานเมื่อ
                                <?php echo date('d/m/Y', strtotime($job['opened_date'])); ?>
                                <?php if ($job['tech_name']): ?>
                                    · ช่าง:
                                    <?php echo htmlspecialchars($job['tech_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <span
                                class="inline-block px-3 py-1 rounded-full text-sm font-medium <?php echo $statusColors[$job['status']] ?? 'bg-gray-100 text-gray-700'; ?>">
                                <?php echo htmlspecialchars($job['status_name']); ?>
                            </span>
                            <div class="text-xs text-gray-400 mt-1">ดูรายละเอียด →</div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- My Vehicles -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b">
        <h2 class="font-semibold text-lg">🚗 รถของฉัน</h2>
    </div>

    <?php if (empty($memberVehicles)): ?>
        <div class="p-8 text-center text-gray-400">
            ยังไม่มีข้อมูลรถ
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
            <?php foreach ($memberVehicles as $v): ?>
                <div class="border rounded-xl p-4 flex items-center gap-4">
                    <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center text-2xl">
                        🚙
                    </div>
                    <div>
                        <div class="font-mono font-bold text-lg text-blue-600">
                            <?php echo htmlspecialchars($v['license_plate']); ?>
                        </div>
                        <div class="text-gray-500">
                            <?php echo htmlspecialchars($v['brand'] . ' ' . $v['model']); ?>
                        </div>
                        <?php if ($v['year']): ?>
                            <div class="text-xs text-gray-400">ปี
                                <?php echo $v['year']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>