/**
 * Global UI preferences (theme + locale).
 */
(function () {
  const themeSel = document.getElementById('pref-theme');
  const localeSel = document.getElementById('pref-language');
  const saveEl = document.getElementById('pref-save-indicator');

  if (!themeSel && !localeSel) return;

  function csrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') || '' : '';
  }

  function showSaved(ok) {
    if (!saveEl) return;
    saveEl.textContent = ok ? (saveEl.textContent || 'Saved') : 'Failed';
    saveEl.classList.remove('hidden');
    clearTimeout(showSaved._t);
    showSaved._t = setTimeout(() => saveEl.classList.add('hidden'), 1400);
  }

  async function pushPrefs() {
    const body = {
      _csrf: csrf(),
    };
    if (themeSel) body.theme = themeSel.value;
    if (localeSel) body.locale = localeSel.value;

    const res = await fetch(appUrl('/preferences'), {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      showSaved(false);
      return;
    }
    showSaved(true);
    window.location.reload();
  }

  if (themeSel) {
    themeSel.addEventListener('change', () => {
      const v = themeSel.value === 'light' ? 'theme-light' : 'theme-dark';
      document.body.classList.remove('theme-light', 'theme-dark');
      document.body.classList.add(v);
      pushPrefs().catch(() => showSaved(false));
    });
  }
  if (localeSel) {
    localeSel.addEventListener('change', () => {
      pushPrefs().catch(() => showSaved(false));
    });
  }
})();

