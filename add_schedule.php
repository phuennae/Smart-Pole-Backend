<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }
include 'config.php';

// ฟังก์ชันแปลงข้อความวันเป็นตัวเลข (ให้ตรงกับ ESP32)
function getDayInt($dayStr) {
    $map = [
        'วันอาทิตย์' => 0, 'วันจันทร์' => 1, 'วันอังคาร' => 2, 'วันพุธ' => 3,
        'วันพฤหัสบดี' => 4, 'วันศุกร์' => 5, 'วันเสาร์' => 6, 'ทุกวัน' => 7, 'วันทำงาน(จ-ศ)' => 8
    ];
    return isset($map[$dayStr]) ? $map[$dayStr] : 7;
}

// ฟังก์ชันซิงค์ข้อมูลไปที่เสา ESP32 (ตามฉบับซัพพลายเออร์)
function syncToNode($node_id, $ip, $port, $pdo) {
    $port = $port ?: 80;
    $ctx = stream_context_create(['http' => ['timeout' => 2]]); // ป้องกันเว็บค้าง
    
    // 1. สั่งล้างตารางเก่า
    @file_get_contents("http://$ip:$port/clear-sched", false, $ctx);
    
    // 2. ดึงตารางทั้งหมดส่งไปใหม่
    $stmt = $pdo->prepare("SELECT * FROM node_schedules WHERE node_id = ? AND is_active = 1");
    $stmt->execute([$node_id]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $volPercent = isset($row['volume']) ? $row['volume'] : 50; 
        $espVol = round(($volPercent * 21) / 100); // แปลงเสียง 0-100 เป็น 0-21
        
        $url = "http://$ip:$port/remote-add-timer?day={$row['day_of_week']}&hr={$row['hour']}&mn={$row['minute']}&vol={$espVol}&file=".urlencode($row['filename']);
        @file_get_contents($url, false, $ctx);
    }
}

// รับค่าจาก React
$node_id = $_POST['node_id'] ?? 0;
$ip = $_POST['ip'] ?? '';
$port = $_POST['port'] ?? '80';
$dayStr = $_POST['days'] ?? 'ทุกวัน';
$time = $_POST['time'] ?? '';
$file = $_POST['file'] ?? '';
$volume = $_POST['volume'] ?? 80;

if ($node_id > 0 && $time != '') {
    $dayInt = getDayInt($dayStr);
    $timeParts = explode(':', $time);
    $hr = (int)$timeParts[0];
    $mn = (int)$timeParts[1];

    try {
        // บันทึกลงฐานข้อมูล
        $stmt = $pdo->prepare("INSERT INTO node_schedules (node_id, day_of_week, hour, minute, volume, filename, is_active) VALUES (?,?,?,?,?,?,1)");
        $stmt->execute([$node_id, $dayInt, $hr, $mn, $volume, $file]);
        
        // สั่งอัปเดตไปที่ ESP32
        syncToNode($node_id, $ip, $port, $pdo);
        
        echo json_encode(["status" => "success", "message" => "บันทึกสำเร็จ"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
     echo json_encode(["status" => "error", "message" => "ข้อมูลไม่ครบ"]);
}
?>