<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }
include 'config.php';

function syncToNode($node_id, $ip, $port, $pdo) {
    $port = $port ?: 80;
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    @file_get_contents("http://$ip:$port/clear-sched", false, $ctx);
    
    $stmt = $pdo->prepare("SELECT * FROM node_schedules WHERE node_id = ? AND is_active = 1");
    $stmt->execute([$node_id]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $volPercent = isset($row['volume']) ? $row['volume'] : 50; 
        $espVol = round(($volPercent * 21) / 100);
        $url = "http://$ip:$port/remote-add-timer?day={$row['day_of_week']}&hr={$row['hour']}&mn={$row['minute']}&vol={$espVol}&file=".urlencode($row['filename']);
        @file_get_contents($url, false, $ctx);
    }
}

$id = $_POST['id'] ?? 0;
$node_id = $_POST['node_id'] ?? 0;
$ip = $_POST['ip'] ?? '';
$port = $_POST['port'] ?? '80';

if ($id > 0 && $node_id > 0) {
    try {
        // ลบออกจากฐานข้อมูล
        $stmt = $pdo->prepare("DELETE FROM node_schedules WHERE id = ?");
        $stmt->execute([$id]);
        
        // สั่งอัปเดตไปที่ ESP32
        syncToNode($node_id, $ip, $port, $pdo);
        
        echo json_encode(["status" => "success", "message" => "ลบสำเร็จ"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ไม่พบรหัสตารางเวลา"]);
}
?>