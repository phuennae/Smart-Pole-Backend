<?php
include 'config.php';
// เช็คสิทธิ์ Admin
if ($_SESSION['role'] !== 'admin') { die("สิทธิ์ไม่พอสำหรับเข้าถึงหน้านี้"); }

// 1. เพิ่มผู้ใช้ใหม่
if (isset($_POST['add_user'])) {
    $user = $_POST['username'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT); // เข้ารหัสลับ
    $role = $_POST['role'];

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$user, $pass, $role]);
        $msg = "✅ เพิ่มผู้ใช้ $user สำเร็จ";
    } catch (Exception $e) { $msg = "❌ ข้อผิดพลาด: ชื่อนี้อาจมีอยู่แล้ว"; }
}

// 2. ลบผู้ใช้ (ห้ามลบตัวเอง)
if (isset($_GET['del'])) {
    if ($_GET['del'] == $_SESSION['user_id']) {
        $msg = "❌ คุณไม่สามารถลบตัวเองได้!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_GET['del']]);
        $msg = "🗑️ ลบผู้ใช้เรียบร้อย";
    }
}

// 3. ดึงรายชื่อผู้ใช้ทั้งหมด
$users = $pdo->query("SELECT id, username, role FROM users")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .container { background: white; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .admin { background: #ffd700; }
        .user { background: #e0e0e0; }
    </style>
</head>
<body>
<div class="container">
    <h2>👤 จัดการสมาชิกและสิทธิ์</h2>
    <?php if(isset($msg)) echo "<p>$msg</p>"; ?>

    <form method="POST" style="background: #eee; padding: 15px; border-radius: 5px;">
        <h4>เพิ่มสมาชิกใหม่</h4>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="role">
            <option value="user">User (สั่งงานได้อย่างเดียว)</option>
            <option value="admin">Admin (จัดการระบบได้)</option>
        </select>
        <button type="submit" name="add_user">เพิ่มสมาชิก</button>
    </form>

    <table>
        <tr>
            <th>Username</th>
            <th>Role</th>
            <th>Action</th>
        </tr>
        <?php foreach($users as $u): ?>
        <tr>
            <td><?php echo htmlspecialchars($u['username']); ?></td>
            <td>
                <span class="badge <?php echo $u['role']; ?>">
                    <?php echo strtoupper($u['role']); ?>
                </span>
            </td>
            <td>
                <?php if($u['id'] != $_SESSION['user_id']): ?>
                    <a href="?del=<?php echo $u['id']; ?>" onclick="return confirm('ยืนยันการลบ?')">❌ ลบ</a>
                <?php else: ?>
                    <small>(ตัวคุณเอง)</small>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <br>
    <a href="index.php">⬅️ กลับหน้า Dashboard</a>
</div>
</body>
</html>