<?php
// test_playback_db.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 Testing playback_proxy DB Connection</h2>";

// วิธีที่ 1: ใช้ credentials ตรงๆ
echo "<h3>วิธีที่ 1: Hardcode credentials</h3>";
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=jukebox_db;charset=utf8",
        "root",
        "*sS5383160*",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connection OK<br>";
    
    $stmt = $pdo->query("SELECT id, camera_name, ptz_ip, ptz_username FROM cameras");
    $cameras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Cameras in database:</h4>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>PTZ IP</th><th>Username</th></tr>";
    foreach ($cameras as $cam) {
        echo "<tr>";
        echo "<td>{$cam['id']}</td>";
        echo "<td>{$cam['camera_name']}</td>";
        echo "<td>{$cam['ptz_ip']}</td>";
        echo "<td>{$cam['ptz_username']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// วิธีที่ 2: ใช้ config.php
echo "<h3>วิธีที่ 2: Require config.php</h3>";
try {
    require_once 'config.php';
    echo "✅ Config loaded<br>";
    
    if (isset($pdo)) {
        echo "✅ \$pdo is set<br>";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cameras");
        $result = $stmt->fetch();
        echo "Cameras count: <strong>{$result['count']}</strong><br>";
    } else {
        echo "❌ \$pdo is NOT set<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>