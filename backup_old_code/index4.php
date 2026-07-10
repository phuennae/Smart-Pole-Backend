<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM nodes ORDER BY name ASC");
$nodes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Smart Pole Control</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">

<style>

body{
    background:#eef3f9;
    font-family:'Sarabun',sans-serif;
    color:#1e293b;
}

/* HEADER */

.app-header{
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    border-radius:24px;
    padding:28px;
    color:#fff;
    margin-bottom:24px;
    box-shadow:0 10px 30px rgba(37,99,235,.25);
}

.app-header h2{
    font-weight:700;
    margin:0;
}

.app-header p{
    opacity:.85;
    margin:0;
}

/* QUICK MENU */

.quick-btn{
    background:#fff;
    border:none;
    border-radius:22px;
    padding:18px 10px;
    width:100%;
    box-shadow:0 5px 15px rgba(0,0,0,.06);
    transition:.2s;
}

.quick-btn:hover{
    transform:translateY(-2px);
}

.quick-icon{
    width:52px;
    height:52px;
    border-radius:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:auto auto 10px;
    font-size:20px;
    color:#fff;
}

.icon-live{background:#ef4444;}
.icon-alarm{background:#f59e0b;}
.icon-stop{background:#111827;}

.quick-title{
    font-size:13px;
    font-weight:700;
    color:#0f172a;
}

/* BULK BAR */

.bulk-action-bar{
    display:none;
    position:sticky;
    top:10px;
    z-index:100;
    background:#fff;
    border-radius:20px;
    padding:16px;
    margin-bottom:20px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}

/* NODE CARD */

.node-card{
    background:#fff;
    border-radius:24px;
    padding:18px;
    margin-bottom:18px;
    box-shadow:0 5px 20px rgba(0,0,0,.05);
    transition:.2s;
}

.node-card:hover{
    transform:translateY(-2px);
}

.node-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    margin-bottom:14px;
}

.node-title{
    font-size:18px;
    font-weight:700;
    color:#0f172a;
}

.status-online{
    color:#16a34a;
    font-size:13px;
    font-weight:700;
}

.status-offline{
    color:#ef4444;
    font-size:13px;
    font-weight:700;
}

.node-dot{
    width:10px;
    height:10px;
    border-radius:50%;
    display:inline-block;
    margin-right:6px;
}

.dot-online{
    background:#22c55e;
    box-shadow:0 0 10px #22c55e;
}

.dot-offline{
    background:#ef4444;
}

.vol-wrap{
    background:#f8fafc;
    border-radius:16px;
    padding:10px 14px;
    margin-top:10px;
}

.vol-top{
    display:flex;
    justify-content:space-between;
    margin-bottom:5px;
    font-size:12px;
    color:#64748b;
}

.form-range{
    height:6px;
}

.badge-batt{
    font-size:11px;
    border-radius:10px;
    padding:6px 10px;
}

.file-box{
    background:#f8fafc;
    border-radius:16px;
    padding:14px;
    margin-top:14px;
}

.upload-box{
    margin-top:12px;
}

.schedule-btn{
    border-radius:14px;
    font-size:13px;
    font-weight:600;
}

/* MODAL */

.modal-content{
    border:none;
    border-radius:24px;
    overflow:hidden;
}

.modal-header{
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:#fff;
    border:none;
}

.modal-body{
    background:#f8fafc;
}

.schedule-add-box{
    background:#fff;
    border-radius:18px;
    padding:14px;
}

/* MOBILE */

@media(max-width:768px){

    .app-header{
        padding:22px;
        border-radius:20px;
    }

    .node-card{
        padding:16px;
        border-radius:20px;
    }

}

</style>
</head>

<body>

<?php include 'navbar.php'; ?>

<div class="container py-4">

    <!-- HEADER -->

    <div class="app-header">
        <h2>Smart Pole</h2>
        <p>Smart Sound Online Control Center</p>
    </div>

    <!-- BULK -->

    <div id="bulkBar" class="bulk-action-bar">

        <div class="row g-2 align-items-center">

            <div class="col-md-4">
                <div class="fw-bold text-primary">
                    <i class="fas fa-check-circle me-2"></i>
                    เลือกแล้ว <span id="selectedCount">0</span> โหนด
                </div>
            </div>

            <div class="col-md-8">

                <div class="d-flex flex-wrap gap-2 justify-content-md-end">

                    <input type="text"
                           id="bulkFileName"
                           class="form-control form-control-sm"
                           style="max-width:180px"
                           placeholder="/music.mp3">

                    <button class="btn btn-primary btn-sm rounded-pill px-3"
                            onclick="bulkPlay()">
                        <i class="fas fa-play me-1"></i>
                        เล่น
                    </button>

                    <button class="btn btn-warning btn-sm rounded-pill px-3"
                            onclick="sendAlarmBroadcast()">
                        <i class="fas fa-bell me-1"></i>
                        Alarm
                    </button>

                    <button class="btn btn-dark btn-sm rounded-pill px-3"
                            onclick="bulkStop()">
                        <i class="fas fa-stop me-1"></i>
                        Stop
                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- QUICK -->

    <div class="row g-3 mb-4">

        <div class="col-4">

            <a href="live.php" class="text-decoration-none">

                <button class="quick-btn">

                    <div class="quick-icon icon-live">
                        <i class="fas fa-microphone"></i>
                    </div>

                    <div class="quick-title">
                        Live
                    </div>

                </button>

            </a>

        </div>

        <div class="col-4">

            <button onclick="sendAlarmBroadcast()"
                    class="quick-btn">

                <div class="quick-icon icon-alarm">
                    <i class="fas fa-bell"></i>
                </div>

                <div class="quick-title">
                    Alarm
                </div>

            </button>

        </div>

        <div class="col-4">

            <button onclick="stopAllBroadcast()"
                    class="quick-btn">

                <div class="quick-icon icon-stop">
                    <i class="fas fa-stop"></i>
                </div>

                <div class="quick-title">
                    Stop
                </div>

            </button>

        </div>

    </div>

    <!-- NODE LIST -->

    <?php foreach($nodes as $node):

        $node_port = $node['port'] ?? 80;

        $db_vol = $node['last_volume'] ?? 11;

        $vol_percent = round(($db_vol * 100) / 21);

    ?>

    <div class="node-card" id="row-<?= $node['id'] ?>">

        <div class="node-top">

            <div>

                <div class="d-flex align-items-center gap-2">

                    <input type="checkbox"
                           class="form-check-input node-checkbox"
                           value="<?= $node['id'] ?>"
                           data-ip="<?= $node['ip_address'] ?>"
                           data-port="<?= $node_port ?>"
                           onclick="updateSelection()">

                    <div class="node-title">
                        <?= htmlspecialchars($node['name']) ?>
                    </div>

                </div>

                <div id="status-<?= $node['id'] ?>"
                     class="mt-1 small text-muted">

                    Checking...

                </div>

            </div>

            <button class="btn btn-outline-primary btn-sm schedule-btn"
                    onclick="openScheduleModal(
                        <?= $node['id'] ?>,
                        '<?= $node['name'] ?>',
                        '<?= $node['ip_address'] ?>',
                        <?= $node_port ?>
                    )">

                <i class="fas fa-clock me-1"></i>
                Schedule

            </button>

        </div>

        <!-- VOL -->

        <div class="vol-wrap">

            <div class="vol-top">

                <div>
                    <i class="fas fa-volume-high me-1"></i>
                    Volume
                </div>

                <div id="volLabel<?= $node['id'] ?>">
                    <?= $vol_percent ?>%
                </div>

            </div>

            <input type="range"
                   class="form-range"
                   min="0"
                   max="100"
                   value="<?= $vol_percent ?>"
                   oninput="document.getElementById('volLabel<?= $node['id'] ?>').innerText=this.value+'%'"
                   onchange="setSingleVolume(
                       <?= $node['id'] ?>,
                       '<?= $node['ip_address'] ?>',
                       <?= $node_port ?>,
                       this.value
                   )">

        </div>

        <!-- FILE -->

        <div class="file-box">

            <div id="file-manager-<?= $node['id'] ?>">

                <div class="spinner-border spinner-border-sm text-primary"></div>

            </div>

            <!-- UPLOAD -->

            <div class="upload-box">

                <div id="upload_area_<?= $node['id'] ?>">

                    <div class="input-group">

                        <input type="file"
                               id="input_<?= $node['id'] ?>"
                               class="form-control">

                        <button class="btn btn-primary"
                                onclick="uploadToNode(
                                    <?= $node['id'] ?>,
                                    '<?= $node['ip_address'] ?>',
                                    <?= $node_port ?>
                                )">

                            <i class="fas fa-upload"></i>

                        </button>

                    </div>

                </div>

                <div id="up_loading_<?= $node['id'] ?>"
                     style="display:none"
                     class="text-primary small fw-bold mt-2">

                    <i class="fas fa-spinner fa-spin me-1"></i>
                    Uploading...

                </div>

            </div>

        </div>

    </div>

    <?php endforeach; ?>

