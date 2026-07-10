<?php
include 'config.php';

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// 2. ฟังก์ชันดึงรายชื่อไฟล์จาก ESP32 (ใช้ cURL)
function getFileList($ip, $port = 80) {
    global $api_key;
    $url = "http://{$ip}:{$port}/listfiles?key=" . urlencode($api_key);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);        
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $result) {
        $decoded = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
    }
    return false;
}

// 3. ดึงข้อมูล Nodes ทั้งหมด
$stmt = $pdo->query("SELECT * FROM nodes ORDER BY id DESC");
$nodes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Pole - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Sarabun', sans-serif; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="fas fa-microchip me-2"></i> รายชื่อ Node และการจัดการไฟล์</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ชื่อจุดติดตั้ง</th>
                                    <th>IP / Port</th>
                                    <th style="width: 350px;">จัดการเพลง (.mp3)</th>
                                    <th>อัปโหลดไฟล์</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nodes as $node): 
                                    $node_port = $node['port'] ?? 80;
                                    $files = getFileList($node['ip_address'], $node_port); 
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($node['name']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($node['ip_address']) ?>:<?= $node_port ?></code></td>
                                    <td>
                                        <?php if ($files && is_array($files)): ?>
                                            <div class="d-flex flex-column gap-2">
                                                <select class="form-select form-select-sm" id="file_<?= $node['id'] ?>">
                                                    <?php 
                                                    $has_mp3 = false;
                                                    foreach ($files as $f): 
                                                        if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'mp3'): 
                                                            $has_mp3 = true;
                                                    ?>
                                                        <option value="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></option>
                                                    <?php endif; endforeach; ?>
                                                </select>
                                                <div class="btn-group w-100">
                                                    <button class="btn btn-sm btn-success" onclick="playFile('<?= $node['ip_address'] ?>', '<?= $node_port ?>', 'file_<?= $node['id'] ?>')">เล่น</button>
                                                    <button class="btn btn-sm btn-danger" onclick="stopFile('<?= $node['ip_address'] ?>', '<?= $node_port ?>')">หยุด</button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small italic">Offline หรือไม่พบไฟล์</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="process_broadcast.php?action=upload_single&ip=<?= $node['ip_address'] ?>&port=<?= $node_port ?>" method="post" enctype="multipart/form-data">
                                            <div class="input-group input-group-sm">
                                                <input type="file" name="fileToUpload" class="form-control" required>
                                                <button class="btn btn-primary" type="submit"><i class="fas fa-upload"></i></button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-dark"><i class="fas fa-signal me-2 text-success"></i>สถานะปัจจุบัน</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Node</th>
                                <th>สถานะ</th>
                                <th>กำลังเล่น</th>
                                <th class="text-center">ตั้งค่า</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nodes as $node): ?>
                            <tr>
                                <td class="ps-3"><?= htmlspecialchars($node['name']) ?></td>
                                <td><span id="online-<?= $node['id'] ?>"><small class="text-muted">ตรวจสอบ...</small></span></td>
                                <td id="song-<?= $node['id'] ?>">-</td>
                                <td class="text-center">
                                    <a href="http://<?= $node['ip_address'] ?>:<?= $node['port'] ?? 80 ?>" target="_blank" class="text-secondary fs-5" title="ตั้งค่าอุปกรณ์">
                                        <i class="fas fa-cog"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger text-white text-center py-3">
                    <h5 class="mb-0">Broadcast รวม</h5>
                </div>
                <div class="card-body">
                    <form action="process_broadcast.php" method="POST">
                        <input type="text" name="filename" class="form-control mb-2" placeholder="/song.mp3" required>
                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="play" class="btn btn-danger">สั่งเล่นทุกเครื่อง</button>
                            <button type="submit" name="action" value="stop" class="btn btn-dark">หยุดทั้งหมด</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function playFile(ip, port, selectId) {
    var file = document.getElementById(selectId).value;
    var path = file.startsWith('/') ? file : '/' + file;
    window.location.href = `process_broadcast.php?action=play_single&ip=${ip}&port=${port}&file=${encodeURIComponent(path)}`;
}

function stopFile(ip, port) {
    window.location.href = `process_broadcast.php?action=stop_single&ip=${ip}&port=${port}`;
}

function updateStatuses() {
    <?php foreach ($nodes as $node): ?>
    fetch('get_node_status.php?id=<?= $node['id'] ?>')
        .then(res => res.json())
        .then(data => {
            document.getElementById('online-<?= $node['id'] ?>').innerHTML = data.online ? '<span class="badge bg-success">Online</span>' : '<span class="badge bg-danger">Offline</span>';
            document.getElementById('song-<?= $node['id'] ?>').innerHTML = (data.song && data.song !== "None") ? '<span class="text-primary fw-bold">▶️ ' + data.song + '</span>' : '<span class="text-muted small">⏹️ สแตนด์บาย</span>';
        });
    <?php endforeach; ?>
}
updateStatuses();
setInterval(updateStatuses, 5000);
</script>
</body>
</html>