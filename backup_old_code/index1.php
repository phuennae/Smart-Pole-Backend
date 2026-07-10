<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$stmt = $pdo->query("SELECT * FROM nodes ORDER BY name ASC");
$nodes = $stmt->fetchAll();
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
        .welcome-card { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; border-radius: 15px; padding: 30px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; border: none; transition: 0.3s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); height: 100%; }
        .icon-box { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 15px; }
        .node-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .dot-online { background-color: #22c55e; box-shadow: 0 0 8px #22c55e; }
        .dot-offline { background-color: #ef4444; }
        .vol-slider { cursor: pointer; height: 6px; }
        .vol-value { font-size: 11px; font-weight: bold; color: #64748b; min-width: 35px; text-align: right; }
        .node-row.selected { background-color: #f0f7ff !important; }
        .bulk-action-bar { background: #fff; border-radius: 12px; padding: 15px; margin-bottom: 20px; display: none; border: 2px solid #3b82f6; position: sticky; top: 10px; z-index: 100; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4 mb-5">
    <div class="welcome-card shadow-sm">
        <h2 class="fw-bold mb-1">Smart Pole Management</h2>
        <p class="mb-0 opacity-75">Smart Sound Online : ระบบเสียงตามสายอัจฉริยะ</p>
    </div>

    <div id="bulkBar" class="bulk-action-bar">
        <div class="row align-items-center g-2">
            <div class="col-md-4">
                <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-check-square me-2"></i>เลือกแล้ว <span id="selectedCount">0</span> โหนด</h6>
            </div>
            <div class="col-md-8 text-md-end">
                <div class="input-group input-group-sm d-inline-flex w-auto me-2">
                    <input type="text" id="bulkFileName" class="form-control" placeholder="/music.mp3">
                    <button class="btn btn-primary" onclick="bulkPlay()"><i class="fas fa-play"></i> เล่นกลุ่ม</button>
                </div>
                <button class="btn btn-sm btn-warning fw-bold me-1" onclick="bulkAlarm()"><i class="fas fa-bell"></i> Alarm กลุ่ม</button>
                <button class="btn btn-sm btn-dark fw-bold me-1" onclick="bulkStop()"><i class="fas fa-stop"></i> หยุดกลุ่ม</button>
                <button class="btn btn-sm btn-link text-secondary text-decoration-none" onclick="deselectAll()">ยกเลิก</button>
            </div>
        </div>
    </div>

    <div class="row mb-4 g-3 text-center">
        <div class="col-4"><a href="live.php" class="text-decoration-none h-100 d-block"><div class="stat-card"><div class="icon-box bg-danger text-white mx-auto"><i class="fas fa-microphone"></i></div><p class="small fw-bold mb-0 text-dark">Live Stream</p></div></a></div>
        <div class="col-4"><button onclick="sendAlarmBroadcast()" class="btn p-0 w-100 h-100 border-0"><div class="stat-card"><div class="icon-box bg-warning text-dark mx-auto"><i class="fas fa-bell"></i></div><p class="small fw-bold mb-0 text-dark">All Alarm</p></div></button></div>
        <div class="col-4"><button onclick="stopAllBroadcast()" class="btn p-0 w-100 h-100 border-0"><div class="stat-card bg-dark text-white"><div class="icon-box bg-secondary text-white mx-auto"><i class="fas fa-stop"></i></div><p class="small fw-bold mb-0">All Stop</p></div></button></div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 15px; overflow: hidden;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 50px;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                        <th style="min-width: 250px;">โหนด / ระดับเสียง / ตั้งเวลา</th>
                        <th>รายการเพลง & SD Card</th>
                        <th class="pe-4" style="width: 200px;">อัปโหลด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nodes as $node): 
                        $node_port = $node['port'] ?? 80;
                        $db_vol = $node['last_volume'] ?? 11;
                        $vol_percent = round(($db_vol * 100) / 21);
                    ?>
                    <tr class="node-row" id="row-<?= $node['id'] ?>">
                        <td class="ps-4">
                            <input type="checkbox" class="form-check-input node-checkbox" 
                                   value="<?= $node['id'] ?>" 
                                   data-ip="<?= $node['ip_address'] ?>" 
                                   data-port="<?= $node_port ?>" 
                                   onclick="updateSelection()">
                        </td>
                        <td>
                            <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($node['name']) ?></div>
                            <div id="status-<?= $node['id'] ?>" class="small mb-2 text-muted">Checking...</div>
                            <div class="d-flex align-items-center mb-2" style="max-width: 200px;">
                                <i class="fas fa-volume-low me-2 text-muted small"></i>
                                <input type="range" class="form-range vol-slider me-2" 
                                       min="0" max="100" value="<?= $vol_percent ?>" 
                                       oninput="this.nextElementSibling.innerText = this.value + '%'"
                                       onchange="setSingleVolume(<?= $node['id'] ?>, '<?= $node['ip_address'] ?>', <?= $node_port ?>, this.value)">
                                <span class="vol-value"><?= $vol_percent ?>%</span>
                            </div>
                            <button class="btn btn-sm btn-outline-primary py-0" style="font-size: 11px;" 
                                    onclick="openScheduleModal(<?= $node['id'] ?>, '<?= $node['name'] ?>', '<?= $node['ip_address'] ?>', <?= $node_port ?>)">
                                <i class="fas fa-clock"></i> ตั้งเวลาเล่น
                            </button>
                        </td>
                        <td><div id="file-manager-<?= $node['id'] ?>"><div class="spinner-border spinner-border-sm text-primary opacity-25"></div></div></td>
                        <td class="pe-4">
                            <div id="upload_area_<?= $node['id'] ?>">
                                <div class="input-group input-group-sm">
                                    <input type="file" id="input_<?= $node['id'] ?>" class="form-control">
                                    <button class="btn btn-primary" onclick="uploadToNode(<?= $node['id'] ?>, '<?= $node['ip_address'] ?>', <?= $node_port ?>)">
                                        <i class="fas fa-upload"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="up_loading_<?= $node['id'] ?>" style="display:none;" class="text-primary fw-bold small">
                                <i class="fas fa-spinner fa-spin"></i> กำลังอัปโหลด...
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-alt me-2"></i>ตารางเวลา: <span id="modalNodeName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3 p-3 bg-light rounded">
                    <div class="col-md-3">
                        <select id="sch_day" class="form-select form-select-sm">
                            <option value="7">ทุกวัน</option><option value="1">จันทร์</option><option value="2">อังคาร</option>
                            <option value="3">พุธ</option><option value="4">พฤหัสบดี</option><option value="5">ศุกร์</option>
                            <option value="6">เสาร์</option><option value="0">อาทิตย์</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="sch_hr" class="form-select form-select-sm">
                            <?php for($i=0;$i<24;$i++) printf("<option value='%d'>%02d</option>", $i, $i); ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="sch_mn" class="form-select form-select-sm">
                            <?php for($i=0;$i<60;$i++) printf("<option value='%d'>%02d</option>", $i, $i); ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="sch_file" class="form-select form-select-sm"><option value="">เลือกไฟล์...</option></select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-success btn-sm w-100" onclick="addSchedule()">เพิ่ม</button>
                    </div>
                </div>
                <div id="scheduleTableContainer"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentNode = {};

function openScheduleModal(id, name, ip, port) {
    currentNode = { id, name, ip, port };
    document.getElementById('modalNodeName').innerText = name;
    loadSchedules();
    loadFileListForSelect(id);
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function loadSchedules() {
    fetch(`proxy_scheduler.php?action=list&node_id=${currentNode.id}`)
        .then(res => res.text()).then(html => { document.getElementById('scheduleTableContainer').innerHTML = html; });
}

function loadFileListForSelect(nodeId) {
    const fileSelect = document.getElementById('sch_file');
    const nodeFileSelect = document.querySelector(`#file-manager-${nodeId} select`);
    if(nodeFileSelect) { fileSelect.innerHTML = nodeFileSelect.innerHTML; }
}

function addSchedule() {
    const data = {
        action: 'add', node_id: currentNode.id, ip: currentNode.ip, port: currentNode.port,
        day: document.getElementById('sch_day').value, hr: document.getElementById('sch_hr').value,
        mn: document.getElementById('sch_mn').value, file: document.getElementById('sch_file').value
    };
    if(!data.file) return alert("กรุณาเลือกไฟล์");
    fetch('proxy_scheduler.php', { method: 'POST', body: new URLSearchParams(data) }).then(() => loadSchedules());
}

function toggleSched(id, activeStatus) {
    fetch(`proxy_scheduler.php?action=toggle&id=${id}&active=${activeStatus}&node_id=${currentNode.id}&ip=${currentNode.ip}&port=${currentNode.port}`)
        .then(() => loadSchedules());
}

function deleteSched(id) {
    if(!confirm("ยืนยันการลบตารางเวลานี้?")) return;
    fetch(`proxy_scheduler.php?action=delete&id=${id}&node_id=${currentNode.id}&ip=${currentNode.ip}&port=${currentNode.port}`)
        .then(() => loadSchedules());
}

function confirmDeleteFile(ip, port, selectId) {
    const el = document.getElementById(selectId);
    if (!el) return;
    const fileName = el.value;
    const nodeId = selectId.split('_')[1];
    if (!fileName) return alert("กรุณาเลือกไฟล์ที่จะลบ");

    if (confirm(`⚠️ ยืนยันการลบไฟล์ "${fileName}"?\n*รายการในตารางเวลาจะถูกลบออกด้วย*`)) {
        const filePath = fileName.startsWith('/') ? fileName : '/' + fileName;
        fetch(`http://${ip}:${port}/delete_web?file=${encodeURIComponent(filePath)}`, { mode: 'no-cors' }).then(() => {
            const formData = new URLSearchParams();
            formData.append('action', 'delete_by_file');
            formData.append('node_id', nodeId);
            formData.append('file', fileName);
            fetch('proxy_scheduler.php', { method: 'POST', body: formData });
            document.getElementById('file-manager-' + nodeId).innerHTML = '<div class="spinner-border spinner-border-sm text-danger"></div>';
            setTimeout(() => { loadAllNodeFiles(); alert("ลบสำเร็จ"); }, 2000); 
        });
    }
}

function setSingleVolume(nodeId, ip, port, volPercent) {
    const espVol = Math.round((volPercent * 21) / 100);
    fetch(`process_broadcast.php?action=vol_single&id=${nodeId}&ip=${ip}&port=${port}&v=${espVol}`);
}

function stopFile(ip, port) {
    fetch(`process_broadcast.php?action=stop_single&ip=${ip}&port=${port}`).then(() => updateStatuses());
}

function playFile(ip, port, selectId) {
    const file = document.getElementById(selectId).value;
    if(!file) return;
    fetch(`process_broadcast.php?action=play_single&ip=${ip}&port=${port}&file=${encodeURIComponent('/'+file)}`).then(() => setTimeout(updateStatuses, 1000));
}

// --- ส่วนที่แก้ไขตามโจทย์ (แสดงสัญลักษณ์แล้วเอาออก) ---
function uploadToNode(id, ip, port) {
    const fileInput = document.getElementById('input_' + id);
    const area = document.getElementById('upload_area_' + id);
    const loading = document.getElementById('up_loading_' + id);

    if (!fileInput.files[0]) return alert("เลือกไฟล์ก่อน");

    // 1. ซ่อนช่องอัปโหลด และแสดงสัญลักษณ์หมุนๆ แทน
    area.style.display = 'none';
    loading.style.display = 'block';

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', `http://${ip}:${port}/upload`, true);

    xhr.onload = function() {
        // 2. เมื่อเสร็จ (ไม่ว่าจะสำเร็จหรือ error) ให้เอาสัญลักษณ์ออกและโชว์ช่องอัปโหลดเหมือนเดิม
        loading.style.display = 'none';
        area.style.display = 'block';
        fileInput.value = ''; 
        
        // Popup เดิม
        alert("อัปโหลดสำเร็จ"); 
        loadAllNodeFiles();
    };

    xhr.onerror = function() {
        loading.style.display = 'none';
        area.style.display = 'block';
        alert("การเชื่อมต่อผิดพลาด");
		loadAllNodeFiles();
    };

    xhr.send(formData);
}

function toggleSelectAll(master) {
    document.querySelectorAll('.node-checkbox').forEach(cb => {
        cb.checked = master.checked;
        document.getElementById('row-' + cb.value).classList.toggle('selected', master.checked);
    });
    updateSelection();
}

function updateSelection() {
    const selected = document.querySelectorAll('.node-checkbox:checked');
    document.getElementById('bulkBar').style.display = selected.length > 0 ? 'block' : 'none';
    document.getElementById('selectedCount').innerText = selected.length;
}

function deselectAll() { 
    document.getElementById('selectAll').checked = false; 
    toggleSelectAll(document.getElementById('selectAll')); 
}

function bulkPlay() {
    const fileName = document.getElementById('bulkFileName').value;
    if(!fileName) return alert("ระบุชื่อไฟล์");
    const selected = document.querySelectorAll('.node-checkbox:checked');
    const nodes = Array.from(selected).map(cb => ({ ip: cb.dataset.ip, port: cb.dataset.port }));
    submitBulk('play_selected', { nodes: JSON.stringify(nodes), filename: fileName.startsWith('/') ? fileName : '/' + fileName });
}

function bulkStop() {
    const selected = document.querySelectorAll('.node-checkbox:checked');
    const nodes = Array.from(selected).map(cb => ({ ip: cb.dataset.ip, port: cb.dataset.port }));
    submitBulk('stop_selected', { nodes: JSON.stringify(nodes) });
}

function submitBulk(action, params) {
    const formData = new URLSearchParams();
    formData.append('action', action);
    for (const key in params) formData.append(key, params[key]);
    fetch('process_broadcast.php', { method: 'POST', body: formData }).then(() => setTimeout(updateStatuses, 1000));
}

function updateStatuses() {
    <?php foreach ($nodes as $node): ?>
    fetch('get_node_status.php?id=<?= $node['id'] ?>')
        .then(res => res.json())
        .then(data => {
            const row = document.getElementById('row-<?= $node['id'] ?>');
            const nameEl = row.querySelector('.fw-bold.text-dark');
            const statusEl = document.getElementById('status-<?= $node['id'] ?>');
            if (data.online) {
                let colorClass = (data.volt_pct <= 25) ? 'bg-danger' : (data.volt_pct <= 50) ? 'bg-warning text-dark' : 'bg-success';
                const voltBadge = `<span class="badge ${colorClass} ms-2 shadow-sm" style="font-size: 0.7rem; font-weight: normal;"><i class="fas fa-battery-three-quarters"></i> ${data.volt}V (${data.volt_pct}%)</span>`;
                nameEl.innerHTML = "<?= htmlspecialchars($node['name']) ?>" + voltBadge;
                let html = '<span class="node-dot dot-online"></span> <span class="text-success small fw-bold">Online</span>';
                if (data.song && data.song !== "") html += ` <span class="badge bg-info text-dark ms-2 fw-normal">▶️ ${data.song}</span>`;
                statusEl.innerHTML = html;
            } else {
                statusEl.innerHTML = '<span class="node-dot dot-offline"></span> <span class="text-danger small fw-bold">Offline</span>';
            }
        });
    <?php endforeach; ?>
}

function loadAllNodeFiles() {
    <?php foreach ($nodes as $node): ?>
    fetch(`get_node_files.php?id=<?= $node['id'] ?>&ip=<?= $node['ip_address'] ?>&port=<?= $node['port'] ?? 80 ?>`)
        .then(res => res.text()).then(html => { document.getElementById('file-manager-<?= $node['id'] ?>').innerHTML = html; });
    <?php endforeach; ?>
}

function sendAlarmBroadcast() { if(confirm("🚨 Alarm ทุกเครื่อง?")) submitBulk('play', { filename: '/alarm007.mp3' }); }
function stopAllBroadcast() { if(confirm("หยุดทุกเครื่อง?")) submitBulk('stop', {}); }

document.addEventListener('DOMContentLoaded', () => { loadAllNodeFiles(); updateStatuses(); setInterval(updateStatuses, 10000); });
</script>
</body>
</html>