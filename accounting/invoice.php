<?php
/**
 * Invoice Management - ออกใบเสร็จและรับชำระ
 */

require_once '../config/database.php';
require_once '../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();
$currentUser = getCurrentUser();
$jobId = $_GET['job_id'] ?? 0;

if (!$jobId) {
    header('Location: index.php');
    exit;
}

// Get invoice prefix from settings
$prefixStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'invoice_prefix'");
$invoicePrefix = $prefixStmt ? ($prefixStmt->fetchColumn() ?: 'INV') : 'INV';

// Get bank settings
$bankSettings = [];
$bankStmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('bank_name', 'bank_account', 'bank_account_name', 'promptpay')");
if ($bankStmt) {
    foreach ($bankStmt->fetchAll() as $row) {
        $bankSettings[$row['setting_key']] = $row['setting_value'];
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_invoice') {
        $netAmount = floatval($_POST['net_amount'] ?? 0);
        $discountVip = floatval($_POST['discount_vip'] ?? 0);
        $discountPromo = floatval($_POST['discount_promo'] ?? 0);
        $promoReason = trim($_POST['promo_reason'] ?? '');

        // Calculate total discount
        $totalDiscount = $discountVip + $discountPromo;

        // Generate invoice number
        $year = date('Y');
        $countStmt = $pdo->query("SELECT COUNT(*) FROM invoices WHERE YEAR(issued_at) = $year");
        $count = $countStmt->fetchColumn() + 1;
        $invoiceNumber = $invoicePrefix . '-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Get member_id from job
        $memberStmt = $pdo->prepare("SELECT member_id FROM job_orders WHERE job_id = ?");
        $memberStmt->execute([$jobId]);
        $memberId = $memberStmt->fetchColumn();

        // Create invoice
        $stmt = $pdo->prepare("INSERT INTO invoices (job_id, member_id, total_amount, discount, discount_vip, discount_promo, promo_reason, net_amount, payment_status, issued_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?)");
        $subtotal = $netAmount + $totalDiscount;
        $stmt->execute([$jobId, $memberId, $subtotal, $totalDiscount, $discountVip, $discountPromo, $promoReason, $netAmount, $currentUser['user_id']]);

        header('Location: invoice.php?job_id=' . $jobId . '&success=invoice_created');
        exit;
    }

    if ($action === 'record_payment') {
        $invoiceId = $_POST['invoice_id'] ?? 0;
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $paymentAmount = floatval($_POST['payment_amount'] ?? 0);
        $paymentRef = trim($_POST['payment_ref'] ?? '');

        if ($invoiceId && $paymentAmount > 0) {
            // Record payment
            $stmt = $pdo->prepare("INSERT INTO payments (invoice_id, paid_amount, payment_method) VALUES (?, ?, ?)");
            $stmt->execute([$invoiceId, $paymentAmount, $paymentMethod]);

            // Update invoice status
            $pdo->prepare("UPDATE invoices SET payment_status = 'paid' WHERE invoice_id = ?")->execute([$invoiceId]);

            // Update job status to DELIVERED
            $pdo->prepare("UPDATE job_orders SET status = 'DELIVERED' WHERE job_id = ?")->execute([$jobId]);

            header('Location: invoice.php?job_id=' . $jobId . '&success=payment_recorded');
            exit;
        }
    }
}

