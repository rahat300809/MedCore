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
})();
