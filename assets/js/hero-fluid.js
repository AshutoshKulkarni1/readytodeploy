/**
 * Fluid Motion Graphics for LMS Portal Login Hero
 * Single silky harmonic wave, darker subtle constellation background,
 * and smooth damped parallax motion responsiveness on the centered card.
 */
(function() {
  'use strict';

  var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var canvas = document.getElementById('heroFluidCanvas');
  if (!canvas) return;

  var ctx = canvas.getContext('2d');
  if (!ctx) return;

  var hero = canvas.closest('.auth-hero');
  if (!hero) return;

  var showcaseCard = hero.querySelector('.hero-glass-card');

  var width = 0;
  var height = 0;
  var dpr = 1;
  var animFrameId = null;
  var isVisible = true;
  var time = 0;

  // Mouse interaction state
  var mouse = {
    x: -1000,
    y: -1000,
    targetX: -1000,
    targetY: -1000,
    active: false,
    radius: 180,
    force: 0
  };

  // Card motion lerp state
  var currentTiltX = 0, currentTiltY = 0;
  var targetTiltX = 0, targetTiltY = 0;
  var currentTransX = 0, currentTransY = 0;
  var targetTransX = 0, targetTransY = 0;

  // Responsive canvas resize with HiDPI support
  function resize() {
    var rect = hero.getBoundingClientRect();
    width = rect.width;
    height = rect.height;
    if (width === 0 || height === 0) return;

    dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';
    ctx.scale(dpr, dpr);
  }

  // Exactly ONE silky smooth fluid wave
  var wave = {
    baseY: 0.74,
    amplitude: 34,
    frequency: 0.0024,
    speed: 0.0016,
    colorStart: 'rgba(20, 55, 130, 0.28)',
    colorEnd: 'rgba(10, 25, 65, 0.06)',
    lineColor: 'rgba(96, 165, 250, 0.42)',
    lineWidth: 2
  };

  // Subtle dark constellation dots & connecting threads
  var particleCount = 28;
  var particles = [];

  function initParticles() {
    particles = [];
    var w = width || 800;
    var h = height || 600;
    for (var i = 0; i < particleCount; i++) {
      particles.push({
        x: Math.random() * w,
        y: Math.random() * h,
        size: Math.random() * 2 + 1.2,
        speedX: (Math.random() - 0.5) * 0.35 + 0.1,
        speedY: (Math.random() - 0.5) * 0.25 - 0.08,
        alpha: Math.random() * 0.35 + 0.25,
        pulseSpeed: Math.random() * 0.025 + 0.015,
        pulseVal: Math.random() * Math.PI * 2
      });
    }
  }

  // Smooth mouse event listeners
  hero.addEventListener('mousemove', function(e) {
    var rect = hero.getBoundingClientRect();
    mouse.targetX = e.clientX - rect.left;
    mouse.targetY = e.clientY - rect.top;
    mouse.active = true;
    mouse.force = 1;

    // Card motion responsiveness
    if (window.innerWidth >= 1024) {
      var relX = (e.clientX - rect.left) / rect.width - 0.5;
      var relY = (e.clientY - rect.top) / rect.height - 0.5;
      targetTiltX = -relY * 5;  // Subtle 2.5 deg max tilt
      targetTiltY = relX * 5;
      targetTransX = relX * 10; // Subtle 5px max translation
      targetTransY = relY * 10;
    }
  }, { passive: true });

  hero.addEventListener('mouseleave', function() {
    mouse.active = false;
    targetTiltX = 0;
    targetTiltY = 0;
    targetTransX = 0;
    targetTransY = 0;
  }, { passive: true });

  // Main 60fps render loop
  function render() {
    if (!isVisible) return;

    time += 1;

    // Smooth mouse position damping
    if (mouse.active) {
      mouse.x += (mouse.targetX - mouse.x) * 0.08;
      mouse.y += (mouse.targetY - mouse.y) * 0.08;
    } else {
      mouse.force *= 0.94;
    }

    // Smooth card motion lerp
    if (showcaseCard && window.innerWidth >= 1024) {
      currentTiltX += (targetTiltX - currentTiltX) * 0.06;
      currentTiltY += (targetTiltY - currentTiltY) * 0.06;
      currentTransX += (targetTransX - currentTransX) * 0.06;
      currentTransY += (targetTransY - currentTransY) * 0.06;

      if (Math.abs(currentTiltX) > 0.02 || Math.abs(currentTiltY) > 0.02 || Math.abs(currentTransX) > 0.02) {
        showcaseCard.style.transform = 'perspective(900px) rotateX(' + currentTiltX.toFixed(2) + 'deg) rotateY(' + currentTiltY.toFixed(2) + 'deg) translate3d(' + currentTransX.toFixed(1) + 'px, ' + currentTransY.toFixed(1) + 'px, 0)';
      } else {
        showcaseCard.style.transform = 'none';
      }
    }

    ctx.clearRect(0, 0, width, height);

    // 1. Draw subtle, darker constellation lines & dots in background
    for (var i = 0; i < particles.length; i++) {
      var p = particles[i];
      p.x += p.speedX;
      p.y += p.speedY;
      p.pulseVal += p.pulseSpeed;

      if (p.x < -10) p.x = width + 10;
      if (p.x > width + 10) p.x = -10;
      if (p.y < -10) p.y = height + 10;
      if (p.y > height + 10) p.y = -10;

      // Soft mouse deflection on particles
      if (mouse.active) {
        var mdx = p.x - mouse.x;
        var mdy = p.y - mouse.y;
        var mdist = Math.sqrt(mdx * mdx + mdy * mdy);
        if (mdist < 100 && mdist > 0) {
          p.x += (mdx / mdist) * 1.2;
          p.y += (mdy / mdist) * 1.2;
        }
      }

      var currentAlpha = p.alpha * (0.8 + 0.2 * Math.sin(p.pulseVal));

      // Slightly dark nodes in background
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(8, 26, 68, ' + currentAlpha + ')';
      ctx.fill();

      // Slightly dark connecting threads between proximate dots
      for (var j = i + 1; j < particles.length; j++) {
        var p2 = particles[j];
        var cdx = p.x - p2.x;
        var cdy = p.y - p2.y;
        var cdist = Math.sqrt(cdx * cdx + cdy * cdy);
        if (cdist < 80) {
          ctx.beginPath();
          ctx.moveTo(p.x, p.y);
          ctx.lineTo(p2.x, p2.y);
          ctx.strokeStyle = 'rgba(8, 26, 68, ' + (0.22 * (1 - cdist / 80)) + ')';
          ctx.lineWidth = 0.85;
          ctx.stroke();
        }
      }
    }

    // 2. Render the single silky smooth harmonic wave
    ctx.beginPath();
    var cy = height * wave.baseY;
    ctx.moveTo(0, height);
    ctx.lineTo(0, cy);

    var step = 6;
    for (var x = 0; x <= width; x += step) {
      var y = cy +
        Math.sin(x * wave.frequency + time * wave.speed) * wave.amplitude +
        Math.sin(x * (wave.frequency * 0.55) + time * (wave.speed * 0.7) + 1.2) * (wave.amplitude * 0.35);

      // Smooth fluid ripple disturbance from cursor
      if (mouse.active || mouse.force > 0.01) {
        var dx = x - mouse.x;
        var dy = y - mouse.y;
        var dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < mouse.radius) {
          var push = (1 - dist / mouse.radius) * 22 * mouse.force;
          y += Math.sin(dist * 0.05 - time * 0.06) * push;
        }
      }

      ctx.lineTo(x, y);
    }

    ctx.lineTo(width, height);
    ctx.closePath();

    // Smooth vertical gradient fill
    var grad = ctx.createLinearGradient(0, cy - wave.amplitude, 0, height);
    grad.addColorStop(0, wave.colorStart);
    grad.addColorStop(1, wave.colorEnd);
    ctx.fillStyle = grad;
    ctx.fill();

    // Silky wave crest stroke
    ctx.strokeStyle = wave.lineColor;
    ctx.lineWidth = wave.lineWidth;
    ctx.stroke();

    if (!prefersReducedMotion) {
      animFrameId = requestAnimationFrame(render);
    }
  }

  // Throttled window resize
  var resizeTimeout;
  window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(function() {
      resize();
      initParticles();
    }, 150);
  });

  // Preserve battery when inactive
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
      isVisible = false;
      if (animFrameId) cancelAnimationFrame(animFrameId);
    } else {
      isVisible = true;
      if (!prefersReducedMotion) animFrameId = requestAnimationFrame(render);
    }
  });

  resize();
  initParticles();
  render();
})();