// Get job details
$job = $pdo->prepare("SELECT jo.*, 
    v.license_plate, v.brand, v.model,
    m.first_name as member_first_name, m.last_name as member_last_name, m.phone as member_phone, m.member_code,
    (SELECT COALESCE(SUM(net_amount), 0) FROM invoices WHERE member_id = m.member_id AND payment_status = 'paid') as member_total_spent
    FROM job_orders jo
    LEFT JOIN vehicles v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN members m ON jo.member_id = m.member_id
    WHERE jo.job_id = ?");
$job->execute([$jobId]);
$job = $job->fetch();

if (!$job) {
    header('Location: index.php');
    exit;
}

// Get VIP threshold
$vipStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vip_threshold'");
$vipThreshold = $vipStmt ? ($vipStmt->fetchColumn() ?: 50000) : 50000;
$isVip = $job['member_total_spent'] >= $vipThreshold;

// Calculate VIP Discount (5% if VIP)
$vipDiscountRate = $isVip ? 0.05 : 0;

// Get services (Actual Price)
$services = $pdo->prepare("SELECT js.*, s.service_name, js.price FROM job_services js JOIN service_items s ON js.service_id = s.service_id WHERE js.job_id = ?");
$services->execute([$jobId]);
$services = $services->fetchAll();

// Get parts
$parts = $pdo->prepare("SELECT jp.*, p.part_name, p.sell_price FROM job_parts jp JOIN spare_parts p ON jp.part_id = p.part_id WHERE jp.job_id = ?");
$parts->execute([$jobId]);
$parts = $parts->fetchAll();

// Calculate totals
$serviceTotal = 0;
foreach ($services as $s) {
    $serviceTotal += $s['price'] * $s['quantity'];
}
$partsTotal = 0;
foreach ($parts as $p) {
    $partsTotal += $p['sell_price'] * $p['quantity'];
}
$subtotal = $serviceTotal + $partsTotal;

// Calculate VIP Discount Amount
$preCalVipDiscount = $subtotal * $vipDiscountRate;

// Get existing invoice
$invoice = $pdo->prepare("SELECT * FROM invoices WHERE job_id = ?");
$invoice->execute([$jobId]);
$invoice = $invoice->fetch();

$pageTitle = 'ใบเสร็จ #' . str_pad($jobId, 5, '0', STR_PAD_LEFT);
require_once 'includes/header.php';

$success = $_GET['success'] ?? '';
?>

<?php if ($success): ?>
    <div
        class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center gap-2 no-print">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?php echo [
            'invoice_created' => 'ออกใบเสร็จสำเร็จ',
            'payment_recorded' => 'บันทึกการชำระเงินสำเร็จ'
        ][$success] ?? 'ดำเนินการสำเร็จ'; ?>
    </div>
<?php endif; ?>

<!-- Back Link -->
<a href="index.php"
    class="text-emerald-600 hover:text-emerald-700 text-sm mb-4 inline-flex items-center gap-1 no-print">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
    </svg>
    กลับรายการ
