<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }
include 'config.php';

$node_id = $_POST['node_id'] ?? '';
$ip = $_POST['ip'] ?? '';
$port = $_POST['port'] ?? 80;
$volume = (int)($_POST['volume'] ?? 50);

if (!empty($node_id) && !empty($ip)) {
    try {
        // 1. สั่งงาน ESP32 ทันที (ให้เวลา Time out 1 วินาทีพอ)
        $url = "http://{$ip}:{$port}/vol?v=" . $volume;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_exec($ch);
        curl_close($ch);

        // 2. บันทึกความดังล่าสุดลง Database เผื่อไว้ดึงมาแสดงตอนเปิดเครื่องใหม่
        $stmt = $pdo->prepare("UPDATE nodes SET last_volume = ? WHERE id = ?");
        $stmt->execute([$volume, $node_id]);

        echo json_encode(["status" => "success", "message" => "ปรับเสียงเรียบร้อย"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ข้อมูลไม่ครบถ้วน"]);
}
?>