<?php
/**
 * Admin Header Component
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

requireStaffLogin();
$currentUser = getCurrentUser();

// Redirect non-admin roles to their portal
$role = $currentUser['role'] ?? '';
if ($role === 'accountant') {
    header('Location: /model01/accounting/');
    exit;
} elseif ($role === 'technician') {
    header('Location: /model01/technician/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $pageTitle ?? 'Admin'; ?> | ระบบจัดการอู่ซ่อมรถ
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen flex">