</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Invoice Preview -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-md p-6" id="invoicePreview">
            <!-- Header -->
            <div class="text-center mb-6 border-b pb-4">
                <h1 class="text-2xl font-bold">อู่อุดร Service</h1>
                <p class="text-gray-500 text-sm">ใบเสร็จรับเงิน / ใบกำกับภาษี</p>
                <?php if ($invoice): ?>
                    <div class="mt-2 text-lg font-mono font-bold text-emerald-600">
                        INV-<?php echo str_pad($invoice['invoice_id'], 5, '0', STR_PAD_LEFT); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Customer Info -->
            <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                <div>
                    <div class="text-gray-500">ลูกค้า</div>
                    <div class="font-medium">
                        <?php echo htmlspecialchars($job['member_first_name'] . ' ' . $job['member_last_name']); ?>
                    </div>
                    <div class="text-gray-600">
                        <?php echo htmlspecialchars($job['member_phone']); ?>
                    </div>
                    <?php if ($isVip): ?>
                        <span class="inline-block mt-1 px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded-full">⭐
                            VIP</span>
                    <?php endif; ?>
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

            <!-- Services -->
            <?php if (!empty($services)): ?>
                <div class="mb-4">
                    <div class="text-sm font-medium text-gray-500 mb-2">บริการ</div>
                    <table class="w-full text-sm">
                        <?php foreach ($services as $s): ?>
                            <tr>
                                <td class="py-1">
                                    <?php echo htmlspecialchars($s['service_name']); ?>
                                </td>
                                <td class="py-1 text-right text-gray-500">x
                                    <?php echo $s['quantity']; ?>
                                </td>
                                <td class="py-1 text-right w-28">฿
                                    <?php echo number_format($s['price'] * $s['quantity'], 0); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Parts -->
            <?php if (!empty($parts)): ?>
                <div class="mb-4">
                    <div class="text-sm font-medium text-gray-500 mb-2">อะไหล่</div>
                    <table class="w-full text-sm">
                        <?php foreach ($parts as $p): ?>
                            <tr>
                                <td class="py-1">
                                    <?php echo htmlspecialchars($p['part_name']); ?>
                                </td>
                                <td class="py-1 text-right text-gray-500">x
                                    <?php echo $p['quantity']; ?>
                                </td>
                                <td class="py-1 text-right w-28">฿
                                    <?php echo number_format($p['sell_price'] * $p['quantity'], 0); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Totals -->
            <div class="border-t pt-4 mt-4">
                <div class="flex justify-between text-sm mb-1">
                    <span>รวมบริการ</span>
                    <span>฿
                        <?php echo number_format($serviceTotal, 0); ?>
                    </span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span>รวมอะไหล่</span>
                    <span>฿
                        <?php echo number_format($partsTotal, 0); ?>
                    </span>
                </div>
                <div class="flex justify-between font-medium border-t pt-2 mt-2">
                    <span>ยอดรวม</span>
                    <span>฿
                        <?php echo number_format($subtotal, 0); ?>
                    </span>
                </div>

                <!-- Display Discounts -->
                <?php if ($invoice): ?>
                    <!-- Existing Invoice View -->
                    <?php if ($invoice['discount_vip'] > 0): ?>
                        <div class="flex justify-between text-sm text-yellow-600">
                            <span>ส่วนลด VIP (5%)</span>
                            <span>-฿<?php echo number_format($invoice['discount_vip'], 0); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($invoice['discount_promo'] > 0): ?>
                        <div class="flex justify-between text-sm text-red-600">
                            <span>ส่วนลดโปรโมชั่น (<?php echo htmlspecialchars($invoice['promo_reason']); ?>)</span>
                            <span>-฿<?php echo number_format($invoice['discount_promo'], 0); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="flex justify-between text-xl font-bold text-emerald-600 border-t pt-2 mt-2">
                        <span>ยอดชำระ</span>
                        <span>฿<?php echo number_format($invoice['net_amount'], 0); ?></span>
                    </div>

                <?php else: ?>
                    <!-- Creating Invoice View (Dynamic) -->
                    <div id="previewDiscounts">
                        <!-- VIP Discount Preview -->
                        <?php if ($isVip): ?>
                            <div class="flex justify-between text-sm text-yellow-600">
                                <span>ส่วนลด VIP (5%)</span>
                                <span>-฿<span
                                        id="previewVipDiscount"><?php echo number_format($preCalVipDiscount, 0); ?></span></span>
                            </div>
                        <?php endif; ?>

                        <!-- Promo Discount Preview -->
                        <div id="previewPromoRow" class="flex justify-between text-sm text-red-600 hidden">
                            <span>ส่วนลดโปรโมชั่น <span id="previewPromoReason" class="text-xs text-gray-500"></span></span>
                            <span>-฿<span id="previewPromoDiscount">0</span></span>
                        </div>
                    </div>

                    <div class="flex justify-between text-xl font-bold text-emerald-600 pt-2" id="netAmountDisplay">
                        <span>ยอดชำระ</span>
                        <span>฿<?php echo number_format($subtotal - $preCalVipDiscount, 0); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Payment Status -->
            <?php if ($invoice): ?>
                <div class="mt-6 pt-4 border-t text-center">
                    <?php if ($invoice['payment_status'] === 'paid'): ?>
                        <div class="text-green-600 font-bold text-xl">✓ ชำระแล้ว</div>
                    <?php else: ?>
                        <div class="text-orange-600 font-medium">รอชำระเงิน</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Actions Panel -->
    <div class="space-y-4 no-print">
        <?php if (!$invoice): ?>
            <!-- Create Invoice Form -->
            <div class="bg-white rounded-xl shadow-md p-5">
                <h3 class="font-semibold mb-4">🧾 ออกใบเสร็จ</h3>
                <form method="POST" onsubmit="return validateForm()">
                    <input type="hidden" name="action" value="create_invoice">
                    <input type="hidden" name="net_amount" id="netAmountInput"
                        value="<?php echo $subtotal - $preCalVipDiscount; ?>">

                    <!-- VIP Discount Section -->
                    <div class="mb-4 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-sm font-semibold text-yellow-800">⭐ ส่วนลด VIP (5%)</label>
                            <?php if ($isVip): ?>
                                <span class="bg-yellow-200 text-yellow-800 text-xs px-2 py-0.5 rounded-full">Active</span>
                            <?php else: ?>
                                <span class="bg-gray-200 text-gray-500 text-xs px-2 py-0.5 rounded-full">Inactive</span>
                            <?php endif; ?>
                        </div>
                        <input type="number" name="discount_vip" id="vipDiscountInput"
                            value="<?php echo $isVip ? $preCalVipDiscount : 0; ?>"
                            class="w-full px-3 py-2 bg-white border border-yellow-300 rounded text-gray-600 font-medium"
                            readonly>
                    </div>

                    <!-- Promo Discount Section -->
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg border">
                        <label class="block text-sm font-medium mb-2 text-gray-700">🏷️ ส่วนลดโปรโมชั่น (ระบุเอง)</label>

                        <div class="flex gap-2 mb-2">
                            <div class="flex-1">
                                <input type="number" name="discount_promo" id="promoDiscountInput" value="" min="0"
                                    max="<?php echo $subtotal; ?>" placeholder="0.00"
                                    class="w-full px-3 py-2 border rounded-lg text-sm" oninput="updateNetAmount()">
                            </div>
                        </div>

                        <div id="promoReasonObj" class="transition-all duration-200">
                            <input type="text" name="promo_reason" id="promoReasonInput"
                                class="w-full px-3 py-2 border rounded-lg text-sm"
                                placeholder="ระบุเหตุผล (เช่น โปรปีใหม่, ชดเชยงานล่าช้า)" oninput="updatePreviewReason()">
                            <p class="text-xs text-red-500 mt-1 hidden" id="promoReasonError">*
                                จำเป็นต้องระบุเหตุผลหากมีส่วนลด</p>
                        </div>
                    </div>

                    <div class="p-3 bg-emerald-50 rounded-lg mb-4 flex justify-between items-center">
                        <span class="text-emerald-800 text-sm">ยอดสุทธิหลังหักส่วนลด</span>
                        <span class="font-bold text-emerald-700 text-lg" id="finalNetDisplay">
                            ฿<?php echo number_format($subtotal - $preCalVipDiscount, 0); ?>
                        </span>
                    </div>

                    <button type="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-lg font-medium shadow-sm">
                        ออกใบเสร็จ
                    </button>
                </form>
            </div>

        <?php elseif ($invoice['payment_status'] !== 'paid'): ?>
            <!-- Record Payment Form -->
            <div class="bg-white rounded-xl shadow-md p-5">
                <h3 class="font-semibold mb-4">💳 รับชำระเงิน</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="invoice_id" value="<?php echo $invoice['invoice_id']; ?>">
                    <input type="hidden" name="payment_amount" value="<?php echo $invoice['net_amount']; ?>">

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">ยอดรับชำระ</label>
                        <div class="text-2xl font-bold text-emerald-600">฿
                            <?php echo number_format($invoice['net_amount'], 0); ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">ช่องทางชำระ</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                class="flex items-center justify-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-500">
                                <input type="radio" name="payment_method" value="cash" checked class="hidden"
                                    onchange="toggleBankInfo()">
                                <span>💵 เงินสด</span>
                            </label>
                            <label
                                class="flex items-center justify-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-500">
                                <input type="radio" name="payment_method" value="transfer" class="hidden"
                                    onchange="toggleBankInfo()">
                                <span>📱 โอนเงิน</span>
                            </label>
                        </div>
                    </div>

                    <!-- Bank Info Panel (shows when transfer selected) -->
                    <div id="bankInfoPanel" class="hidden mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="text-center">
                            <div class="text-sm font-medium text-blue-700 mb-3">🏦 โอนเงินมาที่</div>
                            <?php if (!empty($bankSettings['promptpay'])): ?>
                                <img src="https://promptpay.io/<?php echo htmlspecialchars($bankSettings['promptpay']); ?>/<?php echo $invoice['net_amount']; ?>.png"
                                    alt="QR PromptPay" class="w-40 h-40 mx-auto mb-3 rounded-lg bg-white p-2">
                            <?php endif; ?>
                            <div class="text-sm space-y-1">
                                <div class="font-medium"><?php echo htmlspecialchars($bankSettings['bank_name'] ?? ''); ?>
                                </div>
                                <div class="font-mono text-lg font-bold">
                                    <?php echo htmlspecialchars($bankSettings['bank_account'] ?? ''); ?>
                                </div>
                                <div><?php echo htmlspecialchars($bankSettings['bank_account_name'] ?? ''); ?></div>
                                <?php if (!empty($bankSettings['promptpay'])): ?>
                                    <div class="text-blue-600">PromptPay:
                                        <?php echo htmlspecialchars($bankSettings['promptpay']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Reference Number (only for transfer) -->
                        <div class="mt-4 pt-3 border-t border-blue-200">
                            <label class="block text-sm font-medium mb-1 text-blue-800">เลขอ้างอิง/สลิป</label>
                            <input type="text" name="payment_ref" class="w-full px-4 py-2 border rounded-lg bg-white"
                                placeholder="เลขอ้างอิงหรือหมายเลขสลิป">
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-medium text-lg">
                        ✓ บันทึกการชำระ
                    </button>
                </form>
            </div>

        <?php else: ?>
            <!-- Already Paid -->
            <div class="bg-white rounded-xl shadow-md p-5">
                <div class="text-center text-green-600">
                    <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="font-bold text-xl">ชำระเงินแล้ว</div>
                </div>
                <button onclick="window.print()"
                    class="w-full mt-4 bg-gray-600 hover:bg-gray-700 text-white py-2.5 rounded-lg font-medium">
                    🖨️ พิมพ์ใบเสร็จ
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function updateNetAmount() {
        const subtotal = <?php echo $subtotal; ?>;
        const vipDiscount = parseFloat(document.getElementById('vipDiscountInput').value) || 0;
        const promoDiscount = parseFloat(document.getElementById('promoDiscountInput').value) || 0;

        const netAmount = subtotal - vipDiscount - promoDiscount;

        // Update Hidden input
        document.getElementById('netAmountInput').value = netAmount; // Removed .toFixed(2) to keep precision if needed, PHP will handle float

        // Update Displays
        document.getElementById('netAmountDisplay').innerHTML = '<span>ยอดชำระ</span><span>฿' + netAmount.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + '</span>';
        document.getElementById('finalNetDisplay').innerText = '฿' + netAmount.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

        // Update Promo Preview
        const previewRow = document.getElementById('previewPromoRow');
        const previewAmt = document.getElementById('previewPromoDiscount');
        if (promoDiscount > 0) {
            previewRow.classList.remove('hidden');
            previewAmt.innerText = promoDiscount.toLocaleString();
        } else {
            previewRow.classList.add('hidden');
        }
    }

    function updatePreviewReason() {
        const reason = document.getElementById('promoReasonInput').value;
        document.getElementById('previewPromoReason').innerText = reason ? '(' + reason + ')' : '';
    }

    function validateForm() {
        const promoDiscount = parseFloat(document.getElementById('promoDiscountInput').value) || 0;
        const reason = document.getElementById('promoReasonInput').value.trim();
        const errorMsg = document.getElementById('promoReasonError');
        const reasonInput = document.getElementById('promoReasonInput');

        if (promoDiscount > 0 && reason === '') {
            errorMsg.classList.remove('hidden');
            reasonInput.classList.add('border-red-500');
            reasonInput.focus();
            return false;
        } else {
            errorMsg.classList.add('hidden');
            reasonInput.classList.remove('border-red-500');
            return true;
        }
    }

    function toggleBankInfo() {
        const method = document.querySelector('input[name="payment_method"]:checked').value;
        const panel = document.getElementById('bankInfoPanel');
        if (panel) {
            if (method === 'transfer') {
                panel.classList.remove('hidden');
            } else {
                panel.classList.add('hidden');
            }
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>