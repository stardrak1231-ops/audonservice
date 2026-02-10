<?php
/**
 * Accounting Header Component
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

requireStaffLogin();
$currentUser = getCurrentUser();

// Check if user is accounting or admin
if (!in_array($currentUser['role'] ?? '', ['accountant', 'admin'])) {
    // Redirect to appropriate portal based on role
    if ($currentUser['role'] === 'technician') {
        header('Location: /model01/technician/');
    } else {
        header('Location: /model01/staff-login.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $pageTitle ?? 'Accounting'; ?> | ระบบจัดการอู่ซ่อมรถ
    </title>
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
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <!-- Top Navigation -->
    <nav class="bg-emerald-700 text-white shadow-lg sticky top-0 z-50 no-print">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-6">
                    <a href="/model01/accounting/" class="font-bold text-lg flex items-center gap-2">
                        🚗 ระบบจัดการอู่ซ่อมรถ
                    </a>
                    <span class="bg-emerald-500 text-white text-xs px-2 py-1 rounded-full">ฝ่ายบัญชี</span>
                    <div class="hidden md:flex items-center gap-1">
                        <?php
                        $currentPage = basename($_SERVER['PHP_SELF'], '.php');
                        $navItems = [
                            'index' => ['label' => 'รอออกบิล', 'icon' => '📋'],
                            'payments' => ['label' => 'การชำระเงิน', 'icon' => '💳'],
                            'reports' => ['label' => 'รายงาน', 'icon' => '📊'],
                        ];
                        foreach ($navItems as $page => $item):
                            $isActive = $currentPage === $page;
                            ?>
                            <a href="<?php echo $page; ?>.php"
                                class="px-3 py-2 rounded-lg text-sm <?php echo $isActive ? 'bg-emerald-600' : 'hover:bg-emerald-600/50'; ?>">
                                <?php echo $item['icon'] . ' ' . $item['label']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-sm">
                        <span class="text-emerald-200">สวัสดี,</span>
                        <span class="font-medium">
                            <?php echo htmlspecialchars($currentUser['first_name']); ?>
                        </span>
                    </div>
                    <a href="/model01/logout.php"
                        class="bg-emerald-600 hover:bg-emerald-500 px-3 py-1.5 rounded-lg text-sm">
                        ออก
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-4 py-6">