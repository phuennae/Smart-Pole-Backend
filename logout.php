<?php
// เริ่มต้น session เพื่อให้เข้าถึงข้อมูลปัจจุบันได้
session_start();

// ลบตัวแปร session ทั้งหมด
$_SESSION = array();

// ถ้ามีการใช้ cookie สำหรับ session ให้ลบทิ้งด้วย
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย session
session_destroy();

// ส่งผู้ใช้กลับไปหน้า login หรือหน้าหลัก
header("Location: login.php");
exit;
?>