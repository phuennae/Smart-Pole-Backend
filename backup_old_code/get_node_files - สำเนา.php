<?php
// get_node_files.php
include 'config.php';

$ip = $_GET['ip'];
$port = $_GET['port'] ?? 80;
$node_id = $_GET['id'];

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

if ($files && is_array($files)) {
    echo '<div class="d-flex flex-column gap-2">';
    echo '<select class="form-select form-select-sm" id="file_'.$node_id.'">';
    foreach ($files as $f) {
        $fname = $f['name'];
        if (strtolower($fname) === 'alarm007.mp3' || strtolower($fname) === 'vol.txt') continue;
        if (strtolower(pathinfo($fname, PATHINFO_EXTENSION)) === 'mp3') {
            echo '<option value="'.htmlspecialchars($fname).'">'.htmlspecialchars($fname).'</option>';
        }
    }
    echo '</select>';
    echo '<div class="btn-group w-100">';
    echo '<button class="btn btn-sm btn-success" onclick="playFile(\''.$ip.'\', \''.$port.'\', \'file_'.$node_id.'\')">เล่น</button>';
    echo '<button class="btn btn-sm btn-danger" onclick="stopFile(\''.$ip.'\', \''.$port.'\')">หยุด</button>';
    echo '<button class="btn btn-sm btn-danger" onclick="confirmDeleteFile(\''.$ip.'\', '.$port.', \'select_'.$node_id.'\')"><i class="fas fa-trash"></i></button>';
	echo '</div></div>';
} else {
    echo '<div class="text-center py-1"><span class="text-muted small"><i class="fas fa-exclamation-circle"></i> ไม่พบไฟล์/Offline</span></div>';
}
?>