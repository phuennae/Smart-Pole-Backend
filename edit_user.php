<?php
// 1. ตั้งค่า Header สำหรับ CORS เพื่ออนุญาตให้ React เข้าถึงได้
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

require_once 'config.php';

// ดึงค่าจาก $_POST ตามที่ React ส่งมาแบบ FormData
$id = $_POST['id'] ?? '';
$name = $_POST['username'] ?? '';   
$password = $_POST['password'] ?? ''; 
$role = $_POST['role'] ?? '';       

if (empty($id) || empty($name)) {
    echo json_encode(["status" => "error", "message" => "ข้อมูลไม่ครบถ้วน (ต้องการ ID และ ชื่อสมาชิก)"]);
    exit;
}

// แปลง Role ให้เป็นตัวพิมพ์เล็ก (admin / user) ให้ตรงกับ DB เสมอ
if (!empty($role)) {
    $role = strtolower($role);
}

try {
    // กำหนดให้ PDO พ่น Error ออกมาหากคิวรีพัง
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!empty($password)) {
        // 💡 เข้ารหัสผ่านแบบ bcrypt ให้ตรงกับระบบ Login
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
        $stmt->execute([$name, $hashed_password, $role, $id]);
    } else {
        // กรณีต้องการเปลี่ยนแค่ชื่อและสิทธิ์ (คงรหัสผ่านเดิมไว้ ไม่แก้ไขฟิลด์ password)
        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
        $stmt->execute([$name, $role, $id]);
    }

    echo json_encode(["status" => "success", "message" => "อัปเดตข้อมูลสมาชิกเรียบร้อยแล้ว"]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "SQL Error: " . $e->getMessage()]);
}
?>