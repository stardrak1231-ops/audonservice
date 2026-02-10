<?php
/**
 * Member Receipt - ใบเสร็จ
 */

$jobId = $_GET['job_id'] ?? 0;

if (!$jobId) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/session.php';

requireMemberLogin();
$currentMember = getCurrentMember();
$pdo = getDBConnection();

// Get job and invoice
$job = $pdo->prepare("SELECT jo.*, 
    v.license_plate, v.brand, v.model,
    i.invoice_id, i.total_amount, i.discount, i.net_amount, i.payment_status, i.issued_at
    FROM job_orders jo
    JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN invoices i ON jo.job_id = i.job_id
    WHERE jo.job_id = ? AND jo.member_id = ?");
$job->execute([$jobId, $currentMember['member_id']]);
$job = $job->fetch();

if (!$job || !$job['invoice_id'] || $job['payment_status'] !== 'paid') {
    header('Location: history.php');
    exit;
}

// Get services
$services = $pdo->prepare("SELECT js.*, s.service_name, s.standard_price FROM job_services js JOIN service_items s ON js.service_id = s.service_id WHERE js.job_id = ?");
$services->execute([$jobId]);
$services = $services->fetchAll();

// Get parts
$parts = $pdo->prepare("SELECT jp.*, p.part_name, p.sell_price FROM job_parts jp JOIN spare_parts p ON jp.part_id = p.part_id WHERE jp.job_id = ?");
$parts->execute([$jobId]);
$parts = $parts->fetchAll();

$pageTitle = 'ใบเสร็จ';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเสร็จ | อู่อุดร Service</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>

<body class="bg-gray-100 p-4">

    <div class="max-w-2xl mx-auto">
        <!-- Actions -->
        <div class="flex justify-between mb-4 no-print">
            <a href="job.php?id=<?php echo $jobId; ?>" class="text-blue-600 hover:underline">← กลับ</a>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                🖨️ พิมพ์
            </button>
        </div>

        <!-- Receipt -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <!-- Header -->
            <div class="text-center mb-6 border-b pb-6">
                <h1 class="text-2xl font-bold">อู่อุดร Service</h1>
                <p class="text-gray-500">ใบเสร็จรับเงิน</p>
                <div class="mt-2 font-mono text-lg font-bold text-blue-600">
                    INV-
                    <?php echo str_pad($job['invoice_id'], 5, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="text-sm text-gray-400">
                    <?php echo date('d/m/Y H:i', strtotime($job['issued_at'])); ?>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                <div>
                    <div class="text-gray-500">ลูกค้า</div>
                    <div class="font-medium">
                        <?php echo htmlspecialchars($currentMember['first_name'] . ' ' . $currentMember['last_name']); ?>
                    </div>
                    <div class="text-gray-600">
                        <?php echo htmlspecialchars($currentMember['phone']); ?>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-gray-500">รถ</div>
                    <div class="font-mono font-bold text-blue-600">
                        <?php echo htmlspecialchars($job['license_plate']); ?>
                    </div>
                    <div class="text-gray-600">
                        <?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="border rounded-lg overflow-hidden mb-6">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">รายการ</th>
                            <th class="px-4 py-2 text-right">จำนวน</th>
                            <th class="px-4 py-2 text-right">ราคา</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($services as $s): ?>
                            <tr>
                                <td class="px-4 py-2">
                                    <?php echo htmlspecialchars($s['service_name']); ?>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <?php echo $s['quantity']; ?>
                                </td>
                                <td class="px-4 py-2 text-right">฿
                                    <?php echo number_format($s['standard_price'] * $s['quantity'], 0); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($parts as $p): ?>
                            <tr>
                                <td class="px-4 py-2">
                                    <?php echo htmlspecialchars($p['part_name']); ?>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <?php echo $p['quantity']; ?>
                                </td>
                                <td class="px-4 py-2 text-right">฿
                                    <?php echo number_format($p['sell_price'] * $p['quantity'], 0); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="border-t pt-4">
                <div class="flex justify-between mb-1">
                    <span>รวม</span>
                    <span>฿
                        <?php echo number_format($job['total_amount'], 0); ?>
                    </span>
                </div>
                <?php if ($job['discount'] > 0): ?>
                    <div class="flex justify-between text-red-600 mb-1">
                        <span>ส่วนลด</span>
                        <span>-฿
                            <?php echo number_format($job['discount'], 0); ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between text-xl font-bold text-blue-600 border-t pt-2 mt-2">
                    <span>ยอดชำระ</span>
                    <span>฿
                        <?php echo number_format($job['net_amount'], 0); ?>
                    </span>
                </div>
            </div>

            <!-- Paid Status -->
            <div class="mt-6 pt-4 border-t text-center">
                <div class="inline-flex items-center gap-2 text-green-600 font-bold text-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    ชำระเงินแล้ว
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 pt-4 border-t text-center text-gray-400 text-sm">
                <p>ขอบคุณที่ใช้บริการ</p>
                <p>อู่อุดร Service</p>
            </div>
        </div>
    </div>

</body>

</html>