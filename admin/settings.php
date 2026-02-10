<?php
/**
 * System Settings - ตั้งค่าระบบ
 */

require_once '../config/database.php';
require_once '../config/session.php';

requireStaffLogin();

$pdo = getDBConnection();

// Create settings table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Default settings
$defaults = [
    'vip_threshold' => '50000',
    'bank_name' => 'ธนาคารกสิกรไทย',
    'bank_account' => '',
    'bank_account_name' => '',
    'promptpay' => '',
];

// Insert defaults if not exist
foreach ($defaults as $key => $value) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['vip_threshold', 'bank_name', 'bank_account', 'bank_account_name', 'promptpay'];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([trim($_POST[$field]), $field]);
        }
    }

    header('Location: settings.php?success=1');
    exit;
}

// Get current settings
$settings = [];
$result = $pdo->query("SELECT setting_key, setting_value FROM settings");
foreach ($result as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = 'ตั้งค่าระบบ';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$success = $_GET['success'] ?? '';
?>

<?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        บันทึกการตั้งค่าเรียบร้อย
    </div>
<?php endif; ?>

<form method="POST">
    <!-- Header with Save Button -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold">⚙️ ตั้งค่าระบบ</h2>
        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-medium flex items-center gap-2 shadow-lg shadow-blue-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            บันทึก
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- VIP Settings -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gradient-to-r from-yellow-50 to-orange-50">
                <h3 class="font-semibold text-lg flex items-center gap-2">
                    ⭐ ระบบ VIP
                </h3>
                <p class="text-sm text-gray-500 mt-1">กำหนดเงื่อนไขสมาชิก VIP</p>
            </div>
            <div class="p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">ยอดสะสมขั้นต่ำ (บาท)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">฿</span>
                    <input type="number" name="vip_threshold"
                        value="<?php echo htmlspecialchars($settings['vip_threshold'] ?? '50000'); ?>"
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg font-semibold"
                        min="0" step="1000">
                </div>
                <p class="text-sm text-gray-500 mt-3 flex items-start gap-2">
                    <span class="text-yellow-500">💡</span>
                    สมาชิกที่ยอดสะสม ≥ จำนวนนี้จะได้รับสถานะ VIP อัตโนมัติ
                </p>
            </div>
        </div>

        <!-- Bank Settings -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b bg-gradient-to-r from-green-50 to-emerald-50">
                <h3 class="font-semibold text-lg flex items-center gap-2">
                    🏦 ข้อมูลการรับชำระ
                </h3>
                <p class="text-sm text-gray-500 mt-1">แสดงในใบเสร็จสำหรับลูกค้าโอนเงิน</p>
            </div>
            <div class="p-5 space-y-4">
                <!-- Bank Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ธนาคาร</label>
                    <select name="bank_name"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <?php
                        $banks = ['ธนาคารกสิกรไทย', 'ธนาคารกรุงไทย', 'ธนาคารไทยพาณิชย์', 'ธนาคารกรุงศรีอยุธยา', 'ธนาคารทหารไทยธนชาต', 'ธนาคารออมสิน', 'ธนาคารกรุงเทพ'];
                        foreach ($banks as $bank):
                            ?>
                            <option value="<?php echo $bank; ?>" <?php echo ($settings['bank_name'] ?? '') === $bank ? 'selected' : ''; ?>>
                                <?php echo $bank; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Account Number -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">เลขบัญชี</label>
                    <input type="text" name="bank_account"
                        value="<?php echo htmlspecialchars($settings['bank_account'] ?? ''); ?>"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono tracking-wider"
                        placeholder="xxx-x-xxxxx-x">
                </div>

                <!-- Account Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อบัญชี</label>
                    <input type="text" name="bank_account_name"
                        value="<?php echo htmlspecialchars($settings['bank_account_name'] ?? ''); ?>"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="ชื่อ-นามสกุล หรือ ชื่อบริษัท">
                </div>

                <!-- PromptPay -->
                <div class="pt-3 border-t">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="bg-blue-600 text-white text-xs px-1.5 py-0.5 rounded">PP</span>
                            PromptPay
                        </span>
                    </label>
                    <input type="text" name="promptpay"
                        value="<?php echo htmlspecialchars($settings['promptpay'] ?? ''); ?>"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono"
                        placeholder="เบอร์โทร หรือ เลขประจำตัวผู้เสียภาษี">
                    <p class="text-sm text-gray-500 mt-2">ใช้สำหรับสร้าง QR Code ชำระเงิน</p>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>