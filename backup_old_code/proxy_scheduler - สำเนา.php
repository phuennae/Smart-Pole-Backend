<?php
require_once 'config.php';
$action = $_REQUEST['action'] ?? '';

// ฟังก์ชันส่งข้อมูลตารางเวลาทั้งหมดไปที่ ESP32
function syncToNode($node_id, $ip, $port, $pdo) {
    $port = $port ?: 80;
    // 1. สั่ง ESP32 ล้างตารางเดิมใน RAM/SD (ตามโค้ด C++ ที่มี /clear-sched)
    @file_get_contents("http://$ip:$port/clear-sched");
    
    // 2. ดึงตารางที่ "เปิดใช้งาน (is_active = 1)" เท่านั้นส่งไปให้ ESP32
    $stmt = $pdo->prepare("SELECT * FROM node_schedules WHERE node_id = ? AND is_active = 1");
    $stmt->execute([$node_id]);
    foreach($stmt->fetchAll() as $row) {
        $url = "http://$ip:$port/remote-add-timer?day={$row['day_of_week']}&hr={$row['hour']}&mn={$row['minute']}&file=".urlencode($row['filename']);
        @file_get_contents($url);
    }
}

if ($action == 'list') {
    $stmt = $pdo->prepare("SELECT * FROM node_schedules WHERE node_id = ? ORDER BY day_of_week, hour, minute");
    $stmt->execute([$_GET['node_id']]);
    $data = $stmt->fetchAll();
    echo '<table class="table table-sm small"><thead><tr><th>สถานะ</th><th>วัน</th><th>เวลา</th><th>ไฟล์</th><th>ลบ</th></tr></thead><tbody>';
    foreach($data as $r) {
        $days = ["อา.", "จ.", "อ.", "พ.", "พฤ.", "ศ.", "ส.", "ทุกวัน"];
        // ปุ่ม Toggle: ส่งค่าสลับ 0/1 ไปที่ฟังก์ชัน toggleSched
        $btnStatus = $r['is_active'] 
            ? "<button class='btn btn-xs btn-success' onclick='toggleSched({$r['id']}, 0)'>เปิด</button>" 
            : "<button class='btn btn-xs btn-secondary' onclick='toggleSched({$r['id']}, 1)'>ปิด</button>";
            
        echo "<tr>
                <td>$btnStatus</td>
                <td>{$days[$r['day_of_week']]}</td>
                <td>".sprintf("%02d:%02d", $r['hour'], $r['minute'])."</td>
                <td>{$r['filename']}</td>
                <td><a href='javascript:void(0)' class='text-danger' onclick='deleteSched({$r['id']})'><i class='fas fa-trash'></i></a></td>
              </tr>";
    }
    echo '</tbody></table>';
}

if ($action == 'add') {
    $stmt = $pdo->prepare("INSERT INTO node_schedules (node_id, day_of_week, hour, minute, filename, is_active) VALUES (?,?,?,?,?,1)");
    $stmt->execute([$_POST['node_id'], $_POST['day'], $_POST['hr'], $_POST['mn'], $_POST['file']]);
    syncToNode($_POST['node_id'], $_POST['ip'], $_POST['port'], $pdo);
}

if ($action == 'toggle') {
    $stmt = $pdo->prepare("UPDATE node_schedules SET is_active = ? WHERE id = ?");
    $stmt->execute([$_GET['active'], $_GET['id']]);
    syncToNode($_GET['node_id'], $_GET['ip'], $_GET['port'], $pdo);
}

if ($action == 'delete') {
    $stmt = $pdo->prepare("DELETE FROM node_schedules WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    syncToNode($_GET['node_id'], $_GET['ip'], $_GET['port'], $pdo);
}
// เพิ่มต่อจาก action == 'delete' เดิม
if ($action == 'delete_by_file') {
    $node_id = $_POST['node_id'];
    $filename = $_POST['file'];
    
    // 1. ลบรายการในฐานข้อมูลที่ใช้ไฟล์นี้และโหนดนี้
    $stmt = $pdo->prepare("DELETE FROM node_schedules WHERE node_id = ? AND filename = ?");
    $stmt->execute([$node_id, $filename]);
    
    // 2. ซิงค์ข้อมูลที่เหลือ (หลังลบแล้ว) ไปที่ตัว ESP32 เพื่ออัปเดตตารางใน RAM บอร์ด
    syncToNode($node_id, $_POST['ip'], $_POST['port'], $pdo);
    
    echo "DB schedules cleared";
}