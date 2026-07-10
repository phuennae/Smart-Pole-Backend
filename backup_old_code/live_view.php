<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'] ?? 'user';
$NODE_SERVER = 'http://localhost:3000';
$cameraIP = '192.168.3.64';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Live View - <?= $cameraIP ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jmuxer@2.0.5/dist/jmuxer.min.js"></script>
    <style>
        body { background: #0f0f0f; font-family: 'Kanit', sans-serif; color: #fff; }
        .container { max-width: 1400px; }
        
        .video-container {
            position: relative;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 16/9;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }
        #videoCanvas {
            width: 100%;
            height: 100%;
            display: block;
            background: #000;
        }
        .video-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            padding: 5px 15px;
            border-radius: 6px;
            font-size: 0.9rem;
            z-index: 10;
        }
        .video-controls {
            position: absolute;
            bottom: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        .btn-ctrl {
            background: rgba(0,0,0,0.7);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 8px 14px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-ctrl:hover { background: rgba(13,110,253,0.8); }
        
        .blink { animation: blinker 1.5s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .status-connected { background: #198754; }
        .status-disconnected { background: #dc3545; }
        .status-loading { background: #ffc107; color: #000; }
        
        .ptz-panel {
            background: #1e1e1e;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        .ptz-grid {
            display: grid;
            grid-template-columns: repeat(3, 65px);
            grid-template-rows: repeat(3, 65px);
            gap: 8px;
            justify-content: center;
        }
        .ptz-btn {
            background: #2a2a2a;
            border: 2px solid #444;
            color: #fff;
            border-radius: 12px;
            font-size: 1.3rem;
            cursor: pointer;
            transition: 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ptz-btn:hover { background: #0d6efd; border-color: #0d6efd; }
        .ptz-btn:active { transform: scale(0.95); background: #0b5ed7; }
        .ptz-btn.center { background: #dc3545; border-color: #dc3545; }
        .ptz-btn.center:hover { background: #bb2d3b; }
        
        .log-panel {
            background: #1e1e1e;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            max-height: 200px;
            overflow-y: auto;
            font-family: 'Consolas', monospace;
            font-size: 12px;
        }
        .log-entry {
            padding: 3px 0;
            color: #9cdcfe;
        }
        .log-entry.error { color: #f48771; }
        .log-entry.success { color: #4ec9b0; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1"><i class="fas fa-broadcast-tower text-danger"></i> Live Stream</h3>
            <small class="text-muted">📹 Camera: <?= $cameraIP ?> | Channel: 102 (Sub-stream)</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="status-badge status-loading" id="streamStatus">
                <i class="fas fa-spinner fa-spin"></i> Connecting...
            </span>
            <span class="badge bg-info" id="fpsCounter">-- FPS</span>
        </div>
    </div>

    <!-- Video Player -->
    <div class="video-container">
        <div class="video-overlay">
            <i class="fas fa-circle text-danger blink me-1"></i>
            <span id="liveLabel">LIVE</span>: <?= $cameraIP ?>
            <span class="ms-2 text-muted" id="timeLabel"></span>
        </div>
        <canvas id="videoCanvas" width="1280" height="720"></canvas>
        <div class="video-controls">
            <button class="btn-ctrl" onclick="toggleFullscreen()" title="Fullscreen">
                <i class="fas fa-expand"></i>
            </button>
            <button class="btn-ctrl" onclick="captureFrame()" title="Snapshot">
                <i class="fas fa-camera"></i>
            </button>
            <button class="btn-ctrl" onclick="reconnect()" title="Reconnect">
                <i class="fas fa-redo"></i>
            </button>
        </div>
    </div>

    <!-- PTZ Control -->
    <div class="row mt-3">
        <div class="col-md-6">
            <div class="ptz-panel">
                <h6 class="mb-3"><i class="fas fa-gamepad text-primary"></i> PTZ Control (192.168.3.64)</h6>
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
                <div class="row mt-3 g-2">
                    <div class="col-6">
                        <button class="btn btn-outline-info w-100" onmousedown="ptzMove('zoomin')" onmouseup="ptzStop()" onmouseleave="ptzStop()">
                            <i class="fas fa-search-plus"></i> Zoom In
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-info w-100" onmousedown="ptzMove('zoomout')" onmouseup="ptzStop()" onmouseleave="ptzStop()">
                            <i class="fas fa-search-minus"></i> Zoom Out
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="ptz-panel">
                <h6 class="mb-3"><i class="fas fa-info-circle text-info"></i> Stream Info</h6>
                <table class="table table-dark table-sm">
                    <tr><th>Camera IP</th><td><?= $cameraIP ?></td></tr>
                    <tr><th>RTSP URL</th><td class="text-break">rtsp://admin:***@<?= $cameraIP ?>:554/Streaming/Channels/102</td></tr>
                    <tr><th>Video Codec</th><td>MPEG-1 (via FFmpeg)</td></tr>
                    <tr><th>Resolution</th><td>640x360</td></tr>
                    <tr><th>Bitrate</th><td>1000 kbps</td></tr>
                    <tr><th>FPS</th><td>20 fps</td></tr>
                    <tr><th>Transport</th><td>RTSP over TCP</td></tr>
                    <tr><th>WebSocket</th><td>ws://localhost:3000/ws-live</td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Log Panel -->
    <div class="log-panel" id="logPanel">
        <div class="text-muted">📋 Stream Log...</div>
    </div>
</div>

<script>
const NODE_SERVER = '<?= $NODE_SERVER ?>';
const WS_URL = 'ws://localhost:3000/ws-live';

let ws = null;
let jmuxer = null;
let reconnectTimer = null;
let frameCount = 0;
let lastFpsTime = Date.now();

// ============================================
// Logging
// ============================================
function log(msg, type = 'info') {
    const panel = document.getElementById('logPanel');
    const time = new Date().toLocaleTimeString();
    const div = document.createElement('div');
    div.className = 'log-entry ' + type;
    div.innerHTML = `<span style="color:#608b4e">[${time}]</span> ${msg}`;
    panel.insertBefore(div, panel.firstChild);
    if (panel.children.length > 50) panel.removeChild(panel.lastChild);
    console.log(`[${type.toUpperCase()}]`, msg);
}

// ============================================
// Stream Status
// ============================================
function updateStatus(status) {
    const el = document.getElementById('streamStatus');
    const map = {
        connected: { class: 'status-connected', icon: 'fa-check-circle', text: 'Connected' },
        disconnected: { class: 'status-disconnected', icon: 'fa-times-circle', text: 'Disconnected' },
        loading: { class: 'status-loading', icon: 'fa-spinner fa-spin', text: 'Connecting...' }
    };
    const s = map[status];
    el.className = 'status-badge ' + s.class;
    el.innerHTML = `<i class="fas ${s.icon}"></i> ${s.text}`;
}

// ============================================
// Initialize JMuxer
// ============================================
function initJMuxer() {
    if (jmuxer) {
        try { jmuxer.destroy(); } catch(e) {}
        jmuxer = null;
    }
    
    jmuxer = new JMuxer({
        node: 'videoCanvas',
        mode: 'video',
        flushingTime: 0,
        fps: 20,
        debug: false,
        onError: (data) => {
            log(`⚠️ JMuxer error: ${data}`, 'error');
        }
    });
    log('✅ JMuxer initialized');
}

// ============================================
// WebSocket Connection
// ============================================
function connectStream() {
    if (ws) {
        try { ws.close(); } catch(e) {}
        ws = null;
    }
    
    if (reconnectTimer) {
        clearTimeout(reconnectTimer);
        reconnectTimer = null;
    }
    
    updateStatus('loading');
    log(`🔌 Connecting to ${WS_URL}...`);
    
    try {
        ws = new WebSocket(WS_URL);
        ws.binaryType = 'arraybuffer';
        
        ws.onopen = () => {
            log('✅ WebSocket connected', 'success');
            updateStatus('connected');
            frameCount = 0;
        };
        
        ws.onmessage = (event) => {
            try {
                if (jmuxer) {
                    jmuxer.feed({ video: new Uint8Array(event.data) });
                    frameCount++;
                    
                    // Calculate FPS
                    const now = Date.now();
                    if (now - lastFpsTime >= 1000) {
                        const fps = Math.round(frameCount * 1000 / (now - lastFpsTime));
                        document.getElementById('fpsCounter').textContent = fps + ' FPS';
                        frameCount = 0;
                        lastFpsTime = now;
                    }
                }
            } catch (e) {
                log(`⚠️ Feed error: ${e.message}`, 'error');
            }
        };
        
        ws.onerror = (err) => {
            log('❌ WebSocket error', 'error');
            updateStatus('disconnected');
        };
        
        ws.onclose = (event) => {
            log(`🔌 Connection closed (code: ${event.code})`, 'error');
            updateStatus('disconnected');
            
            // Auto reconnect after 3 seconds
            log('🔄 Reconnecting in 3 seconds...');
            reconnectTimer = setTimeout(() => {
                connectStream();
            }, 3000);
        };
    } catch (err) {
        log(`❌ Connection failed: ${err.message}`, 'error');
        updateStatus('disconnected');
        reconnectTimer = setTimeout(connectStream, 3000);
    }
}

function reconnect() {
    log('🔄 Manual reconnect...');
    connectStream();
}

// ============================================
// PTZ Control
// ============================================
const ptzConfig = {
    ptz_ip: '192.168.3.64',
    ptz_port: 80,
    ptz_username: 'admin1',
    ptz_password: '*sS5383160'
};

async function ptzMove(command) {
    try {
        const res = await fetch(`${NODE_SERVER}/api/ptz`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...ptzConfig, command, speed: 0.5 })
        });
        const data = await res.json();
        if (data.success) {
            log(`✅ PTZ ${command}`, 'success');
        } else {
            log(`❌ PTZ error: ${data.error}`, 'error');
        }
    } catch (err) {
        log(`❌ PTZ request failed: ${err.message}`, 'error');
    }
}

async function ptzStop() {
    try {
        await fetch(`${NODE_SERVER}/api/ptz`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...ptzConfig, command: 'stop' })
        });
    } catch (err) {
        log(`❌ Stop error: ${err.message}`, 'error');
    }
}

// ============================================
// Utilities
// ============================================
function toggleFullscreen() {
    const container = document.querySelector('.video-container');
    if (!document.fullscreenElement) {
        container.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function captureFrame() {
    const canvas = document.getElementById('videoCanvas');
    const link = document.createElement('a');
    link.download = `snapshot_${Date.now()}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
    log('📸 Snapshot saved', 'success');
}

// Update time label
setInterval(() => {
    document.getElementById('timeLabel').textContent = new Date().toLocaleTimeString();
}, 1000);

// Keyboard control
document.addEventListener('keydown', (e) => {
    if (e.target.tagName === 'INPUT') return;
    const keyMap = {
        'ArrowUp': 'up', 'ArrowDown': 'down',
        'ArrowLeft': 'left', 'ArrowRight': 'right',
        '+': 'zoomin', '-': 'zoomout'
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
    const moveKeys = ['ArrowUp','ArrowDown','ArrowLeft','ArrowRight','+','-'];
    if (moveKeys.includes(e.key)) ptzStop();
});

// ============================================
// Initialize
// ============================================
window.addEventListener('load', () => {
    log('🚀 Live Viewer starting...');
    initJMuxer();
    connectStream();
});

window.addEventListener('beforeunload', () => {
    if (ws) try { ws.close(); } catch(e) {}
    if (jmuxer) try { jmuxer.destroy(); } catch(e) {}
});
</script>
</body>
</html>