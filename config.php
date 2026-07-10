<?php
session_start();

// ตั้งค่า Error (สำหรับเช็คงาน)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = "localhost";
$user = "root"; 
// 👇 แก้ไขตรงนี้ครับ: เปลี่ยนให้เป็นค่าว่าง (ค่าเริ่มต้นของ XAMPP)
$pass = ""; 
$db   = "jukebox_db";
$api_key = "*sS5383160*"; //

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // แก้ไขให้พ่นออกมาเป็น JSON เพื่อไม่ให้ React พัง
    die(json_encode(["status" => "error", "message" => "DB Connection failed: " . $e->getMessage()]));
}

// ย้ายฟังก์ชันมาไว้ใน config ให้เรียกใช้ได้จากทุกที่
if (!function_exists('sendToESP')) {
    function sendToESP($ip, $path_with_params, $port = 80) {
        global $api_key;
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $connector = (strpos($path_with_params, '?') !== false) ? '&' : '?';
        
        // ผสม IP และ Port เพื่อรองรับรูปแบบ theoneiot.i234.me:8080
        $url = "http://" . $ip . ":" . $port . $path_with_params . $connector . "key=" . urlencode($api_key);
        
        $result = @file_get_contents($url, false, $ctx);
        return $result;
    }
}

if (!function_exists('checkStatus')) {
    function checkStatus($ip, $port) {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $url = "http://" . $ip . ":" . $port . "/status";
        $result = @file_get_contents($url, false, $ctx);
        
        if ($result) {
            return json_decode($result, true);
        }
        return false;
    }
}
?>