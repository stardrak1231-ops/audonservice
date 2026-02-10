<?php
/**
 * Logout - ออกจากระบบ (รวมทุก Role)
 */

require_once 'config/session.php';

// Check if staff before logout (to determine redirect)
$isStaff = isStaff();

logout();

// Redirect based on user type
if ($isStaff) {
    header('Location: /model01/staff-login.php');
} else {
    header('Location: /model01/login.php');
}
exit;
