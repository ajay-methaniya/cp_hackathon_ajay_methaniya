/**
 * Calls list: row navigation, select-all, bulk delete.
 */
(function () {
  const root = document.getElementById('calls-list-page');
  if (!root) return;

  function csrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') || '' : '';
  }

  function selectedIds() {
    return [...root.querySelectorAll('input[data-call-select]:checked')].map((el) => el.value);
  }

  function updateBulkBar() {
    const bar = document.getElementById('calls-bulk-bar');
    const n = selectedIds().length;
    const countEl = document.getElementById('calls-bulk-count');
    if (countEl) countEl.textContent = String(n);
    if (bar) bar.classList.toggle('hidden', n === 0);
  }

  const selectAll = document.getElementById('calls-select-all');
  if (selectAll) {
    selectAll.addEventListener('change', () => {
      const on = selectAll.checked;
      root.querySelectorAll('input[data-call-select]').forEach((el) => {
        el.checked = on;
      });
      selectAll.indeterminate = false;
      updateBulkBar();
    });
  }

  root.querySelectorAll('input[data-call-select]').forEach((el) => {
    el.addEventListener('change', () => {
      const boxes = [...root.querySelectorAll('input[data-call-select]')];
      const n = boxes.length;
      const c = boxes.filter((b) => b.checked).length;
      if (selectAll) {
        selectAll.checked = n > 0 && c === n;
        selectAll.indeterminate = c > 0 && c < n;
      }
      updateBulkBar();
    });
    el.addEventListener('click', (e) => e.stopPropagation());
  });

  root.addEventListener('click', (e) => {
    if (e.target.closest('a, button, input, label')) return;
    const tr = e.target.closest('tr[data-call-row]');
    if (tr && tr.dataset.href) {
      window.location.href = tr.dataset.href;
    }
  });

  const bulkBtn = document.getElementById('calls-bulk-delete');
  if (bulkBtn) {
    bulkBtn.addEventListener('click', async () => {
      const ids = selectedIds();
      if (!ids.length) return;
      if (!confirm('Delete ' + ids.length + ' call(s) and all related data? This cannot be undone.')) return;
      bulkBtn.disabled = true;
      try {
        const res = await fetch(appUrl('/calls/bulk-delete'), {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf(),
          },
          credentials: 'same-origin',
          body: JSON.stringify({ _csrf: csrf(), ids }),
        });
        if (!res.ok) {
          const j = await res.json().catch(() => ({}));
          alert((j && j.error) || 'Delete failed');
          bulkBtn.disabled = false;
          return;
        }
        window.location.reload();
      } catch (err) {
        console.error(err);
        alert('Network error');
        bulkBtn.disabled = false;
      }
    });
  }

  updateBulkBar();
})();
