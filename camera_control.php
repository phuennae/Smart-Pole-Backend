<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'] ?? 'user';
$cameraIP = '192.168.1.64';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ควบคุมกล้อง IP - <?= $cameraIP ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jmuxer@2.0.5/dist/jmuxer.min.js"></script>
    <style>
        body { background: #0f0f0f; font-family: 'Kanit', sans-serif; color: #fff; }
        .main-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* Video Player */
        .video-container {
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            aspect-ratio: 16/9;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }
        #videoCanvas { width: 100%; height: 100%; display: block; background: #000; }
        .video-overlay {
            position: absolute; top: 10px; left: 10px;
            background: rgba(0,0,0,0.7); padding: 5px 15px;
            border-radius: 6px; font-size: 0.9rem; z-index: 10;
        }
        .blink { animation: blinker 1.5s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }
        
        /* Mode Tabs */
        .mode-tabs {
            display: flex; gap: 10px; margin-bottom: 20px;
        }
        .mode-tab {
            flex: 1; padding: 15px; background: #1e1e1e;
            border: 2px solid transparent; border-radius: 10px;
            color: #888; cursor: pointer; transition: 0.3s;
            text-align: center; font-size: 1.1rem;
        }
        .mode-tab.active {
            border-color: #0d6efd; color: #fff;
            background: #1a2a4a;
        }
        .mode-tab:hover { color: #fff; }
        
        /* PTZ Control */
        .ptz-container {
            background: #1e1e1e; border-radius: 12px;
            padding: 20px; margin-top: 20px;
        }
        .ptz-grid {
            display: grid;
            grid-template-columns: repeat(3, 70px);
            grid-template-rows: repeat(3, 70px);
            gap: 8px;
            justify-content: center;
        }
        .ptz-btn {
            background: #2a2a2a; border: 2px solid #444;
            color: #fff; border-radius: 12px; cursor: pointer;
            font-size: 1.5rem; transition: 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .ptz-btn:hover { background: #0d6efd; border-color: #0d6efd; }
        .ptz-btn:active { transform: scale(0.95); background: #0b5ed7; }
        .ptz-btn.center { background: #dc3545; border-color: #dc3545; }
        .ptz-btn.center:hover { background: #bb2d3b; }
        .ptz-btn.disabled { opacity: 0.3; pointer-events: none; }
        
        .speed-control { margin-top: 15px; }
        .speed-control label { color: #aaa; font-size: 0.9rem; }
        
        /* Playback Panel */
        .playback-panel {
            background: #1e1e1e; border-radius: 12px;
            padding: 20px; margin-top: 20px;
        }
        .playback-panel input, .playback-panel select {
            background: #2a2a2a; border: 1px solid #444;
            color: #fff;
        }
        .timeline-container {
            background: #0f0f0f; border-radius: 8px;
            padding: 15px; margin-top: 15px;
        }
        .timeline-bar {
            height: 40px; background: #2a2a2a;
            border-radius: 6px; position: relative;
            overflow: hidden;
        }
        .timeline-segment {
            position: absolute; height: 100%;
            background: linear-gradient(90deg, #0d6efd, #6610f2);
            opacity: 0.7;
        }
        
        /* Status */
        .status-badge {
            display: inline-flex; align-items: center;
            gap: 5px; padding: 4px 12px;
            border-radius: 20px; font-size: 0.85rem;
        }
        .status-connected { background: #198754; }
        .status-disconnected { background: #dc3545; }
        .status-loading { background: #ffc107; color: #000; }
        
        /* Debug Panel */
        .debug-panel {
            position: fixed; bottom: 0; left: 0; right: 0;
            max-height: 40vh; background: #1e1e1e;
            color: #d4d4d4; font-family: 'Consolas', monospace;
            font-size: 12px; z-index: 9999;
            display: none; flex-direction: column;
            border-top: 3px solid #007bff;
        }
        .debug-panel.show { display: flex; }
        .debug-header {
            background: #252526; padding: 8px 15px;
            display: flex; justify-content: space-between;
            align-items: center; border-bottom: 1px solid #3c3c3c;
        }
        .debug-body { flex: 1; overflow-y: auto; padding: 10px 15px; }
        .debug-entry {
            margin-bottom: 8px; padding: 8px;
            background: #2d2d2d; border-left: 3px solid #007bff;
            border-radius: 4px;
        }
        .debug-entry.success { border-left-color: #4ec9b0; }
        .debug-entry.error { border-left-color: #f48771; }
        .debug-entry.info { border-left-color: #ffc107; }
        .debug-toggle {
            position: fixed; bottom: 20px; right: 20px;
            z-index: 10000; width: 50px; height: 50px;
            border-radius: 50%; background: #dc3545;
            color: white; border: none; cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fas fa-video text-danger"></i> ควบคุมกล้อง IP: <?= $cameraIP ?></h3>
        <div>
            <span class="status-badge status-loading" id="ptzStatus">
                <i class="fas fa-spinner fa-spin"></i> เชื่อมต่อ PTZ...
            </span>
            <span class="status-badge status-loading" id="streamStatus">
                <i class="fas fa-spinner fa-spin"></i> Stream...
            </span>
        </div>
    </div>

    <!-- Mode Tabs -->
    <div class="mode-tabs">
        <div class="mode-tab active" onclick="switchMode('live')" id="tab-live">
            <i class="fas fa-broadcast-tower"></i> สด (Live)
        </div>
        <div class="mode-tab" onclick="switchMode('playback')" id="tab-playback">
            <i class="fas fa-history"></i> ย้อนหลัง (Playback)
        </div>
    </div>

    <!-- Video Player -->
    <div class="video-container">
        <div class="video-overlay">
            <i class="fas fa-circle text-danger blink me-1"></i>
            <span id="modeLabel">LIVE</span>: <?= $cameraIP ?>
        </div>
        <canvas id="videoCanvas"></canvas>
    </div>

    <!-- Live Mode: PTZ Control -->
    <div id="livePanel" class="ptz-container">
        <h5 class="mb-3"><i class="fas fa-gamepad"></i> PTZ Control</h5>
        <div class="row">
            <div class="col-md-6">
                <div class="ptz-grid">
                    <div></div>
                    <button class="ptz-btn" onmousedown="ptzMove('up')" onmouseup="ptzStop()" onmouseleave="ptzStop()">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                    <div></div>
                    <button class="ptz-btn" onmousedown="ptzMove('left')" onmouseup="ptzStop()" onmouseleave="ptzStop()">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <button class="ptz-btn center" onclick="ptzStop()">
                        <i class="fas fa-stop"></i>
                    </button>
                    <button class="ptz-btn" onmousedown="ptzMove('right')" onmouseup="ptzStop()" onmouseleave="ptzStop()">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <div></div>
                    <button class="ptz-btn" onmousedown="ptzMove('down')" onmouseup="ptzStop()" onmouseleave="ptzStop()">
                        <i class="fas fa-arrow-down"></i>
                    </button>
                    <div></div>
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-3">🔍 Zoom & Focus</h6>
                <div class="d-grid gap-2 mb-3">
                    <button class="ptz-btn" style="height:50px" onmousedown="ptzMove('zoomin')" onmouseup="ptzStop()" onmouseleave="ptzStop()">
                        <i class="fas fa-search-plus"></i> Zoom In
                    </button>
                    <button class="ptz-btn" style="height:50px" onmousedown="ptzMove('zoomout')" onmouseup="ptzStop()" onmouseleave="ptzStop()">
                        <i class="fas fa-search-minus"></i> Zoom Out
                    </button>
                </div>
                <div class="speed-control">
                    <label>ความเร็ว: <span id="speedLabel">0.5</span></label>
                    <input type="range" class="form-range" id="speedRange" 
                           min="0.1" max="1" step="0.1" value="0.5"
                           oninput="updateSpeed(this.value)">
                </div>
                <div class="mt-3">
                    <button class="btn btn-outline-info btn-sm w-100" onclick="goPreset(1)">
                        <i class="fas fa-home"></i> กลับตำแหน่งเริ่มต้น
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Playback Mode -->
    <div id="playbackPanel" class="playback-panel" style="display:none;">
        <h5 class="mb-3"><i class="fas fa-history"></i> เลือกช่วงเวลา</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">วันที่</label>
                <input type="date" class="form-control" id="playbackDate" 
                       value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">เวลาเริ่มต้น</label>
                <input type="time" class="form-control" id="startTime" 
                       value="<?= date('H:i', strtotime('-1 hour')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">เวลาสิ้นสุด</label>
                <input type="time" class="form-control" id="endTime" 
                       value="<?= date('H:i') ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" onclick="startPlayback()">
                    <i class="fas fa-play"></i> เล่น
                </button>
            </div>
        </div>
        
        <div class="timeline-container">
            <div class="d-flex justify-content-between mb-2">
                <small class="text-muted">Timeline</small>
                <small class="text-muted" id="playbackTime">--:--:--</small>
            </div>
            <div class="timeline-bar" id="timelineBar"></div>
            <div class="d-flex justify-content-between mt-2">
                <small class="text-muted">00:00</small>
                <small class="text-muted">06:00</small>
                <small class="text-muted">12:00</small>
                <small class="text-muted">18:00</small>
                <small class="text-muted">24:00</small>
            </div>
        </div>
        
        <div class="mt-3">
            <button class="btn btn-outline-danger btn-sm" onclick="stopPlayback()">
                <i class="fas fa-stop"></i> หยุดเล่นย้อนหลัง
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="switchMode('live')">
                <i class="fas fa-broadcast-tower"></i> กลับไป Live
            </button>
        </div>
    </div>
</div>

<!-- Debug Toggle -->
<button class="debug-toggle" onclick="toggleDebug()" title="Debug Panel">
    <i class="fas fa-bug"></i>
</button>

<!-- Debug Panel -->
<div class="debug-panel" id="debugPanel">
    <div class="debug-header">
        <h6 class="mb-0"><i class="fas fa-bug"></i> Debug Console</h6>
        <div>
            <button class="btn btn-sm btn-outline-warning" onclick="clearDebug()">
                <i class="fas fa-trash"></i> Clear
            </button>
            <button class="btn btn-sm btn-outline-light" onclick="toggleDebug()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div class="debug-body" id="debugBody"></div>
</div>

<script>
const NODE_SERVER = '<?= $nodeServer ?>';
const CAMERA_IP = '<?= $cameraIP ?>';

let currentMode = 'live';
let currentSpeed = 0.5;
let jmuxer = null;
let ws = null;
let isPTZReady = false;

// ============================================
// Debug Functions
// ============================================
function toggleDebug() {
    document.getElementById('debugPanel').classList.toggle('show');
}

function clearDebug() {
    document.getElementById('debugBody').innerHTML = '';
}

function debugLog(msg, type = 'info', data = null) {
    const body = document.getElementById('debugBody');
    const time = new Date().toLocaleTimeString();
    const colors = { info: '#ffc107', success: '#4ec9b0', error: '#f48771' };
    
    let html = `<div class="debug-entry ${type}">
        <span style="color:${colors[type]}">[${time}]</span> ${msg}`;
    if (data) {
        html += `<pre style="margin:5px 0 0;color:#9cdcfe;font-size:11px;">${
            typeof data === 'string' ? data : JSON.stringify(data, null, 2)
        }</pre>`;
    }
    html += `</div>`;
    body.insertAdjacentHTML('afterbegin', html);
    console.log(`[${type.toUpperCase()}]`, msg, data || '');
}

// ============================================
// Mode Switching
// ============================================
function switchMode(mode) {
    currentMode = mode;
    document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + mode).classList.add('active');
    
    if (mode === 'live') {
        document.getElementById('livePanel').style.display = 'block';
        document.getElementById('playbackPanel').style.display = 'none';
        document.getElementById('modeLabel').textContent = 'LIVE';
        stopPlayback();
        connectLiveStream();
    } else {
        document.getElementById('livePanel').style.display = 'none';
        document.getElementById('playbackPanel').style.display = 'block';
        document.getElementById('modeLabel').textContent = 'PLAYBACK';
        stopLiveStream();
    }
}

// ============================================
// Live Stream (WebSocket)
// ============================================
function initJmuxer() {
    if (jmuxer) return;
    
    const canvas = document.getElementById('videoCanvas');
    jmuxer = new JMuxer({
        node: canvas,
        mode: 'video',
        flushingTime: 0,
        fps: 20,
        debug: false
    });
    debugLog('JMuxer initialized', 'success');
}

function connectLiveStream() {
    stopLiveStream();
    initJmuxer();
    
    const wsUrl = `ws://${window.location.hostname}:9999`;
    debugLog(`Connecting to live stream: ${wsUrl}`, 'info');
    
    updateStreamStatus('loading');
    
    ws = new WebSocket(wsUrl);
    ws.binaryType = 'arraybuffer';
    
    ws.onopen = () => {
        debugLog('✅ Live stream connected', 'success');
        updateStreamStatus('connected');
    };
    
    ws.onmessage = (event) => {
        try {
            jmuxer.feed({ video: new Uint8Array(event.data) });
        } catch (e) {
            debugLog('⚠️ JMuxer feed error: ' + e.message, 'error');
        }
    };
    
    ws.onerror = (err) => {
        debugLog('❌ WebSocket error', 'error', err);
        updateStreamStatus('disconnected');
    };
    
    ws.onclose = () => {
        debugLog('🔌 Live stream disconnected', 'info');
        updateStreamStatus('disconnected');
        // Auto reconnect
        if (currentMode === 'live') {
            setTimeout(connectLiveStream, 3000);
        }
    };
}

function stopLiveStream() {
    if (ws) {
        try { ws.close(); } catch (e) {}
        ws = null;
    }
}

function updateStreamStatus(status) {
    const el = document.getElementById('streamStatus');
    const map = {
        connected: { class: 'status-connected', icon: 'fa-check-circle', text: 'Stream: Connected' },
        disconnected: { class: 'status-disconnected', icon: 'fa-times-circle', text: 'Stream: Offline' },
        loading: { class: 'status-loading', icon: 'fa-spinner fa-spin', text: 'Stream: Connecting...' }
    };
    const s = map[status];
    el.className = 'status-badge ' + s.class;
    el.innerHTML = `<i class="fas ${s.icon}"></i> ${s.text}`;
}

// ============================================
// PTZ Control
// ============================================
async function checkPTZStatus() {
    try {
        const res = await fetch(`${NODE_SERVER}/api/ptz-status`);
        const data = await res.json();
        debugLog('PTZ Status', 'info', data);
        
        isPTZReady = data.ready;
        const el = document.getElementById('ptzStatus');
        
        if (data.ready) {
            el.className = 'status-badge status-connected';
            el.innerHTML = `<i class="fas fa-check-circle"></i> PTZ: ${data.device?.model || 'Ready'}`;
            document.querySelectorAll('.ptz-btn').forEach(b => b.classList.remove('disabled'));
        } else {
            el.className = 'status-badge status-disconnected';
            el.innerHTML = `<i class="fas fa-times-circle"></i> PTZ: Not Ready`;
            document.querySelectorAll('.ptz-btn').forEach(b => b.classList.add('disabled'));
        }
    } catch (err) {
        debugLog('❌ Cannot check PTZ status: ' + err.message, 'error');
        document.getElementById('ptzStatus').className = 'status-badge status-disconnected';
        document.getElementById('ptzStatus').innerHTML = '<i class="fas fa-times-circle"></i> PTZ: Server Offline';
    }
}

async function ptzMove(command) {
    debugLog(`📤 PTZ Move: ${command} (speed: ${currentSpeed})`, 'info');
    
    try {
        const res = await fetch(`${NODE_SERVER}/api/ptz`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ command, speed: currentSpeed })
        });
        
        const data = await res.json();
        
        if (res.ok) {
            debugLog(`✅ PTZ ${command} sent`, 'success', data);
        } else {
            debugLog(`❌ PTZ error: ${data.error}`, 'error', data);
        }
    } catch (err) {
        debugLog('❌ PTZ request failed: ' + err.message, 'error');
    }
}

async function ptzStop() {
    debugLog('🛑 PTZ Stop', 'info');
    try {
        await fetch(`${NODE_SERVER}/api/ptz`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ command: 'stop' })
        });
    } catch (err) {
        debugLog('❌ Stop error: ' + err.message, 'error');
    }
}

async function goPreset(presetIndex) {
    debugLog(`🏠 Going to preset ${presetIndex}`, 'info');
    try {
        const res = await fetch(`${NODE_SERVER}/api/ptz-preset`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ preset: presetIndex })
        });
        const data = await res.json();
        debugLog(data.success ? '✅ Preset loaded' : '❌ ' + data.error, 
                 data.success ? 'success' : 'error', data);
    } catch (err) {
        debugLog('❌ Preset error: ' + err.message, 'error');
    }
}

function updateSpeed(val) {
    currentSpeed = parseFloat(val);
    document.getElementById('speedLabel').textContent = val;
}

// ============================================
// Playback
// ============================================
async function startPlayback() {
    const date = document.getElementById('playbackDate').value;
    const startTime = document.getElementById('startTime').value;
    const endTime = document.getElementById('endTime').value;
    
    if (!date || !startTime || !endTime) {
        alert('กรุณาเลือกวันและเวลา');
        return;
    }
    
    const startISO = `${date}T${startTime}:00.000Z`;
    const endISO = `${date}T${endTime}:00.000Z`;
    
    debugLog(`📼 Starting playback: ${startISO} → ${endISO}`, 'info');
    
    // Stop live stream first
    stopLiveStream();
    initJmuxer();
    
    try {
        const res = await fetch(`${NODE_SERVER}/api/playback`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ startTime: startISO, endTime: endISO })
        });
        
        const data = await res.json();
        debugLog('Playback response', data.success ? 'success' : 'error', data);
        
        if (data.success) {
            // Connect to playback WebSocket
            connectPlaybackStream();
        }
    } catch (err) {
        debugLog('❌ Playback error: ' + err.message, 'error');
    }
}

function connectPlaybackStream() {
    const wsUrl = `ws://${window.location.hostname}:9998`;
    debugLog(`Connecting to playback: ${wsUrl}`, 'info');
    
    const playbackWs = new WebSocket(wsUrl);
    playbackWs.binaryType = 'arraybuffer';
    
    playbackWs.onopen = () => {
        debugLog('✅ Playback stream connected', 'success');
    };
    
    playbackWs.onmessage = (event) => {
        try {
            jmuxer.feed({ video: new Uint8Array(event.data) });
        } catch (e) {
            debugLog('⚠️ Playback feed error: ' + e.message, 'error');
        }
    };
    
    playbackWs.onerror = (err) => {
        debugLog('❌ Playback WS error', 'error');
    };
    
    playbackWs.onclose = () => {
        debugLog('🔌 Playback stream closed', 'info');
    };
}

async function stopPlayback() {
    try {
        await fetch(`${NODE_SERVER}/api/stop-playback`, { method: 'POST' });
        debugLog('🛑 Playback stopped', 'info');
    } catch (err) {
        debugLog('❌ Stop playback error: ' + err.message, 'error');
    }
}

// ============================================
// Init
// ============================================
window.addEventListener('load', () => {
    debugLog('🚀 System initialized', 'success');
    debugLog(`Camera IP: ${CAMERA_IP}`, 'info');
    debugLog(`Node Server: ${NODE_SERVER}`, 'info');
    
    checkPTZStatus();
    setInterval(checkPTZStatus, 10000);
    
    connectLiveStream();
});

// Keyboard control
document.addEventListener('keydown', (e) => {
    if (currentMode !== 'live') return;
    if (e.target.tagName === 'INPUT') return;
    
    const keyMap = {
        'ArrowUp': 'up', 'ArrowDown': 'down',
        'ArrowLeft': 'left', 'ArrowRight': 'right',
        '+': 'zoomin', '-': 'zoomout',
        'w': 'up', 's': 'down', 'a': 'left', 'd': 'right'
    };
    
    if (keyMap[e.key]) {
        e.preventDefault();
        ptzMove(keyMap[e.key]);
    } else if (e.key === ' ' || e.key === 'Escape') {
        e.preventDefault();
        ptzStop();
    }
});

document.addEventListener('keyup', (e) => {
    if (currentMode !== 'live') return;
    const moveKeys = ['ArrowUp','ArrowDown','ArrowLeft','ArrowRight','w','a','s','d','+','-'];
    if (moveKeys.includes(e.key)) ptzStop();
});
</script>
</body>
</html>