</div>

<!-- MODAL -->

<div class="modal fade" id="scheduleModal" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    <i class="fas fa-calendar-alt me-2"></i>

                    Schedule :
                    <span id="modalNodeName"></span>

                </h5>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body">

                <div class="schedule-add-box mb-3">

                    <div class="row g-2">

                        <div class="col-md-3">

                            <select id="sch_day"
                                    class="form-select">

                                <option value="7">ทุกวัน</option>
                                <option value="1">จันทร์</option>
                                <option value="2">อังคาร</option>
                                <option value="3">พุธ</option>
                                <option value="4">พฤหัส</option>
                                <option value="5">ศุกร์</option>
                                <option value="6">เสาร์</option>
                                <option value="0">อาทิตย์</option>

                            </select>

                        </div>

                        <div class="col-3 col-md-1">

                            <select id="sch_hr"
                                    class="form-select">

                                <?php
                                for($i=0;$i<24;$i++)
                                    printf(
                                        "<option value='%d'>%02d</option>",
                                        $i,
                                        $i
                                    );
                                ?>

                            </select>

                        </div>

                        <div class="col-3 col-md-1">

                            <select id="sch_mn"
                                    class="form-select">

                                <?php
                                for($i=0;$i<60;$i++)
                                    printf(
                                        "<option value='%d'>%02d</option>",
                                        $i,
                                        $i
                                    );
                                ?>

                            </select>

                        </div>

                        <div class="col-md-5">

                            <select id="sch_file"
                                    class="form-select">

                                <option value="">
                                    เลือกไฟล์...
                                </option>

                            </select>

                        </div>

                        <div class="col-md-2">

                            <button class="btn btn-success w-100"
                                    onclick="addSchedule()">

                                เพิ่ม

                            </button>

                        </div>

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

