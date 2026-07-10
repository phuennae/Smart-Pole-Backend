<?php
include 'config.php';

// กำหนด Username และ Password ใหม่ที่นี่
$new_user = 'admin';
$new_pass = '123456'; // <--- แก้รหัสตามที่ต้องการ
$hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

try {
    // ลบ Admin เก่า (ถ้ามี)
    $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$new_user]);
    
    // เพิ่ม Admin ใหม่พร้อม Password Hash ที่ถูกต้อง
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
    $stmt->execute([$new_user, $hashed_pass]);

    echo "✅ รีเซ็ตรหัสผ่านสำเร็จ!<br>";
    echo "Username: <b>$new_user</b><br>";
    echo "Password: <b>$new_pass</b><br>";
    echo "<a href='login.php'>ไปหน้า Login</a>";
} catch (Exception $e) {
    echo "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>