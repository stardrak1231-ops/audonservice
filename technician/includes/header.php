<?php
/**
 * Technician Header Component
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

requireStaffLogin();
$currentUser = getCurrentUser();

// Check if user is technician
if (($currentUser['role'] ?? '') !== 'technician') {
    // Redirect to appropriate portal based on role
    if ($currentUser['role'] === 'admin') {
        header('Location: /model01/admin/');
    } elseif ($currentUser['role'] === 'accountant') {
        header('Location: /model01/accounting/');
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
        <?php echo $pageTitle ?? 'Technician'; ?> | ระบบจัดการอู่ซ่อมรถ
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <!-- Top Navigation -->
    <nav class="bg-blue-700 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-4">
                    <a href="/model01/technician/" class="font-bold text-lg flex items-center gap-2">
                        🚗 ระบบจัดการอู่ซ่อมรถ
                    </a>
                    <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full">ฝ่ายซ่อม</span>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-sm">
                        <span class="text-blue-200">สวัสดี,</span>
                        <span class="font-medium">
                            <?php echo htmlspecialchars($currentUser['first_name']); ?>
                        </span>
                    </div>
                    <a href="/model01/logout.php"
                        class="bg-blue-600 hover:bg-blue-500 px-3 py-1.5 rounded-lg text-sm flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                            </path>
                        </svg>
                        ออก
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-4 py-6">