/* ======================================================
   MODAL
====================================================== */

function openScheduleModal(id,name,ip,port){

    currentNode = {id,name,ip,port};

    document.getElementById('modalNodeName').innerText=name;

    loadSchedules();

    loadFileListForSelect(id);

    new bootstrap.Modal(
        document.getElementById('scheduleModal')
    ).show();
}

function loadSchedules(){

    fetch(
        `proxy_scheduler.php?action=list&node_id=${currentNode.id}`
    )
    .then(res=>res.text())
    .then(html=>{
        document.getElementById(
            'scheduleTableContainer'
        ).innerHTML=html;
    });
}

function loadFileListForSelect(nodeId){

    const fileSelect=document.getElementById('sch_file');

    const nodeFileSelect=document.querySelector(
        `#file-manager-${nodeId} select`
    );

    if(nodeFileSelect){

        fileSelect.innerHTML=nodeFileSelect.innerHTML;

    }
}

function addSchedule(){

    const data={

        action:'add',
        node_id:currentNode.id,
        ip:currentNode.ip,
        port:currentNode.port,

        day:document.getElementById('sch_day').value,
        hr:document.getElementById('sch_hr').value,
        mn:document.getElementById('sch_mn').value,
        file:document.getElementById('sch_file').value

    };

    if(!data.file){

        alert("กรุณาเลือกไฟล์");
        return;

    }

    fetch(
        'proxy_scheduler.php',
        {
            method:'POST',
            body:new URLSearchParams(data)
        }
    )
    .then(()=>{
        loadSchedules();
    });
}

