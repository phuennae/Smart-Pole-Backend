<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'config.php';

$ip = $_GET['ip'] ?? '';
$port = $_GET['port'] ?? 80;

if (empty($ip)) {
    echo json_encode(["status" => "error", "message" => "กรุณาส่ง IP มาด้วย", "files" => []]);
    exit;
}

function getFileList($ip, $port) {
    global $api_key;
    $url = "http://{$ip}:{$port}/listfiles?key=" . urlencode($api_key);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);        
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($http_code === 200) ? json_decode($result, true) : false;
}

$files = getFileList($ip, $port);
$mp3_files = [];

if ($files && is_array($files)) {
    foreach ($files as $f) {
        $fname = $f['name'];
        // ข้ามไฟล์ระบบ
        if (strtolower($fname) === 'alarm007.mp3' || strtolower($fname) === 'vol.txt') continue;
        // กรองเอาเฉพาะไฟล์ MP3
        if (strtolower(pathinfo($fname, PATHINFO_EXTENSION)) === 'mp3') {
            $mp3_files[] = htmlspecialchars($fname);
        }
    }
    // ส่งรายชื่อไฟล์ออกเป็น JSON Array
    echo json_encode(["status" => "success", "files" => $mp3_files]);
} else {
    echo json_encode(["status" => "error", "message" => "อุปกรณ์ Offline หรือหาไฟล์ไม่พบ", "files" => []]);
}
?>