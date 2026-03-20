/**
 * Global helpers for CP Prompt-X.
 */
window.appUrl = function appUrl(path) {
  const p = path.startsWith('/') ? path : '/' + path;
  const b = typeof window.__APP_BASE__ === 'string' ? window.__APP_BASE__ : '';
  return b + p;
};

function getCsrfToken() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}

/** Prefer the hidden form field (matches PHP session); fallback to meta. */
function readCsrfToken() {
  const inp = document.querySelector('input[name="_csrf"]');
  const v = inp && inp.value ? inp.value.trim() : '';
  return v || getCsrfToken();
}

async function apiDeleteCall(callId) {
  if (!confirm('Delete this call and all related data?')) return;
  const res = await fetch(appUrl('/calls/' + callId), {
    method: 'DELETE',
    headers: {
      Accept: 'application/json',
      'X-CSRF-Token': getCsrfToken(),
    },
    credentials: 'same-origin',
    body: JSON.stringify({ _csrf: getCsrfToken() }),
  });
  if (res.ok) window.location.reload();
  else alert('Delete failed');
}

document.addEventListener('click', (e) => {
  const t = e.target.closest('[data-delete-call]');
  if (t) {
    e.preventDefault();
    apiDeleteCall(t.getAttribute('data-delete-call'));
  }
});