/* ======================================================
   TOGGLE
====================================================== */

function toggleSched(id,activeStatus){

    const url =
        `proxy_scheduler.php?action=toggle` +
        `&id=${id}` +
        `&active=${activeStatus}` +
        `&node_id=${currentNode.id}` +
        `&ip=${currentNode.ip}` +
        `&port=${currentNode.port}`;

    fetch(url)
    .then(res=>res.text())
    .then(data=>{

        console.log(data);

        setTimeout(()=>{
            loadSchedules();
        },300);

    })
    .catch(err=>{

        console.error(err);

        alert("Toggle ไม่สำเร็จ");

    });
}

/* ======================================================
   DELETE SCHEDULE
====================================================== */

function deleteSched(id){

    if(!confirm("ยืนยันการลบ ?")) return;

    const url =
        `proxy_scheduler.php?action=delete` +
        `&id=${id}` +
        `&node_id=${currentNode.id}` +
        `&ip=${currentNode.ip}` +
        `&port=${currentNode.port}`;

    fetch(url)
    .then(res=>res.text())
    .then(data=>{

        console.log(data);

        setTimeout(()=>{
            loadSchedules();
        },300);

    })
    .catch(err=>{

        console.error(err);

        alert("ลบไม่สำเร็จ");

    });
}

/* ======================================================
   VOLUME
====================================================== */

function setSingleVolume(nodeId,ip,port,volPercent){

    const espVol=Math.round((volPercent*21)/100);

    fetch(
        `process_broadcast.php?action=vol_single&id=${nodeId}&ip=${ip}&port=${port}&v=${espVol}`
    );
}

/* ======================================================
   FILE
====================================================== */

function playFile(ip,port,selectId){

    const file=document.getElementById(selectId).value;

    if(!file) return;

    fetch(
        `process_broadcast.php?action=play_single&ip=${ip}&port=${port}&file=${encodeURIComponent('/'+file)}`
    );
}

function stopFile(ip,port){

    fetch(
        `process_broadcast.php?action=stop_single&ip=${ip}&port=${port}`
    );
}

/* ======================================================
   UPLOAD
====================================================== */

function uploadToNode(id,ip,port){

    const fileInput=document.getElementById('input_'+id);

    const area=document.getElementById('upload_area_'+id);

    const loading=document.getElementById('up_loading_'+id);

    if(!fileInput.files[0]){

        alert("เลือกไฟล์ก่อน");
        return;

    }

    area.style.display='none';

    loading.style.display='block';

    const formData=new FormData();

    formData.append('file',fileInput.files[0]);

    const xhr=new XMLHttpRequest();

    xhr.open(
        'POST',
        `http://${ip}:${port}/upload`,
        true
    );

    xhr.onload=function(){

        loading.style.display='none';

        area.style.display='block';

        fileInput.value='';

        alert("อัปโหลดสำเร็จ");

        loadAllNodeFiles();
    };

    xhr.onerror=function(){

        loading.style.display='none';

        area.style.display='block';

        alert("Upload Error");
    };

    xhr.send(formData);
}

/* ======================================================
   SELECT
====================================================== */

