<?php
/**
 * Admin Sidebar Component
 */

date_default_timezone_set('Asia/Bangkok');

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!-- Sidebar -->
<aside class="w-64 bg-gray-800 text-white flex-shrink-0 fixed inset-y-0 left-0 flex flex-col z-40">
    <div class="p-4 border-b border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                    </path>
                </svg>
            </div>
            <div>
                <div class="font-bold text-sm">อู่อุดร Service</div>
                <div class="text-xs text-gray-400">Admin Panel</div>
            </div>
        </div>
    </div>

    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <!-- Dashboard -->
        <a href="/model01/admin/index.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo ($currentPage == 'index' && $currentDir == 'admin') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                </path>
            </svg>
            <span>Dashboard</span>
        </a>

        <!-- Users -->
        <a href="/model01/admin/users/index.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentDir == 'users' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                </path>
            </svg>
            <span>จัดการผู้ใช้งาน</span>
        </a>

        <!-- Members -->
        <a href="/model01/admin/members/index.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentDir == 'members' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                </path>
            </svg>
            <span>สมาชิกและรถยนต์</span>
        </a>


        <!-- Services -->
        <a href="/model01/admin/services/index.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentDir == 'services' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z">
                </path>
            </svg>
            <span>ค่าบริการ / ค่าแรง</span>
        </a>

        <!-- Parts -->
        <a href="/model01/admin/parts/index.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentDir == 'parts' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            <span>คลังอะไหล่</span>
        </a>

        <!-- Procurement (PO + Suppliers) -->
        <a href="/model01/admin/po/index.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentDir == 'po' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                </path>
            </svg>
            <span>จัดซื้ออะไหล่</span>
        </a>
        <a href="/model01/admin/jobs/index.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentDir == 'jobs' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01">
                </path>
            </svg>
            <span>ใบงาน</span>
        </a>

        <!-- Reports -->
        <a href="/model01/admin/reports/index.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentDir == 'reports' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                </path>
            </svg>
            <span>รายงาน</span>
        </a>

        <!-- Promotions -->
        <a href="/model01/admin/promotions.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo ($currentPage == 'promotions') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z">
                </path>
            </svg>
            <span>ข่าวสาร/โปรโมชั่น</span>
        </a>

        <!-- Divider -->
        <hr class="border-gray-700 my-4">

        <!-- Settings -->
        <a href="/model01/admin/settings.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-300 hover:bg-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                </path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            <span>ตั้งค่าระบบ</span>
        </a>
    </nav>

    <!-- User Info -->
    <div class="absolute bottom-0 left-0 w-64 p-4 border-t border-gray-700 bg-gray-800">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gray-600 rounded-full flex items-center justify-center">
                <span class="text-sm font-semibold">
                    <?php echo mb_substr($currentUser['first_name'], 0, 1); ?>
                </span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate">
                    <?php echo htmlspecialchars($currentUser['first_name']); ?>
                </div>
                <div class="text-xs text-gray-400">
                    <?php echo htmlspecialchars($currentUser['role_name'] ?? 'Staff'); ?>
                </div>
            </div>
            <a href="/model01/logout.php" class="text-gray-400 hover:text-white" title="ออกจากระบบ">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                    </path>
                </svg>
            </a>
        </div>
    </div>
</aside>

<!-- Main Content -->
<div class="flex-1 flex flex-col ml-64">
    <!-- Top Bar -->
    <header class="bg-white shadow-sm border-b h-16 flex items-center justify-between px-6">
        <h1 class="text-xl font-semibold text-gray-800">
            <?php echo $pageTitle ?? 'Admin'; ?>
        </h1>
        <div class="text-sm text-gray-500">
            <?php echo date('d/m/Y H:i'); ?>
        </div>
    </header>

    <!-- Page Content -->
    <main class="flex-1 p-6 overflow-y-auto">