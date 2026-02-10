<?php
/**
 * Session Management
 * ระบบจัดการ Session สำหรับ Member และ Staff
 */

// เริ่ม session ถ้ายังไม่ได้เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ตรวจสอบว่า login อยู่หรือไม่
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) || isset($_SESSION['member_id']);
}

/**
 * ตรวจสอบว่าเป็น Member หรือไม่
 */
function isMember(): bool
{
    return isset($_SESSION['member_id']);
}

/**
 * ตรวจสอบว่าเป็น Staff หรือไม่
 */
function isStaff(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * ดึงข้อมูล Member ที่ login อยู่
 */
function getCurrentMember(): ?array
{
    if (!isMember())
        return null;
    return [
        'member_id' => $_SESSION['member_id'],
        'member_code' => $_SESSION['member_code'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'phone' => $_SESSION['phone'] ?? '',
    ];
}

/**
 * ดึงข้อมูล Staff ที่ login อยู่
 */
function getCurrentUser(): ?array
{
    if (!isStaff())
        return null;
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'role' => $_SESSION['role'] ?? 'technician',
        'role_name' => $_SESSION['role_name'] ?? '',
    ];
}

/**
 * Login สำหรับ Member
 */
function loginMember(array $member): void
{
    $_SESSION['member_id'] = $member['member_id'];
    $_SESSION['member_code'] = $member['member_code'];
    $_SESSION['first_name'] = $member['first_name'];
    $_SESSION['last_name'] = $member['last_name'];
    $_SESSION['phone'] = $member['phone'];
    $_SESSION['login_type'] = 'member';
}

/**
 * Login สำหรับ Staff
 */
function loginUser(array $user): void
{
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['role_name'] = $user['role_name'] ?? '';
    $_SESSION['login_type'] = 'staff';
}

/**
 * Logout
 */
function logout(): void
{
    session_unset();
    session_destroy();
}

/**
 * Require Member Login - redirect ถ้ายังไม่ได้ login
 */
function requireMemberLogin(): void
{
    if (!isMember()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require Staff Login - redirect ถ้ายังไม่ได้ login
 */
function requireStaffLogin(): void
{
    if (!isStaff()) {
        header('Location: /model01/staff-login.php');
        exit;
    }
}
?>