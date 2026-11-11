<?php
require_once __DIR__ . '/../app/config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>3D Interactive UI Showcase — MedCore Healthcare Network</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <style>
    body {
      background: #030712;
      color: #f1f5f9;
      font-family: 'Plus Jakarta Sans', var(--font-main);
      overflow-x: hidden;
      margin: 0;
      padding: 0;
    }
    .perspective-container {
      perspective: 1200px;
    }
    .card-3d {
      transition: transform 0.15s ease-out, box-shadow 0.15s ease-out;
      transform-style: preserve-3d;
    }
    .hero-glow {
      position: absolute;
      width: 500px;
      height: 500px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(2,132,199,0.18) 0%, rgba(99,102,241,0.05) 50%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }
    .badge-pill {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 3px 10px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .portal-nav-btn {
      transition: all 0.2s ease;
      cursor: pointer;
      border: 1px solid rgba(255,255,255,0.08);
      background: rgba(15,23,42,0.7);
      color: #94a3b8;
      border-radius: 12px;
      padding: 10px 14px;
      font-weight: 700;
      font-size: 13px;
      text-align: center;
    }
    .portal-nav-btn:hover, .portal-nav-btn.active {
      background: rgba(2,132,199,0.2);
      border-color: #0284c7;
      color: #38bdf8;
      box-shadow: 0 4px 14px rgba(2,132,199,0.25);
      transform: translateY(-2px);
    }
    @keyframes pulseRing {
      0% { transform: scale(0.95); opacity: 0.8; }
      50% { transform: scale(1.05); opacity: 0.4; }
      100% { transform: scale(0.95); opacity: 0.8; }
    }
    .pulse-ring {
      animation: pulseRing 3s infinite ease-in-out;
    }
  </style>
</head>
<body>

  <!-- Ambient background glow -->
  <div class="hero-glow" style="top:-100px; left:-100px;"></div>
  <div class="hero-glow" style="bottom:100px; right:-100px; background:radial-gradient(circle, rgba(124,58,237,0.15) 0%, transparent 70%);"></div>

  <!-- Header / Nav -->
  <header style="border-bottom:1px solid #1e293b; background:rgba(3,7,18,0.85); backdrop-filter:blur(16px); position:sticky; top:0; z-index:50;">
    <div style="max-width:1240px; margin:0 auto; padding:14px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
      <a href="<?= APP_URL ?>/" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:#fff;">
        <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #0284c7, #2563eb); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px;">M</div>
        <div>
          <span style="font-weight:900; font-size:18px; letter-spacing:-0.5px;">MedCore <span style="color:#38bdf8;">3D</span></span>
          <span style="display:block; font-size:11px; color:#64748b; font-weight:600;">Interactive Ecosystem Showcase</span>
        </div>
      </a>

      <div style="display:flex; align-items:center; gap:10px;">
        <a href="<?= APP_URL ?>/book-doctor.php" style="padding:6px 14px; background:#0284c7; color:#fff; font-weight:700; border-radius:8px; text-decoration:none; font-size:12px; display:inline-flex; align-items:center; gap:6px;">
          <i class="bi bi-calendar2-check-fill"></i> ডাক্তার সিরিয়াল
        </a>
        <a href="<?= APP_URL ?>/" style="padding:6px 14px; background:#1e293b; color:#94a3b8; font-weight:700; border-radius:8px; text-decoration:none; font-size:12px;">
          &larr; Back to Home
        </a>
      </div>
    </div>
  </header>

  <!-- Hero Headline -->
  <section style="max-width:1240px; margin:30px auto 20px; padding:0 20px; text-align:center;">
    <div class="badge-pill" style="background:rgba(2,132,199,0.15); color:#38bdf8; border:1px solid rgba(56,189,248,0.3); margin-bottom:12px;">
      <i class="bi bi-box" style="font-size:12px;"></i> 3D Real-Time Healthcare Ecosystem
    </div>
    <h1 style="font-size:clamp(26px, 4vw, 42px); font-weight:900; line-height:1.2; margin:0 0 10px;">
      Less Text. <span style="background:linear-gradient(135deg, #38bdf8, #818cf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent;">More Visual Understanding.</span>
    </h1>
    <p style="color:#94a3b8; font-size:15px; max-width:680px; margin:0 auto 20px;">
      ঘুরিয়ে দেখুন ৩ডি হেলথকেয়ার ইকোসিস্টেম। প্রতিটি পোর্টালে লগইন করার পর ইন্টারফেস কেমন দেখাবে, তা ইন্টারেক্টিভভাবে টেস্ট করুন।
    </p>

    <!-- Quick Mode Toggles -->
    <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
      <button onclick="selectPortal('patient')" id="tab-patient" class="portal-nav-btn active">
        🧑‍🦱 Patient Portal
      </button>
      <button onclick="selectPortal('doctor')" id="tab-doctor" class="portal-nav-btn">
        🩺 Doctor Chamber
      </button>
      <button onclick="selectPortal('hospital')" id="tab-hospital" class="portal-nav-btn">
        🏥 Hospital OPD Desk
      </button>
      <button onclick="selectPortal('lab')" id="tab-lab" class="portal-nav-btn">
        🔬 Diagnostic Lab
      </button>
      <button onclick="selectPortal('emergency')" id="tab-emergency" class="portal-nav-btn">
        🚨 ICE First-Responder
      </button>
    </div>
  </section>

  <!-- Interactive 3D Showcase Grid -->
  <main style="max-width:1240px; margin:0 auto 60px; padding:0 20px;">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:24px; align-items:start;">

      <!-- Left Column: 3D Holographic Orbit Stage -->
      <div style="background:rgba(15,23,42,0.7); border:1px solid #1e293b; border-radius:20px; padding:20px; position:relative; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.5); backdrop-filter:blur(20px);">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
          <div style="font-size:12px; font-weight:800; color:#38bdf8; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:6px;">
            <span style="width:8px; height:8px; border-radius:50%; background:#10b981; display:inline-block;" class="pulse-ring"></span>
            3D Medical Universe
          </div>
          <div style="display:flex; gap:6px;">
            <button id="btn-toggle-rotate" style="padding:4px 10px; background:#0284c7; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">
              Auto-Rotate: ON
            </button>
            <button id="btn-reset-view" style="padding:4px 10px; background:#334155; color:#cbd5e1; border:none; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">
              Reset
            </button>
          </div>
        </div>

        <!-- 3D Canvas -->
        <canvas id="canvas-3d" style="width:100%; height:420px; display:block; border-radius:14px; background:radial-gradient(circle at center, #0f172a 0%, #030712 100%); cursor:grab;"></canvas>

        <div style="margin-top:14px; font-size:12px; color:#64748b; text-align:center;">
          💡 <em>মাউস দিয়ে ড্র্যাগ করে ৩ডি ইউনিভার্স ৩৬০ ডিগ্রিতে ঘুরিয়ে দেখুন</em>
        </div>
      </div>

      <!-- Right Column: Interactive 3D Depth Card Simulator -->
      <div class="perspective-container">
        
        <div id="interactive-card" class="card-3d" style="background:rgba(15,23,42,0.85); border:1px solid rgba(255,255,255,0.12); border-radius:20px; padding:28px; box-shadow:0 25px 60px rgba(0,0,0,0.6); position:relative; overflow:hidden; backdrop-filter:blur(24px);">
          
          <!-- Specular Glare -->
          <div id="card-glare" style="position:absolute; inset:-100%; pointer-events:none; opacity:0.15; background:radial-gradient(circle, rgba(56,189,248,0.4) 0%, transparent 60%); transition:opacity 0.2s;"></div>

          <!-- Dynamic Portal Content -->
          <div id="portal-content">
            <!-- Populated by JS -->
          </div>

        </div>

        <div style="margin-top:12px; background:rgba(15,23,42,0.5); border:1px solid #1e293b; border-radius:12px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#64748b;">
          <span><i class="bi bi-arrows-move" style="margin-right:6px; color:#38bdf8;"></i> Interactive 3D Card</span>
          <span>মাউস নাড়ালে ৩ডি টিল্ট ও গ্লেয়ার ইফেক্ট হবে</span>
        </div>

      </div>

    </div>
  </main>

  <!-- Script for 3D Galaxy & Interactive Live Controls -->
  <script>
    // 1. 3D Canvas Orbital Math
    const canvas = document.getElementById('canvas-3d');
    const ctx = canvas.getContext('2d');
    let width = canvas.width = canvas.clientWidth;
    let height = canvas.height = 420;

    let rotX = 0.25;
    let rotY = 0;
    let isDragging = false;
    let lastX = 0, lastY = 0;
    let autoRotate = true;

    const nodes = [
      { x: 0, y: 0, z: 0, r: 24, color: '#38bdf8', label: 'MedCore Cloud', sub: 'Unified DB', key: 'core' },
      { x: 120, y: -45, z: 20, r: 18, color: '#0284c7', label: 'Patient Portal', sub: 'PT-000001', key: 'patient' },
      { x: -120, y: -55, z: 40, r: 18, color: '#0d9488', label: 'Doctor Chamber', sub: 'DR-000001', key: 'doctor' },
      { x: 110, y: 65, z: -40, r: 18, color: '#1e3a8a', label: 'Hospital OPD', sub: 'HOSP-01', key: 'hospital' },
      { x: -110, y: 65, z: -30, r: 18, color: '#7c3aed', label: 'Diagnostic Lab', sub: 'LT-Token', key: 'lab' },
      { x: 0, y: -115, z: -60, r: 18, color: '#dc2626', label: 'Emergency ICE', sub: 'STAT Beacon', key: 'emergency' }
    ];

    const stars = [];
    for (let i = 0; i < 80; i++) {
      stars.push({
        x: (Math.random() - 0.5) * 600,
        y: (Math.random() - 0.5) * 600,
        z: (Math.random() - 0.5) * 600,
        r: Math.random() * 1.5 + 0.5
      });
    }

    function project(x, y, z) {
      const cosY = Math.cos(rotY), sinY = Math.sin(rotY);
      const x1 = x * cosY - z * sinY;
      const z1 = z * cosY + x * sinY;

      const cosX = Math.cos(rotX), sinX = Math.sin(rotX);
      const y2 = y * cosX - z1 * sinX;
      const z2 = z1 * cosX + y * sinX;

      const fov = 350;
      const scale = fov / (fov + z2 + 300);
      return {
        x: width / 2 + x1 * scale,
        y: height / 2 + y2 * scale,
        scale: scale,
        z: z2
      };
    }

    function render3D() {
      ctx.clearRect(0, 0, width, height);

      if (autoRotate && !isDragging) {
        rotY += 0.007;
      }

      // Draw background stars
      ctx.fillStyle = '#475569';
      stars.forEach(s => {
        const p = project(s.x, s.y, s.z);
        if (p.scale > 0) {
          ctx.beginPath();
          ctx.arc(p.x, p.y, s.r * p.scale, 0, Math.PI * 2);
          ctx.fill();
        }
      });

      // Project Nodes
      const proj = nodes.map(n => ({ ...n, p: project(n.x, n.y, n.z) }));
      proj.sort((a, b) => b.p.z - a.p.z);

      const core = proj.find(n => n.key === 'core');

      // Draw Lines
      proj.forEach(n => {
        if (n.key !== 'core') {
          ctx.beginPath();
          ctx.moveTo(core.p.x, core.p.y);
          ctx.lineTo(n.p.x, n.p.y);
          ctx.strokeStyle = 'rgba(56,189,248,0.18)';
          ctx.lineWidth = 1.5 * n.p.scale;
          ctx.setLineDash([4, 4]);
          ctx.stroke();
          ctx.setLineDash([]);
        }
      });

      // Draw Rings
      ctx.beginPath();
      ctx.ellipse(width / 2, height / 2, 130 * core.p.scale, 50 * core.p.scale, rotX, 0, Math.PI * 2);
      ctx.strokeStyle = 'rgba(14,165,233,0.15)';
      ctx.lineWidth = 1;
      ctx.stroke();

      // Draw Spheres
      proj.forEach(n => {
        const rad = Math.max(n.r * n.p.scale, 5);

        // Glow
        const grad = ctx.createRadialGradient(n.p.x, n.p.y, 0, n.p.x, n.p.y, rad * 2.2);
        grad.addColorStop(0, n.color);
        grad.addColorStop(1, 'transparent');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.arc(n.p.x, n.p.y, rad * 2.2, 0, Math.PI * 2);
        ctx.fill();

        // Core Sphere
        ctx.fillStyle = n.color;
        ctx.beginPath();
        ctx.arc(n.p.x, n.p.y, rad, 0, Math.PI * 2);
        ctx.fill();

        // Highlight
        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        ctx.beginPath();
        ctx.arc(n.p.x - rad * 0.3, n.p.y - rad * 0.3, rad * 0.35, 0, Math.PI * 2);
        ctx.fill();

        // Text
        ctx.font = `bold ${Math.max(12 * n.p.scale, 9)}px 'Plus Jakarta Sans', sans-serif`;
        ctx.fillStyle = '#f8fafc';
        ctx.textAlign = 'center';
        ctx.fillText(n.label, n.p.x, n.p.y + rad + 14 * n.p.scale);

        ctx.font = `${Math.max(10 * n.p.scale, 8)}px monospace`;
        ctx.fillStyle = '#94a3b8';
        ctx.fillText(n.sub, n.p.x, n.p.y + rad + 26 * n.p.scale);
      });

      requestAnimationFrame(render3D);
    }
    render3D();

    // Orbit Dragging
    canvas.addEventListener('mousedown', (e) => {
      isDragging = true;
      lastX = e.clientX;
      lastY = e.clientY;
      canvas.style.cursor = 'grabbing';
    });
    window.addEventListener('mouseup', () => {
      isDragging = false;
      canvas.style.cursor = 'grab';
    });
    window.addEventListener('mousemove', (e) => {
      if (!isDragging) return;
      const dx = e.clientX - lastX;
      const dy = e.clientY - lastY;
      rotY += dx * 0.008;
      rotX += dy * 0.008;
      lastX = e.clientX;
      lastY = e.clientY;
    });

    document.getElementById('btn-toggle-rotate').addEventListener('click', function() {
      autoRotate = !autoRotate;
      this.textContent = `Auto-Rotate: ${autoRotate ? 'ON' : 'OFF'}`;
      this.style.background = autoRotate ? '#0284c7' : '#334155';
    });
    document.getElementById('btn-reset-view').addEventListener('click', function() {
      rotX = 0.25;
      rotY = 0;
    });

    // 2. 3D Card Tilt
    const card = document.getElementById('interactive-card');
    const glare = document.getElementById('card-glare');

    window.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;

      if (x > -80 && x < rect.width + 80 && y > -80 && y < rect.height + 80) {
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const rotateX = -((y - centerY) / centerY) * 12;
        const rotateY = ((x - centerX) / centerX) * 12;

        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
        glare.style.opacity = '0.35';
        glare.style.transform = `translate(${x * 0.2}px, ${y * 0.2}px)`;
      } else {
        card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
        glare.style.opacity = '0.1';
      }
    });

    // 3. Portals Rendering
    let queueSerial = 1;
    let timerSec = 1799;

    setInterval(() => {
      if (timerSec > 0) {
        timerSec--;
        const el = document.getElementById('sim-timer');
        if (el) {
          const m = Math.floor(timerSec / 60);
          const s = timerSec % 60;
          el.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        }
      }
    }, 1000);

    const portals = {
      patient: {
        title: "Patient Dashboard (রোগীর ড্যাশবোর্ড)",
        badge: "PATIENT · PT-000001",
        badgeStyle: "background:rgba(2,132,199,0.2); color:#38bdf8; border:1px solid rgba(56,189,248,0.4);",
        render: () => `
          <!-- Allergy Alert -->
          <div style="background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3); border-radius:12px; padding:12px; display:flex; align-items:center; gap:10px; margin-bottom:16px;">
            <span style="font-size:20px;">⚠️</span>
            <div style="font-size:12px;">
              <strong style="color:#f87171; display:block;">Severe Penicillin Allergy</strong>
              <span style="color:#94a3b8;">DO NOT administer β-lactam antibiotics</span>
            </div>
            <span style="margin-left:auto; background:#dc2626; color:#fff; font-size:10px; font-weight:800; padding:2px 8px; border-radius:999px;">CRITICAL</span>
          </div>

          <!-- Serial Token Card -->
          <div style="background:linear-gradient(135deg, #0284c7 0%, #1e40af 100%); border-radius:16px; padding:18px; color:#fff; margin-bottom:16px; box-shadow:0 8px 24px rgba(37,99,235,0.25);">
            <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:700; opacity:0.9; margin-bottom:8px;">
              <span>TODAY'S APPOINTMENT</span>
              <span style="background:rgba(255,255,255,0.2); padding:2px 8px; border-radius:4px;">CONFIRMED</span>
            </div>
            <div style="display:flex; align-items:center; gap:16px;">
              <div style="background:#fff; color:#1e40af; font-family:'JetBrains Mono', monospace; font-size:32px; font-weight:900; padding:8px 16px; border-radius:12px; line-height:1;">
                #02
              </div>
              <div>
                <h3 style="margin:0 0 2px; font-size:16px; font-weight:800;">Dr. Farhana Rahman</h3>
                <div style="font-size:12px; opacity:0.9;">Cardiology · ABC General Hospital</div>
                <div style="font-size:11px; color:#bfdbfe; margin-top:4px;">Slot: 05:30 PM · Serial Token Ready</div>
              </div>
            </div>
            <button onclick="alert('Digital Token Slip Printed! (#02)');" style="width:100%; margin-top:14px; padding:8px; background:#fff; color:#1e40af; border:none; border-radius:8px; font-weight:800; font-size:12px; cursor:pointer;">
              🖨️ Print Digital Token Slip
            </button>
          </div>

          <!-- Short Lab Token -->
          <div style="background:rgba(88,28,135,0.3); border:1px solid rgba(168,85,247,0.3); border-radius:16px; padding:16px;">
            <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:#d8b4fe; margin-bottom:8px;">
              <span>🔬 Short Lab Token</span>
              <span style="color:#94a3b8; font-size:11px;">Expires: 18 Nov</span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(88,28,135,0.4); border:1px dashed #a855f7; border-radius:10px; padding:10px 14px;">
              <span style="font-family:'JetBrains Mono', monospace; font-size:20px; font-weight:900; color:#e9d5ff; letter-spacing:1px;">LT-99B2A</span>
              <button onclick="alert('Token Copied: LT-99B2A');" style="padding:4px 10px; background:#7c3aed; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">
                Copy
              </button>
            </div>
            <div style="font-size:11px; color:#94a3b8; margin-top:8px;">Show this code at any hospital laboratory desk</div>
          </div>
        `
      },

      doctor: {
        title: "Doctor Chamber (ডাক্তারের চেম্বার)",
        badge: "PHYSICIAN · DR-000001",
        badgeStyle: "background:rgba(13,148,136,0.2); color:#2dd4bf; border:1px solid rgba(45,212,191,0.4);",
        render: () => `
          <!-- Sticky Session Bar -->
          <div style="background:rgba(13,148,136,0.25); border:1px solid rgba(45,212,191,0.4); border-radius:12px; padding:12px 14px; display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <span style="width:8px; height:8px; border-radius:50%; background:#10b981; display:inline-block;" class="pulse-ring"></span>
              <span style="font-size:12px; font-weight:800; color:#2dd4bf;">In Session: Rahat M. (PT-001)</span>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
              <span id="sim-timer" style="font-family:'JetBrains Mono', monospace; font-size:12px; font-weight:800; color:#fbbf24; background:rgba(245,158,11,0.2); border:1px solid rgba(245,158,11,0.3); padding:2px 8px; border-radius:6px;">29:59</span>
              <button onclick="alert('Session Closed. Medical Record Secured.');" style="padding:2px 8px; background:#dc2626; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">End</button>
            </div>
          </div>

          <!-- Executive Summary -->
          <div style="background:rgba(15,23,42,0.8); border:1px solid #334155; border-radius:14px; padding:14px; margin-bottom:16px;">
            <div style="font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;">Executive Clinical Briefing</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:12px;">
              <div style="background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3); padding:8px 10px; border-radius:8px; color:#fca5a5;">
                <span style="display:block; font-size:10px; font-weight:800; color:#f87171;">ALLERGY</span>
                ⚠️ Penicillin
              </div>
              <div style="background:rgba(2,132,199,0.15); border:1px solid rgba(2,132,199,0.3); padding:8px 10px; border-radius:8px; color:#7dd3fc;">
                <span style="display:block; font-size:10px; font-weight:800; color:#38bdf8;">BLOOD & AGE</span>
                O+ Positive · 28 M
              </div>
            </div>
          </div>

          <!-- Live Queue -->
          <div style="background:rgba(15,23,42,0.9); border:1px solid #334155; border-radius:14px; padding:14px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
              <span style="font-size:12px; font-weight:800; color:#fff;">Live Consultation Queue</span>
              <button onclick="callNext();" style="padding:4px 10px; background:#0d9488; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:800; cursor:pointer;">
                + Call Next (#0${queueSerial + 1})
              </button>
            </div>
            <div style="display:flex; flex-direction:column; gap:6px; font-size:12px;">
              <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(239,68,68,0.2); border:1px solid rgba(239,68,68,0.4); padding:8px 10px; border-radius:8px;">
                <span style="font-family:'JetBrains Mono', monospace; font-weight:800; color:#f87171;">#01 STAT EMERGENCY</span>
                <span style="color:#cbd5e1; font-size:11px;">Chest Pain</span>
                <span style="background:#dc2626; color:#fff; font-size:10px; font-weight:800; padding:2px 6px; border-radius:4px;">Calling...</span>
              </div>
              <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(30,41,59,0.6); padding:8px 10px; border-radius:8px;">
                <span style="font-family:'JetBrains Mono', monospace; font-weight:800; color:#94a3b8;">#02 ROUTINE</span>
                <span style="color:#64748b; font-size:11px;">Followup</span>
                <span style="color:#64748b; font-size:10px;">Waiting</span>
              </div>
            </div>
          </div>
        `
      },

      hospital: {
        title: "Hospital Reception & OPD Desk",
        badge: "HOSPITAL · HOSP-000001",
        badgeStyle: "background:rgba(30,58,138,0.3); color:#818cf8; border:1px solid rgba(129,140,248,0.4);",
        render: () => `
          <!-- Stats -->
          <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-bottom:16px; text-align:center;">
            <div style="background:rgba(15,23,42,0.8); border:1px solid #334155; padding:10px; border-radius:12px;">
              <span style="display:block; font-size:20px; font-weight:900; color:#38bdf8; font-family:'JetBrains Mono', monospace;">24</span>
              <span style="font-size:10px; color:#94a3b8; font-weight:800; text-transform:uppercase;">Total OPD</span>
            </div>
            <div style="background:rgba(15,23,42,0.8); border:1px solid #334155; padding:10px; border-radius:12px;">
              <span style="display:block; font-size:20px; font-weight:900; color:#fbbf24; font-family:'JetBrains Mono', monospace;">12</span>
              <span style="font-size:10px; color:#94a3b8; font-weight:800; text-transform:uppercase;">In Lobby</span>
            </div>
            <div style="background:rgba(15,23,42,0.8); border:1px solid #334155; padding:10px; border-radius:12px;">
              <span style="display:block; font-size:20px; font-weight:900; color:#34d399; font-family:'JetBrains Mono', monospace;">08</span>
              <span style="font-size:10px; color:#94a3b8; font-weight:800; text-transform:uppercase;">Done</span>
            </div>
          </div>

          <!-- Lobby Check-in -->
          <div style="background:rgba(15,23,42,0.9); border:1px solid #334155; border-radius:14px; padding:14px;">
            <div style="font-size:12px; font-weight:800; color:#fff; margin-bottom:10px;">Reception Lobby Check-in</div>
            <div style="display:flex; flex-direction:column; gap:8px; font-size:12px;">
              <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(30,41,59,0.5); padding:10px 12px; border-radius:10px; border:1px solid #334155;">
                <div>
                  <span style="font-family:'JetBrains Mono', monospace; font-weight:800; color:#38bdf8;">#01 · APT-8812</span>
                  <div style="font-size:11px; color:#cbd5e1;">Dr. Farhana · Karim Uddin</div>
                </div>
                <button onclick="this.textContent='✓ Checked In'; this.style.background='#059669';" style="padding:6px 12px; background:#d97706; color:#fff; border:none; border-radius:6px; font-weight:800; font-size:11px; cursor:pointer;">
                  Mark Arrived
                </button>
              </div>
              <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(30,41,59,0.5); padding:10px 12px; border-radius:10px; border:1px solid #334155;">
                <div>
                  <span style="font-family:'JetBrains Mono', monospace; font-weight:800; color:#38bdf8;">#02 · APT-8813</span>
                  <div style="font-size:11px; color:#cbd5e1;">Dr. Kamrul · Rahat M.</div>
                </div>
                <span style="background:rgba(16,185,129,0.2); color:#34d399; border:1px solid rgba(16,185,129,0.4); padding:2px 8px; border-radius:4px; font-size:10px; font-weight:800;">
                  Paid ৳1,000
                </span>
              </div>
            </div>
          </div>
        `
      },

      lab: {
        title: "Diagnostic Laboratory Portal",
        badge: "LABORATORY · LAB-000001",
        badgeStyle: "background:rgba(124,58,237,0.2); color:#c084fc; border:1px solid rgba(192,132,252,0.4);",
        render: () => `
          <!-- Omnibar -->
          <div style="background:rgba(88,28,135,0.25); border:1px solid rgba(168,85,247,0.4); border-radius:12px; padding:12px; margin-bottom:14px;">
            <label style="display:block; font-size:10px; font-weight:800; color:#d8b4fe; text-transform:uppercase; margin-bottom:4px;">Token Search Omnibar</label>
            <div style="display:flex; gap:8px;">
              <input type="text" value="LT-99B2A" style="flex:1; background:#0f172a; border:1px solid #7c3aed; border-radius:6px; padding:6px 10px; font-family:'JetBrains Mono', monospace; color:#f3f4f6; font-size:14px;">
              <button onclick="alert('Pulled Test Order for Rahat Mahamud (PT-000001)');" style="padding:6px 14px; background:#7c3aed; color:#fff; border:none; border-radius:6px; font-weight:800; font-size:12px; cursor:pointer;">
                Fetch
              </button>
            </div>
          </div>

          <!-- Test Form -->
          <div style="background:rgba(15,23,42,0.9); border:1px solid #334155; border-radius:14px; padding:14px; font-size:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
              <span style="font-weight:800; color:#fff;">CBC (Complete Blood Count)</span>
              <span style="background:#dc2626; color:#fff; font-size:10px; font-weight:800; padding:2px 6px; border-radius:4px;">STAT</span>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px;">
              <div>
                <label style="font-size:10px; color:#94a3b8; display:block;">Hemoglobin</label>
                <input type="text" value="13.8 g/dL" style="width:100%; box-sizing:border-box; background:#0f172a; border:1px solid #475569; border-radius:6px; padding:4px 8px; color:#fff; font-size:12px;">
              </div>
              <div>
                <label style="font-size:10px; color:#94a3b8; display:block;">Status</label>
                <span style="display:inline-block; margin-top:2px; background:rgba(16,185,129,0.2); color:#34d399; border:1px solid rgba(16,185,129,0.4); padding:2px 8px; border-radius:4px; font-size:11px; font-weight:800;">
                  ✓ NORMAL
                </span>
              </div>
            </div>
            <button onclick="alert('Result Uploaded & Synced to Patient Mobile App!');" style="width:100%; padding:10px; background:linear-gradient(135deg, #7c3aed, #4f46e5); color:#fff; border:none; border-radius:8px; font-weight:800; font-size:12px; cursor:pointer;">
              🚀 Submit & Auto-Sync to Patient Portal
            </button>
          </div>
        `
      },

      emergency: {
        title: "Emergency ICE First-Responder Profile",
        badge: "EMERGENCY · NO LOGIN REQUIRED",
        badgeStyle: "background:rgba(220,38,38,0.2); color:#f87171; border:1px solid rgba(248,113,113,0.4);",
        render: () => `
          <!-- ICE Card -->
          <div style="background:linear-gradient(180deg, rgba(220,38,38,0.2) 0%, rgba(15,23,42,0.9) 100%); border:1px solid rgba(239,68,68,0.4); border-radius:16px; padding:20px; text-align:center;">
            <div style="width:64px; height:64px; margin:0 auto 10px; border-radius:50%; background:#dc2626; color:#fff; font-size:28px; font-weight:900; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(220,38,38,0.4);">
              O+
            </div>
            <h3 style="margin:0 0 2px; font-size:18px; font-weight:900; color:#fff;">Rahat Mahamud</h3>
            <div style="font-family:'JetBrains Mono', monospace; font-size:12px; color:#fca5a5;">UID: PT-000001 · Age: 28</div>

            <div style="margin-top:14px; background:rgba(239,68,68,0.2); border:1px solid rgba(239,68,68,0.4); border-radius:10px; padding:10px 12px; text-align:left; font-size:12px;">
              <strong style="color:#f87171; display:block; font-size:11px;">⚠️ SEVERE ALLERGY:</strong>
              <span style="color:#cbd5e1;">Penicillin (Anaphylaxis Risk)</span>
            </div>

            <a href="tel:01711000000" onclick="alert('Calling Next-of-Kin Emergency Contact: 01711-000000'); return false;" style="display:block; margin-top:16px; padding:12px; background:#059669; color:#fff; border-radius:10px; font-weight:800; font-size:13px; text-decoration:none; box-shadow:0 4px 14px rgba(5,150,105,0.4);">
              📞 1-Click Call Next-of-Kin
            </a>
          </div>
        `
      }
    };

    function selectPortal(key) {
      document.querySelectorAll('.portal-nav-btn').forEach(b => b.classList.remove('active'));
      const activeBtn = document.getElementById(`tab-${key}`);
      if (activeBtn) activeBtn.classList.add('active');

      const p = portals[key];
      const container = document.getElementById('portal-content');
      container.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
          <span class="badge-pill" style="${p.badgeStyle}">
            ${p.badge}
          </span>
          <span style="font-size:11px; color:#64748b; font-family:'JetBrains Mono', monospace;">Interactive 3D View</span>
        </div>
        <h2 style="font-size:18px; font-weight:800; color:#fff; margin:0 0 14px;">${p.title}</h2>
        ${p.render()}
      `;
    }

    function callNext() {
      queueSerial++;
      selectPortal('doctor');
      alert(`Called Patient #${String(queueSerial).padStart(2,'0')} into Doctor Chamber.`);
    }

    // Default to Patient
    selectPortal('patient');

    window.addEventListener('resize', () => {
      width = canvas.width = canvas.clientWidth;
      height = canvas.height = 420;
    });
  </script>

</body>
</html>
