(() => {
  const key = `asset-scroll:${location.pathname}`;
  let restored = false;

  function save() {
    if (restored) sessionStorage.setItem(key, String(window.scrollY));
  }

  const saved = Number(sessionStorage.getItem(key));
  requestAnimationFrame(() => requestAnimationFrame(() => {
    if (Number.isFinite(saved) && saved >= 0) window.scrollTo({top: saved, left: 0, behavior: 'auto'});
    restored = true;
  }));

  document.addEventListener('submit', save, true);
  document.addEventListener('click', event => {
    if (event.target.closest('a, button, input[type="checkbox"]')) save();
  }, true);
  window.addEventListener('beforeunload', save);
  window.addEventListener('pagehide', save);
})();
