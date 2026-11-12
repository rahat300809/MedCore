(function () {
  const btn = document.getElementById('mc-menu-btn');
  const menu = document.getElementById('mc-menu');
  if (!btn || !menu) return;

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
})();
