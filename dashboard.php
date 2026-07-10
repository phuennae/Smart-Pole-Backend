<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

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
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Sarabun', sans-serif; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .btn-alarm { background-color: #ffc107; color: #000; font-weight: bold; border: 2px solid #dc3545; transition: 0.3s; }
        .btn-alarm:hover { background-color: #dc3545; color: #fff; }
        .btn-stop-all { background-color: #212529; color: #fff; font-weight: bold; border: 2px solid #000; }
        .btn-stop-all:hover { background-color: #000; color: #ff0000; }
        .progress { height: 10px; border-radius: 5px; display: none; margin-top: 8px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-microchip me-2"></i> รายชื่อ Node และการจัดการไฟล์</h5>
                    <span class="badge bg-light text-primary">จำนวน <?= count($nodes) ?> โหนด</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 30%;">ชื่อจุดติดตั้ง / สถานะ</th>
                                    <th style="width: 35%;">จัดการเพลงใน SD Card</th>
                                    <th style="width: 35%;">อัปโหลดไฟล์ใหม่</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nodes as $node): $node_port = $node['port'] ?? 80; ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark fs-5"><?= htmlspecialchars($node['name']) ?></div>
                                        <div id="online-text-<?= $node['id'] ?>">
                                            <small class="text-muted"><i class="fas fa-sync fa-spin me-1"></i> กำลังตรวจสอบ...</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div id="file-manager-<?= $node['id'] ?>">
                                            <div class="text-center py-1">
                                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="file" id="input_<?= $node['id'] ?>" class="form-control">
                                            <button class="btn btn-primary" type="button" 
                                                onclick="uploadToNode(<?= $node['id'] ?>, '<?= $node['ip_address'] ?>', <?= $node_port ?>)">
                                                <i class="fas fa-upload"></i>
                                            </button>
                                        </div>
                                        <div class="progress" id="pg_container_<?= $node['id'] ?>">
                                            <div id="bar_<?= $node['id'] ?>" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small id="status_<?= $node['id'] ?>" class="text-muted d-block mt-1" style="font-size: 11px;"></small>
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
                    <h5 class="mb-0 text-dark"><i class="fas fa-signal me-2 text-success"></i>สถานะการเล่นปัจจุบัน</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Node</th>
                                <th>การเชื่อมต่อ</th>
                                <th>สถานะการเล่น</th>
                            </tr>
                        </thead>
                        <tbody id="status-table-body">
                            <?php foreach ($nodes as $node): ?>
                            <tr>
                                <td class="ps-3"><?= htmlspecialchars($node['name']) ?></td>
                                <td id="online-badge-<?= $node['id'] ?>">
                                    <span class="badge bg-secondary">Checking...</span>
                                </td>
                                <td id="song-<?= $node['id'] ?>">-</td>
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
                    <h5 class="mb-0">🚨 Emergency Control</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2 mb-3">
                        <button onclick="sendAlarmBroadcast()" class="btn btn-alarm py-2">
                            <i class="fas fa-exclamation-triangle me-2"></i> สั่งสัญญาณเตือนทุกเครื่อง
                        </button>
                        <button onclick="stopAllBroadcast()" class="btn btn-stop-all py-2">
                            <i class="fas fa-hand-paper me-2"></i> หยุดการทำงานทั้งหมด
                        </button>
                    </div>
                    <hr>
                    <a href="live.php" class="btn btn-primary w-100 py-2 fw-bold">
                        <i class="fas fa-microphone me-2"></i> ไปหน้าประกาศเสียงสด
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// 1. โหลดรายชื่อเพลงแยก Node
function loadAllNodeFiles() {
    <?php foreach ($nodes as $node): ?>
    fetch(`get_node_files.php?id=<?= $node['id'] ?>&ip=<?= $node['ip_address'] ?>&port=<?= $node['port'] ?? 80 ?>`)
        .then(res => res.text())
        .then(html => {
            document.getElementById('file-manager-<?= $node['id'] ?>').innerHTML = html;
        }).catch(err => console.log(err));
    <?php endforeach; ?>
}

// 2. ฟังก์ชันควบคุมเพลง
function playFile(ip, port, selectId) {
    var file = document.getElementById(selectId).value;
    if(!file) return;
    window.location.href = `process_broadcast.php?action=play_single&ip=${ip}&port=${port}&file=${encodeURIComponent('/'+file)}`;
}

function stopFile(ip, port) {
    window.location.href = `process_broadcast.php?action=stop_single&ip=${ip}&port=${port}`;
}

// 3. ฟังก์ชันอัปโหลด
function uploadToNode(nodeId, ip, port) {
    var fileInput = document.getElementById('input_' + nodeId);
    var file = fileInput.files[0];
    if (!file) { alert("กรุณาเลือกไฟล์"); return; }

    var formData = new FormData();
    formData.append('fileToUpload', file);
    var xhr = new XMLHttpRequest();
    var bar = document.getElementById('bar_' + nodeId);
    var container = document.getElementById('pg_container_' + nodeId);
    var statusText = document.getElementById('status_' + nodeId);

    container.style.display = 'flex';
    statusText.innerText = "กำลังอัปโหลด...";

    xhr.upload.addEventListener("progress", function(e) {
        if (e.lengthComputable) {
            var percent = Math.round((e.loaded / e.total) * 100);
            bar.style.width = percent + '%';
            statusText.innerText = "ส่งข้อมูล: " + percent + "%";
        }
    });

    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) {
            if (xhr.status == 200) {
                statusText.className = "text-success fw-bold";
                statusText.innerText = "✅ สำเร็จ!";
                setTimeout(() => {
                    container.style.display = 'none';
                    statusText.innerText = "";
                    loadAllNodeFiles();
                }, 2000);
            } else {
                statusText.className = "text-danger fw-bold";
                statusText.innerText = "❌ ล้มเหลว";
            }
        }
    };
    xhr.open("POST", `process_broadcast.php?action=upload_single&ip=${ip}&port=${port}`, true);
    xhr.send(formData);
}

// 4. ฟังก์ชันสถานะ Real-time
function updateStatuses() {
    <?php foreach ($nodes as $node): ?>
    fetch('get_node_status.php?id=<?= $node['id'] ?>')
        .then(res => res.json())
        .then(data => {
            // อัปเดตข้อความสถานะใต้ชื่อโหนด
            const statusLabel = data.online ? 
                '<span class="text-success small fw-bold"><i class="fas fa-check-circle"></i> Online</span>' : 
                '<span class="text-danger small fw-bold"><i class="fas fa-times-circle"></i> Offline</span>';
            document.getElementById('online-text-<?= $node['id'] ?>').innerHTML = statusLabel;

            // อัปเดต Badge ในตารางสรุป
            const badge = data.online ? 
                '<span class="badge bg-success">Online</span>' : 
                '<span class="badge bg-danger">Offline</span>';
            document.getElementById('online-badge-<?= $node['id'] ?>').innerHTML = badge;

            // อัปเดตชื่อเพลงที่เล่นอยู่
            const songName = (data.song && data.song !== "None") ? 
                '<span class="text-primary fw-bold"><i class="fas fa-play-circle"></i> ' + data.song + '</span>' : 
                '<span class="text-muted small">Standby</span>';
            document.getElementById('song-<?= $node['id'] ?>').innerHTML = songName;
        }).catch(() => {});
    <?php endforeach; ?>
}

// 5. Broadcast Control
function sendAlarmBroadcast() { if (confirm("ยืนยันส่งสัญญาณเตือนภัยทุกเครื่อง?")) submitBroadcast('play', '/alarm007.mp3'); }
function stopAllBroadcast() { if (confirm("ยืนยันหยุดการทำงานทุกเครื่อง?")) submitBroadcast('stop', ''); }
function submitBroadcast(action, filename) {
    const form = document.createElement('form');
    form.method = 'POST'; form.action = 'process_broadcast.php';
    const aI = document.createElement('input'); aI.type = 'hidden'; aI.name = 'action'; aI.value = action;
    const fI = document.createElement('input'); fI.type = 'hidden'; fI.name = 'filename'; fI.value = filename;
    form.appendChild(aI); form.appendChild(fI); document.body.appendChild(form); form.submit();
}

document.addEventListener('DOMContentLoaded', () => {
    loadAllNodeFiles();
    updateStatuses();
    setInterval(updateStatuses, 5000);
});
</script>
</body>
</html>