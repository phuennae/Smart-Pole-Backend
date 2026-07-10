<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM nodes ORDER BY name ASC");
$nodes = $stmt->fetchAll();
$total_nodes = count($nodes);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Pole - Control Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: 'Sarabun', sans-serif; }
        .welcome-card {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white; border-radius: 15px; padding: 30px; margin-bottom: 30px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 20px; border: none;
            transition: 0.3s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .icon-box {
            width: 45px; height: 45px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; margin-bottom: 15px;
        }
        .node-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .dot-online { background-color: #22c55e; box-shadow: 0 0 8px #22c55e; }
        .dot-offline { background-color: #ef4444; }
        .progress { height: 6px; border-radius: 3px; display: none; margin-top: 8px; }
        .node-row.selected { background-color: #f0f7ff; }
        .bulk-action-bar { 
            background: #fff; border-radius: 12px; padding: 15px; 
            margin-bottom: 20px; display: none; border: 2px solid #3b82f6;
            position: sticky; top: 10px; z-index: 100;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4 mb-5">
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="fw-bold">Control Center</h1>
                <p class="lead mb-0">ระบบจัดการโหนดอัจฉริยะ (All-in-One Dashboard)</p>
            </div>
        </div>
    </div>

    <div id="bulkBar" class="bulk-action-bar shadow-lg">
        <div class="row align-items-center">
            <div class="col-md-4">
                <h6 class="mb-0 fw-bold text-primary">
                    <i class="fas fa-check-square me-2"></i>เลือกแล้ว <span id="selectedCount">0</span> โหนด
                </h6>
            </div>
            <div class="col-md-8 text-md-end">
                <div class="input-group input-group-sm d-inline-flex w-auto me-2">
                    <input type="text" id="bulkFileName" class="form-control" placeholder="ชื่อไฟล์ เช่น /music.mp3">
                    <button class="btn btn-primary" onclick="bulkPlay()">
                        <i class="fas fa-play me-1"></i> เล่นไฟล์นี้
                    </button>
                </div>
                <button class="btn btn-sm btn-warning fw-bold me-1" onclick="bulkAlarm()">
                    <i class="fas fa-exclamation-triangle me-1"></i> Alarm กลุ่ม
                </button>
                <button class="btn btn-sm btn-dark fw-bold" onclick="bulkStop()">
                    <i class="fas fa-stop me-1"></i> หยุดกลุ่ม
                </button>
                <button class="btn btn-sm btn-link text-secondary" onclick="deselectAll()">ยกเลิก</button>
            </div>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon-box bg-primary text-white"><i class="fas fa-tower-cell"></i></div>
                <h4 class="fw-bold mb-1"><?= $total_nodes ?></h4>
                <p class="text-muted small mb-0">โหนดทั้งหมด</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <a href="live.php" class="text-decoration-none">
                <div class="stat-card">
                    <div class="icon-box bg-danger text-white"><i class="fas fa-microphone"></i></div>
                    <h4 class="fw-bold mb-1 text-dark">Live</h4>
                    <p class="text-muted small mb-0">ประกาศสด</p>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <button onclick="sendAlarmBroadcast()" class="btn p-0 w-100 text-start border-0">
                <div class="stat-card">
                    <div class="icon-box bg-warning text-dark"><i class="fas fa-bell"></i></div>
                    <h4 class="fw-bold mb-1 text-dark">All Alarm</h4>
                    <p class="text-muted small mb-0">เตือนทุกจุด</p>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-3">
            <button onclick="stopAllBroadcast()" class="btn p-0 w-100 text-start border-0">
                <div class="stat-card">
                    <div class="icon-box bg-dark text-white"><i class="fas fa-stop"></i></div>
                    <h4 class="fw-bold mb-1 text-dark">All Stop</h4>
                    <p class="text-muted small mb-0">หยุดทุกจุด</p>
                </div>
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 15px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="nodeTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 50px;">
                                <input type="checkbox" class="form-check-input" id="selectAll" onclick="toggleSelectAll(this)">
                            </th>
                            <th style="width: 250px;">โหนด / สถานะ</th>
                            <th>จัดการไฟล์บน SD Card</th>
                            <th class="pe-4" style="width: 250px;">อัปโหลด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nodes as $node): $node_port = $node['port'] ?? 80; ?>
                        <tr class="node-row" id="row-<?= $node['id'] ?>">
                            <td class="ps-4">
                                <input type="checkbox" class="form-check-input node-checkbox" 
                                       value="<?= $node['id'] ?>" 
                                       data-ip="<?= $node['ip_address'] ?>" 
                                       data-port="<?= $node_port ?>"
                                       onclick="updateSelection()">
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($node['name']) ?></div>
                                <div id="status-<?= $node['id'] ?>" class="small">
                                    <span class="text-muted small">Checking...</span>
                                </div>
                            </td>
                            <td>
                                <div id="file-manager-<?= $node['id'] ?>">
                                    <div class="spinner-border spinner-border-sm text-primary opacity-25" role="status"></div>
                                </div>
                            </td>
                            <td class="pe-4">
                                <div class="input-group input-group-sm">
                                    <input type="file" id="input_<?= $node['id'] ?>" class="form-control">
                                    <button class="btn btn-outline-primary" type="button" 
                                        onclick="uploadToNode(<?= $node['id'] ?>, '<?= $node['ip_address'] ?>', <?= $node_port ?>)">
                                        <i class="fas fa-upload"></i>
                                    </button>
                                </div>
                                <div class="progress" id="pg_container_<?= $node['id'] ?>">
                                    <div id="bar_<?= $node['id'] ?>" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- ส่วนควบคุม Checkbox ---
function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.node-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
        const row = document.getElementById('row-' + cb.value);
        if(master.checked) row.classList.add('selected');
        else row.classList.remove('selected');
    });
    updateSelection();
}

function updateSelection() {
    const selected = document.querySelectorAll('.node-checkbox:checked');
    const bulkBar = document.getElementById('bulkBar');
    const countLabel = document.getElementById('selectedCount');
    
    document.querySelectorAll('.node-row').forEach(row => row.classList.remove('selected'));
    selected.forEach(cb => document.getElementById('row-' + cb.value).classList.add('selected'));

    if(selected.length > 0) {
        bulkBar.style.display = 'block';
        countLabel.innerText = selected.length;
    } else {
        bulkBar.style.display = 'none';
        document.getElementById('selectAll').checked = false;
    }
}

function deselectAll() {
    document.getElementById('selectAll').checked = false;
    toggleSelectAll(document.getElementById('selectAll'));
}

// --- ฟังก์ชันสั่งการแบบกลุ่ม (Bulk) ---
async function bulkPlay() {
    const fileName = document.getElementById('bulkFileName').value;
    if(!fileName) { alert("ระบุชื่อไฟล์ก่อนครับ"); return; }
    
    const selected = document.querySelectorAll('.node-checkbox:checked');
    const nodes = Array.from(selected).map(cb => ({ ip: cb.dataset.ip, port: cb.dataset.port }));
    
    const formData = new URLSearchParams();
    formData.append('action', 'play_selected');
    formData.append('nodes', JSON.stringify(nodes));
    formData.append('filename', fileName.startsWith('/') ? fileName : '/' + fileName);
    
    fetch('process_broadcast.php', { method: 'POST', body: formData });
    setTimeout(updateStatuses, 1000);
}

async function bulkAlarm() {
    if (!confirm("🚨 ยืนยันการส่งสัญญาณเตือนภัย (Alarm) ไปยังโหนดที่เลือก?")) return;

    const selected = document.querySelectorAll('.node-checkbox:checked');
    const nodes = Array.from(selected).map(cb => ({ ip: cb.dataset.ip, port: cb.dataset.port }));
    
    const formData = new URLSearchParams();
    formData.append('action', 'play_selected');
    formData.append('nodes', JSON.stringify(nodes));
    formData.append('filename', '/alarm007.mp3'); 
    
    fetch('process_broadcast.php', { method: 'POST', body: formData });
    setTimeout(updateStatuses, 1000);
}

async function bulkStop() {
    const selected = document.querySelectorAll('.node-checkbox:checked');
    const nodes = Array.from(selected).map(cb => ({ ip: cb.dataset.ip, port: cb.dataset.port }));
    
    const formData = new URLSearchParams();
    formData.append('action', 'stop_selected');
    formData.append('nodes', JSON.stringify(nodes));
    
    fetch('process_broadcast.php', { method: 'POST', body: formData });
    setTimeout(updateStatuses, 1000);
}

// --- ฟังก์ชันมาตรฐานสำหรับ Node เดียว ---
function loadAllNodeFiles() {
    <?php foreach ($nodes as $node): ?>
    fetch(`get_node_files.php?id=<?= $node['id'] ?>&ip=<?= $node['ip_address'] ?>&port=<?= $node['port'] ?? 80 ?>`)
        .then(res => res.text())
        .then(html => {
            document.getElementById('file-manager-<?= $node['id'] ?>').innerHTML = html;
        }).catch(err => console.log(err));
    <?php endforeach; ?>
}

function playFile(ip, port, selectId) {
    const file = document.getElementById(selectId).value;
    if(!file) return;
    fetch(`process_broadcast.php?action=play_single&ip=${ip}&port=${port}&file=${encodeURIComponent('/'+file)}`)
        .then(() => setTimeout(updateStatuses, 1000));
}

function stopFile(ip, port) {
    fetch(`process_broadcast.php?action=stop_single&ip=${ip}&port=${port}`)
        .then(() => setTimeout(updateStatuses, 1000));
}

function uploadToNode(nodeId, ip, port) {
    const fileInput = document.getElementById('input_' + nodeId);
    const file = fileInput.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('fileToUpload', file);
    const xhr = new XMLHttpRequest();
    const bar = document.getElementById('bar_' + nodeId);
    const container = document.getElementById('pg_container_' + nodeId);

    container.style.display = 'flex';
    xhr.upload.addEventListener("progress", (e) => {
        if (e.lengthComputable) {
            let percent = Math.round((e.loaded / e.total) * 100);
            bar.style.width = percent + '%';
        }
    });

    xhr.onreadystatechange = () => {
        if (xhr.readyState == 4 && xhr.status == 200) {
            setTimeout(() => {
                container.style.display = 'none';
                loadAllNodeFiles();
            }, 1000);
        }
    };
    xhr.open("POST", `process_broadcast.php?action=upload_single&ip=${ip}&port=${port}`, true);
    xhr.send(formData);
}

function updateStatuses() {
    <?php foreach ($nodes as $node): ?>
    fetch('get_node_status.php?id=<?= $node['id'] ?>')
        .then(res => res.json())
        .then(data => {
            const statusEl = document.getElementById('status-<?= $node['id'] ?>');
            let statusHtml = data.online ? 
                '<span class="node-dot dot-online"></span> <span class="text-success">Online</span>' : 
                '<span class="node-dot dot-offline"></span> <span class="text-danger">Offline</span>';
            
            if (data.song && data.song !== "None") {
                statusHtml += ` <span class="badge bg-info text-dark ms-2 fw-normal">▶️ ${data.song}</span>`;
            }
            statusEl.innerHTML = statusHtml;
        }).catch(() => {});
    <?php endforeach; ?>
}

// --- ส่วนสั่งการ All (Broadcast) ---
function sendAlarmBroadcast() { 
    if(confirm("🚨 ยืนยันยิงสัญญาณเตือนภัย 'ทุกเครื่อง' ในระบบ?")) {
        submitBroadcast('play', '/alarm007.mp3'); 
    }
}
function stopAllBroadcast() { 
    if(confirm("ยืนยันหยุดการทำงาน 'ทุกเครื่อง'?")) {
        submitBroadcast('stop', ''); 
    }
}
function submitBroadcast(action, filename) {
    const formData = new URLSearchParams();
    formData.append('action', action);
    formData.append('filename', filename);
    fetch('process_broadcast.php', { method: 'POST', body: formData }).then(() => setTimeout(updateStatuses, 1000));
}

document.addEventListener('DOMContentLoaded', () => {
    loadAllNodeFiles();
    updateStatuses();
    setInterval(updateStatuses, 10000);
});
</script>
</body>
</html>