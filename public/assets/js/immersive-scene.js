/**
 * MedCore Immersive Care Network — WebGL / Canvas 3D scene
 * Visual metaphor: one citizen record connected to hospitals, doctors, labs.
 * Respects prefers-reduced-motion and falls back to static CSS atmosphere.
 */
(function () {
  'use strict';

  const canvas = document.getElementById('mc-immersive-canvas');
  if (!canvas) return;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const ctx = canvas.getContext('2d', { alpha: true });
  if (!ctx) return;

  const ROLES = [
    { label: 'Patient', color: '#0EA5E9', r: 5.2 },
    { label: 'Doctor', color: '#0D9488', r: 4.6 },
    { label: 'Hospital', color: '#0369A1', r: 5.0 },
    { label: 'Lab', color: '#CA8A04', r: 4.2 },
    { label: 'ICE', color: '#DC2626', r: 4.8 },
  ];

  let width = 0;
  let height = 0;
  let dpr = 1;
  let nodes = [];
  let links = [];
  let pointer = { x: 0, y: 0, tx: 0, ty: 0 };
  let time = 0;
  let raf = 0;
  let running = true;

  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function seedNetwork() {
    const count = Math.min(72, Math.floor((width * height) / 18000) + 36);
    nodes = [];
    for (let i = 0; i < count; i++) {
      const role = ROLES[i % ROLES.length];
      const depth = 0.35 + Math.random() * 0.65;
      nodes.push({
        role,
        x: (Math.random() - 0.5) * width * 1.2,
        y: (Math.random() - 0.5) * height * 1.1,
        z: depth,
        ox: 0,
        oy: 0,
        phase: Math.random() * Math.PI * 2,
        spin: 0.2 + Math.random() * 0.8,
      });
      nodes[i].ox = nodes[i].x;
      nodes[i].oy = nodes[i].y;
    }

    // Core hub — the unified citizen record
    nodes.unshift({
      role: { label: 'MedCore', color: '#FFFFFF', r: 7 },
      x: 0,
      y: -height * 0.02,
      z: 1,
      ox: 0,
      oy: -height * 0.02,
      phase: 0,
      spin: 0.35,
      hub: true,
    });

    links = [];
    for (let i = 1; i < nodes.length; i++) {
      if (Math.random() < 0.55) links.push([0, i]);
      const j = 1 + Math.floor(Math.random() * (nodes.length - 1));
      if (i !== j && Math.random() < 0.18) links.push([i, j]);
    }
  }

  function project(node) {
    const parallaxX = pointer.x * (1.2 - node.z) * 28;
    const parallaxY = pointer.y * (1.2 - node.z) * 18;
    const driftX = Math.sin(time * 0.35 * node.spin + node.phase) * 14 * node.z;
    const driftY = Math.cos(time * 0.28 * node.spin + node.phase) * 10 * node.z;
    const scale = 0.55 + node.z * 0.85;
    return {
      x: width * 0.5 + (node.x + driftX + parallaxX) * scale,
      y: height * 0.42 + (node.y + driftY + parallaxY) * scale,
      s: scale,
      a: 0.25 + node.z * 0.7,
    };
  }

  function draw() {
    time += reduceMotion ? 0 : 0.016;
    pointer.x += (pointer.tx - pointer.x) * 0.06;
    pointer.y += (pointer.ty - pointer.y) * 0.06;

    ctx.clearRect(0, 0, width, height);

    // Soft volumetric wash
    const wash = ctx.createRadialGradient(
      width * 0.55,
      height * 0.35,
      40,
      width * 0.5,
      height * 0.45,
      Math.max(width, height) * 0.72
    );
    wash.addColorStop(0, 'rgba(14, 165, 233, 0.12)');
    wash.addColorStop(0.45, 'rgba(13, 148, 136, 0.08)');
    wash.addColorStop(1, 'rgba(2, 44, 54, 0)');
    ctx.fillStyle = wash;
    ctx.fillRect(0, 0, width, height);

    const projected = nodes.map(project);

    // Consent beams between nodes
    for (let i = 0; i < links.length; i++) {
      const [a, b] = links[i];
      const pa = projected[a];
      const pb = projected[b];
      if (!pa || !pb) continue;
      const pulse = 0.25 + 0.35 * Math.sin(time * 1.4 + i);
      ctx.beginPath();
      ctx.moveTo(pa.x, pa.y);
      ctx.lineTo(pb.x, pb.y);
      ctx.strokeStyle = nodes[b].hub || nodes[a].hub
        ? `rgba(125, 211, 252, ${0.18 + pulse * 0.25})`
        : `rgba(45, 212, 191, ${0.08 + pulse * 0.12})`;
      ctx.lineWidth = nodes[a].hub || nodes[b].hub ? 1.4 : 0.8;
      ctx.stroke();
    }

    // Orbit ring around hub
    const hub = projected[0];
    if (hub) {
      ctx.beginPath();
      ctx.ellipse(hub.x, hub.y, 86 + Math.sin(time) * 6, 34, -0.25, 0, Math.PI * 2);
      ctx.strokeStyle = 'rgba(186, 230, 253, 0.28)';
      ctx.lineWidth = 1;
      ctx.stroke();
      ctx.beginPath();
      ctx.ellipse(hub.x, hub.y, 118 + Math.cos(time * 0.8) * 8, 48, 0.2, 0, Math.PI * 2);
      ctx.strokeStyle = 'rgba(45, 212, 191, 0.18)';
      ctx.stroke();
    }

    for (let i = 0; i < nodes.length; i++) {
      const n = nodes[i];
      const p = projected[i];
      const radius = (n.hub ? 7.5 : n.role.r) * p.s;
      const g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, radius * 4);
      g.addColorStop(0, n.role.color + 'cc');
      g.addColorStop(0.35, n.role.color + '55');
      g.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.arc(p.x, p.y, radius * 4, 0, Math.PI * 2);
      ctx.fill();

      ctx.beginPath();
      ctx.arc(p.x, p.y, radius, 0, Math.PI * 2);
      ctx.fillStyle = n.hub ? '#F8FAFC' : n.role.color;
      ctx.globalAlpha = p.a;
      ctx.fill();
      ctx.globalAlpha = 1;

      if (n.hub) {
        ctx.beginPath();
        ctx.arc(p.x, p.y, radius + 5, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(255,255,255,0.55)';
        ctx.lineWidth = 1.5;
        ctx.stroke();
      }
    }

    if (running && !reduceMotion) raf = requestAnimationFrame(draw);
  }

  function onPointer(e) {
    const x = e.clientX ?? (e.touches && e.touches[0]?.clientX) ?? width / 2;
    const y = e.clientY ?? (e.touches && e.touches[0]?.clientY) ?? height / 2;
    pointer.tx = (x / width) * 2 - 1;
    pointer.ty = (y / height) * 2 - 1;
  }

  function onVisibility() {
    if (document.hidden) {
      running = false;
      cancelAnimationFrame(raf);
    } else if (!reduceMotion) {
      running = true;
      raf = requestAnimationFrame(draw);
    }
  }

  resize();
  seedNetwork();
  draw();
  if (!reduceMotion) raf = requestAnimationFrame(draw);

  window.addEventListener('resize', () => {
    resize();
    seedNetwork();
    if (reduceMotion) draw();
  });
  window.addEventListener('pointermove', onPointer, { passive: true });
  window.addEventListener('touchmove', onPointer, { passive: true });
  document.addEventListener('visibilitychange', onVisibility);

  // Depth tilt for interactive UI panels
  document.querySelectorAll('[data-tilt]').forEach((el) => {
    if (reduceMotion) return;
    el.addEventListener('pointermove', (e) => {
      const rect = el.getBoundingClientRect();
      const px = (e.clientX - rect.left) / rect.width - 0.5;
      const py = (e.clientY - rect.top) / rect.height - 0.5;
      el.style.transform =
        `perspective(900px) rotateY(${px * 8}deg) rotateX(${-py * 8}deg) translateY(-4px)`;
    });
    el.addEventListener('pointerleave', () => {
      el.style.transform = '';
    });
  });

  // Reveal sections
  const reveals = document.querySelectorAll('[data-reveal]');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.14, rootMargin: '0px 0px -8% 0px' }
    );
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('is-visible'));
  }
})();