function updateSelection(){

    const selected=document.querySelectorAll(
        '.node-checkbox:checked'
    );

    document.getElementById('selectedCount')
        .innerText=selected.length;

    document.getElementById('bulkBar')
        .style.display=
            selected.length>0?'block':'none';
}

/* ======================================================
   BULK
====================================================== */

function bulkPlay(){

    const fileName=document.getElementById(
        'bulkFileName'
    ).value;

    if(!fileName){

        alert("ระบุไฟล์");
        return;

    }

    const selected=document.querySelectorAll(
        '.node-checkbox:checked'
    );

    const nodes=Array.from(selected).map(cb=>({

        ip:cb.dataset.ip,
        port:cb.dataset.port

    }));

    submitBulk(
        'play_selected',
        {
            nodes:JSON.stringify(nodes),
            filename:fileName.startsWith('/')
                ?fileName
                :'/'+fileName
        }
    );
}

function bulkStop(){

    const selected=document.querySelectorAll(
        '.node-checkbox:checked'
    );

    const nodes=Array.from(selected).map(cb=>({

        ip:cb.dataset.ip,
        port:cb.dataset.port

    }));

    submitBulk(
        'stop_selected',
        {
            nodes:JSON.stringify(nodes)
        }
    );
}

function submitBulk(action,params){

    const formData=new URLSearchParams();

    formData.append('action',action);

    for(const key in params){

        formData.append(key,params[key]);

    }

    fetch(
        'process_broadcast.php',
        {
            method:'POST',
            body:formData
        }
    );
}

/* ======================================================
   STATUS
====================================================== */

function updateStatuses(){

<?php foreach($nodes as $node): ?>

fetch('get_node_status.php?id=<?= $node['id'] ?>')
.then(res=>res.json())
.then(data=>{

    const statusEl=document.getElementById(
        'status-<?= $node['id'] ?>'
    );

    if(data.online){

        let batt='success';

        if(data.volt_pct<=25){

            batt='danger';

        }else if(data.volt_pct<=50){

            batt='warning text-dark';

        }

        let html=`
            <span class="node-dot dot-online"></span>
            <span class="status-online">Online</span>

            <span class="badge bg-${batt} badge-batt ms-2">
                <i class="fas fa-battery-three-quarters me-1"></i>
                ${data.volt}V (${data.volt_pct}%)
            </span>
        `;

        if(data.song && data.song!==""){

            html+=`
                <div class="mt-2">
                    <span class="badge bg-primary">
                        ▶ ${data.song}
                    </span>
                </div>
            `;
        }

        statusEl.innerHTML=html;

    }else{

        statusEl.innerHTML=`
            <span class="node-dot dot-offline"></span>
            <span class="status-offline">
                Offline
            </span>
        `;
    }

});

<?php endforeach; ?>

}

/* ======================================================
   FILE LIST
====================================================== */

function loadAllNodeFiles(){

<?php foreach($nodes as $node): ?>

fetch(
`get_node_files.php?id=<?= $node['id'] ?>&ip=<?= $node['ip_address'] ?>&port=<?= $node['port'] ?? 80 ?>`
)
.then(res=>res.text())
.then(html=>{

    document.getElementById(
        'file-manager-<?= $node['id'] ?>'
    ).innerHTML=html;

});

<?php endforeach; ?>

}

/* ======================================================
   ALL
====================================================== */

function sendAlarmBroadcast(){

    if(confirm("Alarm ทุกเครื่อง ?")){

        submitBulk(
            'play',
            {
                filename:'/alarm007.mp3'
            }
        );
    }
}

function stopAllBroadcast(){

    if(confirm("หยุดทุกเครื่อง ?")){

        submitBulk('stop',{});

    }
}

/* ======================================================
   INIT
====================================================== */

document.addEventListener('DOMContentLoaded',()=>{

    loadAllNodeFiles();

    updateStatuses();

    setInterval(updateStatuses,20000);

});

</script>

</body>
</html>