<?php
require_once 'config.php';
$action = $_REQUEST['action'] ?? '';

// ฟังก์ชันส่งข้อมูลตารางเวลาทั้งหมดไปที่ ESP32
// ฟังก์ชันส่งข้อมูลตารางเวลาทั้งหมดไปที่ ESP32
function syncToNode($node_id, $ip, $port, $pdo) {
    $port = $port ?: 80;
    // 1. สั่ง ESP32 ล้างตารางเดิมใน RAM/SD
    @file_get_contents("http://$ip:$port/clear-sched");
    
    // 2. ดึงตารางที่ "เปิดใช้งาน (is_active = 1)" เท่านั้นส่งไปให้ ESP32
    $stmt = $pdo->prepare("SELECT * FROM node_schedules WHERE node_id = ? AND is_active = 1");
    $stmt->execute([$node_id]);
    foreach($stmt->fetchAll() as $row) {
        // ดึงค่า volume แบบเปอร์เซ็นต์ (0-100) มาด้วย (ถ้าไม่มีให้ค่าเริ่มต้นเป็น 50)
        $volPercent = isset($row['volume']) ? $row['volume'] : 50; 
        
        // แปลงสเกลจาก 0-100 เป็น 0-21 สำหรับ ESP32
        $espVol = round(($volPercent * 21) / 100);
        
        // เพิ่ม &vol= ด้วยค่าที่แปลงแล้ว ($espVol) เข้าไปใน URL
        $url = "http://$ip:$port/remote-add-timer?day={$row['day_of_week']}&hr={$row['hour']}&mn={$row['minute']}&vol={$espVol}&file=".urlencode($row['filename']);
        @file_get_contents($url);
    }
}

if ($action == 'list') {
    $stmt = $pdo->prepare("SELECT * FROM node_schedules WHERE node_id = ? ORDER BY day_of_week, hour, minute");
    $stmt->execute([$_GET['node_id']]);
    $data = $stmt->fetchAll();
    
    // เพิ่มหัวตาราง "ความดัง"
    echo '<table class="table table-sm small text-center align-middle"><thead><tr><th>สถานะ</th><th>วัน</th><th>เวลา</th><th>ความดัง</th><th class="text-start">ไฟล์</th><th>ลบ</th></tr></thead><tbody>';
    foreach($data as $r) {
        $days = ["อา.", "จ.", "อ.", "พ.", "พฤ.", "ศ.", "ส.", "ทุกวัน"];
        $btnStatus = $r['is_active'] 
            ? "<button class='btn btn-xs btn-success' onclick='toggleSched({$r['id']}, 0)'>เปิด</button>" 
            : "<button class='btn btn-xs btn-secondary' onclick='toggleSched({$r['id']}, 1)'>ปิด</button>";
            
        $volDisplay = isset($r['volume']) ? $r['volume']."%" : "-";
            
        echo "<tr>
                <td>$btnStatus</td>
                <td>{$days[$r['day_of_week']]}</td>
                <td>".sprintf("%02d:%02d", $r['hour'], $r['minute'])."</td>
                <td><span class='badge bg-info text-dark'>{$volDisplay}</span></td>
                <td class='text-start text-truncate' style='max-width: 150px;'>{$r['filename']}</td>
                <td><a href='javascript:void(0)' class='text-danger' onclick='deleteSched({$r['id']})'><i class='fas fa-trash'></i></a></td>
              </tr>";
    }
    echo '</tbody></table>';
}

if ($action == 'add') {
    // รับค่า volume จาก $_POST
    $vol = isset($_POST['volume']) ? (int)$_POST['volume'] : 50;
    
    // เพิ่มคอลัมน์ volume ในคำสั่ง INSERT
    $stmt = $pdo->prepare("INSERT INTO node_schedules (node_id, day_of_week, hour, minute, volume, filename, is_active) VALUES (?,?,?,?,?,?,1)");
    $stmt->execute([$_POST['node_id'], $_POST['day'], $_POST['hr'], $_POST['mn'], $vol, $_POST['file']]);
    
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

if ($action == 'delete_by_file') {
    $node_id = $_POST['node_id'];
    $filename = $_POST['file'];
    
    $stmt = $pdo->prepare("DELETE FROM node_schedules WHERE node_id = ? AND filename = ?");
    $stmt->execute([$node_id, $filename]);
    
    // ซิงค์หลังจากลบไฟล์
    // **ข้อควรระวัง:** ต้องระวังว่าใน $_POST มี ip และ port ส่งมาครบหรือไม่
    $ip = $_POST['ip'] ?? '';
    $port = $_POST['port'] ?? '';
    if ($ip != '') {
        syncToNode($node_id, $ip, $port, $pdo);
    }
    
    echo "DB schedules cleared";
}