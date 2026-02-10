<?php
/**
 * Member Header Component
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

requireMemberLogin();
$currentMember = getCurrentMember();

$pdo = getDBConnection();

// Get member vehicles
$vehiclesStmt = $pdo->prepare("SELECT * FROM vehicles WHERE member_id = ?");
$vehiclesStmt->execute([$currentMember['member_id']]);
$memberVehicles = $vehiclesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $pageTitle ?? 'Member'; ?> | อู่อุดร Service
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

<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- Top Navigation -->
    <nav class="bg-white shadow-md sticky top-0 z-50 no-print">
        <div class="max-w-5xl mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-6">
                    <a href="/model01/member/" class="font-bold text-lg text-blue-600 flex items-center gap-2">
                        🚗 อู่อุดร Service
                    </a>
                </div>
                <div class="flex items-center gap-3">
                    <a href="profile.php" class="flex items-center gap-2 text-gray-600 hover:text-blue-600">
                        <?php
                        $profileImg = $currentMember['profile_image_url'] ?? '';
                        if ($profileImg): ?>
                            <img src="/model01/<?php echo htmlspecialchars($profileImg); ?>"
                                class="w-8 h-8 rounded-full object-cover border-2 border-blue-200">
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm">👤</div>
                        <?php endif; ?>
                        <span
                            class="hidden sm:inline"><?php echo htmlspecialchars($currentMember['first_name']); ?></span>
                    </a>
                    <a href="/model01/logout.php" class="text-gray-400 hover:text-red-500 text-sm">
                        ออก
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sub Navigation -->
    <div class="bg-white border-b no-print">
        <div class="max-w-5xl mx-auto px-4">
            <div class="flex items-center gap-1 overflow-x-auto">
                <?php
                $currentPage = basename($_SERVER['PHP_SELF'], '.php');
                $navItems = [
                    'index' => ['label' => 'งานปัจจุบัน', 'icon' => '📋'],
                    'history' => ['label' => 'ประวัติ', 'icon' => '📜'],
                    'profile' => ['label' => 'ข้อมูลส่วนตัว', 'icon' => '👤'],
                ];
                foreach ($navItems as $page => $item):
                    $isActive = $currentPage === $page;
                    ?>
                    <a href="<?php echo $page; ?>.php"
                        class="px-4 py-3 text-sm whitespace-nowrap border-b-2 <?php echo $isActive ? 'border-blue-600 text-blue-600 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        <?php echo $item['icon'] . ' ' . $item['label']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-5xl mx-auto px-4 py-6 flex-grow w-full">