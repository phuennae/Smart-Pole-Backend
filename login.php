<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php'; 
date_default_timezone_set('Asia/Bangkok'); // ตั้งโซนเวลาให้ตรงกัน

$user = isset($_POST['username']) ? $_POST['username'] : '';
$pass = isset($_POST['password']) ? $_POST['password'] : '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$user]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user_data && password_verify($pass, $user_data['password'])) {
    
    // ✅ เช็คว่ามีคนใช้อยู่ไหม (เช็คจากเวลาที่ Active ล่าสุด ภายใน 30 วินาทีที่ผ่านมา)
    if (!empty($user_data['session_token']) && !empty($user_data['last_active'])) {
        $last_active = strtotime($user_data['last_active']);
        $now = time();
        if (($now - $last_active) < 30) {
            echo json_encode([
                "status" => "error", 
                "message" => "บัญชีนี้กำลังถูกใช้งานอยู่โดยอุปกรณ์อื่น"
            ]);
            exit; // หยุดทำงาน ไม่ให้ล็อกอิน
        }
    }

    // ✅ ถ้าไม่มีคนใช้ (หรือคนเก่าปิดเว็บไปเกิน 30 วินาทีแล้ว) สร้าง Token ใหม่ให้เข้าได้เลย
    $session_token = bin2hex(random_bytes(16)); 
    $now_str = date('Y-m-d H:i:s');
    
    $update_stmt = $pdo->prepare("UPDATE users SET session_token = ?, last_active = ? WHERE id = ?");
    $update_stmt->execute([$session_token, $now_str, $user_data['id']]);

    echo json_encode([
        "status" => "success", 
        "user" => [
            "id" => $user_data['id'],
            "username" => $user_data['username'],
            "role" => $user_data['role']
        ],
        "session_token" => $session_token
    ]);

} else {
    echo json_encode([
        "status" => "error", 
        "message" => "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"
    ]);
}
?>