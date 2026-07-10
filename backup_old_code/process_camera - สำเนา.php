<?php
session_start();
require_once 'config.php';

// 1. ความปลอดภัย: ตรวจสอบว่า Login หรือยัง และต้องเป็น Admin เท่านั้นถึงจะจัดการได้
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Error: คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (Admin Only)");
}

$action = $_GET['action'] ?? '';

try {
    // --- ส่วนการเพิ่มกล้องใหม่ ---
    if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $name     = $_POST['name'];
        $ip       = $_POST['ip'];
        $location = $_POST['location'];
        
        // เตรียมคำสั่ง SQL (ใช้ Prepared Statement เพื่อป้องกัน SQL Injection)
        $sql = "INSERT INTO cameras (camera_name, ip_address, location) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $ip, $location]);
        
        header("Location: cctv.php?msg=add_success");
        exit;
    }

    // --- ส่วนการลบกล้อง ---
    if ($action == 'delete' && isset($_GET['id'])) {
        $id = $_GET['id'];
        
        $sql = "DELETE FROM cameras WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        header("Location: cctv.php?msg=delete_success");
        exit;
    }

    // --- ส่วนการแก้ไขข้อมูลกล้อง (Optional) ---
    if ($action == 'edit' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $id       = $_POST['id'];
        $name     = $_POST['name'];
        $ip       = $_POST['ip'];
        $location = $_POST['location'];
        
        $sql = "UPDATE cameras SET camera_name = ?, ip_address = ?, location = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $ip, $location, $id]);
        
        header("Location: cctv.php?msg=edit_success");
        exit;
    }

} catch (PDOException $e) {
    // กรณีเกิดข้อผิดพลาดกับ Database
    die("Database Error: " . $e->getMessage());
}

// หากไม่มี action ตรงตามเงื่อนไข ให้ตีกลับไปหน้าหลัก
header("Location: cctv.php");
exit;