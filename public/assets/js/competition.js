(function () {
  const btn = document.getElementById('mc-menu-btn');
  const menu = document.getElementById('mc-menu');
  if (btn && menu) {
    btn.addEventListener('click', function () {
      const open = menu.hasAttribute('hidden');
      if (open) {
        menu.removeAttribute('hidden');
        menu.classList.add('open');
      } else {
        menu.setAttribute('hidden', '');
        menu.classList.remove('open');
      }
    });
  }

  const railToggle = document.getElementById('mcRailToggle');
  const railBody = document.getElementById('mcRailBody');
  if (railToggle && railBody) {
    railToggle.addEventListener('click', function () {
      const isOpen = railBody.classList.toggle('open');
      railToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  // Scroll reveal — calm entrance for public sections
  const nodes = document.querySelectorAll('.mc-reveal');
  if (!nodes.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    nodes.forEach(function (el) { el.classList.add('is-in'); });
  } else if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -10% 0px' });
    nodes.forEach(function (el) { io.observe(el); });
    // Above-fold / already visible: show immediately
    requestAnimationFrame(function () {
      nodes.forEach(function (el) {
        const rect = el.getBoundingClientRect();
        if (rect.top < window.innerHeight * 0.92) el.classList.add('is-in');
      });
    });
  } else {
    nodes.forEach(function (el) { el.classList.add('is-in'); });
  }

  // Nav solidifies slightly after scroll
  const nav = document.getElementById('mc-nav');
  if (nav) {
    const onScroll = function () {
      nav.classList.toggle('is-scrolled', window.scrollY > 12);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }
})();
