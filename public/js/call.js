/**
 * Call detail: charts, audio, waveform seek, transcript tools, notes, follow-ups.
 */
(function () {
  const page = window.__CALL_PAGE;
  if (!page) return;

  const csrf = () => (typeof getCsrfToken === 'function' ? getCsrfToken() : '');

  if (page.status && page.status !== 'complete' && page.status !== 'failed') {
    setInterval(async () => {
      try {
        const r = await fetch(appUrl('/api/calls/' + page.id + '/status'), {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        const d = await r.json();
        if (d.status === 'complete') window.location.reload();
        if (d.status === 'failed') {
          const b = document.getElementById('processing-banner');
          if (b) {
            b.textContent = d.error ? 'Processing failed: ' + d.error : 'Processing failed. Check storage/logs/app.log or re-upload.';
          }
        }
      } catch {
        /* ignore */
      }
    }, 3000);
  }

  const audio = document.getElementById('call-audio');
  const rate = document.getElementById('audio-rate');
  const timeEl = document.getElementById('audio-time');
  const fallbackDur =
    page.audio_duration_seconds != null && page.audio_duration_seconds > 0 ? page.audio_duration_seconds : 0;

  function audioDuration() {
    if (!audio) return 0;
    const raw = audio.duration;
    let dur = Number.isFinite(raw) && raw > 0 ? raw : 0;
    if (dur <= 0 && fallbackDur > 0) dur = fallbackDur;
    return dur;
  }

  if (audio && rate) {
    rate.addEventListener('change', () => {
      audio.playbackRate = parseFloat(rate.value) || 1;
    });
    const fmt = (s) => {
      if (!Number.isFinite(s) || s < 0) return '0:00';
      const m = Math.floor(s / 60);
      const sec = Math.floor(s % 60);
      return m + ':' + String(sec).padStart(2, '0');
    };
    const tick = () => {
      if (!timeEl) return;
      const cur = Number.isFinite(audio.currentTime) ? audio.currentTime : 0;
      const dur = audioDuration();
      timeEl.textContent = fmt(cur) + ' / ' + (dur > 0 ? fmt(dur) : '—');
    };
    audio.addEventListener('timeupdate', tick);
    audio.addEventListener('loadedmetadata', tick);
    audio.addEventListener('durationchange', tick);
    audio.addEventListener('canplay', tick);
  }

  const wf = document.getElementById('waveform-bars');
  if (wf && audio) {
    wf.addEventListener('click', (e) => {
      const dur = audioDuration();
      if (dur <= 0) return;
      const rect = wf.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const t = (x / rect.width) * dur;
      audio.currentTime = Math.max(0, Math.min(dur, t));
      audio.play().catch(() => {});
    });
  }

  document.querySelectorAll('.seek-to').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!audio) return;
      const t = parseFloat(btn.getAttribute('data-seek') || '0');
      if (!Number.isFinite(t)) return;
      audio.currentTime = Math.max(0, t);
      audio.play().catch(() => {});
      const scroll = document.getElementById('transcript-scroll');
      if (scroll) {
        btn.closest('.transcript-block')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.code !== 'Space') return;
    const tag = (e.target && e.target.tagName) || '';
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target.isContentEditable) return;
    if (!audio) return;
    e.preventDefault();
    if (audio.paused) audio.play().catch(() => {});
    else audio.pause();
  });

  const summaryJson = document.getElementById('call-summary-json');
  const copySummaryBtn = document.getElementById('copy-summary-btn');
  if (copySummaryBtn && summaryJson) {
    const copySummaryLabel = document.getElementById('copy-summary-label');
    copySummaryBtn.addEventListener('click', async () => {
      try {
        const text = JSON.parse(summaryJson.textContent || '""');
        await navigator.clipboard.writeText(text);
        if (copySummaryLabel) copySummaryLabel.textContent = 'Copied!';
        setTimeout(() => {
          if (copySummaryLabel) copySummaryLabel.textContent = 'Copy summary';
        }, 2000);
      } catch {
        if (copySummaryLabel) copySummaryLabel.textContent = 'Copy failed';
      }
    });
  }

  let transcriptBlocks = [];
  const tbEl = document.getElementById('transcript-blocks-json');
  if (tbEl) {
    try {
      transcriptBlocks = JSON.parse(tbEl.textContent || '[]');
    } catch {
      transcriptBlocks = [];
    }
  }

  const copyTxBtn = document.getElementById('copy-transcript-btn');
  if (copyTxBtn) {
    copyTxBtn.addEventListener('click', async () => {
      const lines = transcriptBlocks.map((b) => {
        const lab = b.label ? b.label + ' — ' : '';
        return lab + (b.text || '');
      });
      const text = lines.join('\n\n');
      try {
        await navigator.clipboard.writeText(text || document.getElementById('transcript-scroll')?.innerText || '');
        copyTxBtn.textContent = 'Copied';
        setTimeout(() => {
          copyTxBtn.textContent = 'Copy all';
        }, 2000);
      } catch {
        copyTxBtn.textContent = 'Failed';
      }
    });
  }

  const filterInp = document.getElementById('transcript-filter');
  if (filterInp) {
    filterInp.addEventListener('input', () => {
      const q = filterInp.value.trim().toLowerCase();
      document.querySelectorAll('.transcript-block').forEach((el) => {
        const body = el.querySelector('.cx-msg-body');
        const t = (body && body.textContent ? body.textContent : '').toLowerCase();
        el.style.display = !q || t.includes(q) ? '' : 'none';
      });
    });
  }

  function escapeRe(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderTranscriptBodies(highlightKw) {
    const blocks = document.querySelectorAll('.transcript-block');
    blocks.forEach((el, idx) => {
      const b = transcriptBlocks[idx];
      if (!b) return;
      const body = el.querySelector('.cx-msg-body');
      if (!body) return;
      let html = escapeHtml(b.text || '').replace(/\n/g, '<br>');
      if (highlightKw) {
        const re = new RegExp('(' + escapeRe(highlightKw) + ')', 'gi');
        html = html.replace(re, '<mark>$1</mark>');
      }
      body.innerHTML = html;
      el.classList.remove('ring-2', 'ring-brand-400/60');
      if (highlightKw && (b.text || '').toLowerCase().includes(highlightKw.toLowerCase())) {
        el.classList.add('ring-2', 'ring-brand-400/60');
      }
    });
  }

  document.querySelectorAll('.keyword-tag').forEach((btn) => {
    btn.addEventListener('click', () => {
      const kw = (btn.getAttribute('data-keyword') || '').trim();
      if (!kw) return;
      const tabBtn = document.getElementById('tab-btn-summary');
      if (tabBtn) tabBtn.click();
      renderTranscriptBodies(kw);
      const first = document.querySelector('.transcript-block.ring-2');
      if (first) {
        setTimeout(() => first.scrollIntoView({ behavior: 'smooth', block: 'center' }), 80);
      }
    });
  });

  const evoEl = document.getElementById('sentiment-evolution-data');
  if (evoEl && typeof Chart !== 'undefined') {
    let data = [];
    try {
      data = JSON.parse(evoEl.textContent || '[]');
    } catch {
      data = [];
    }
    if (!Array.isArray(data)) data = [];
    const canvas = document.getElementById('chart-sentiment-evo');
    if (canvas && data.length) {
      const labels = data.map((p) => {
        const ts = p.time_seconds != null ? Number(p.time_seconds) : 0;
        return (ts / 60).toFixed(1) + 'm';
      });
      const scores = data.map((p) => Number(p.score ?? 0));
      const excerpts = data.map((p) => String(p.excerpt ?? ''));

      const pointColors = scores.map((v) => {
        if (v > 0.15) return 'rgba(74,222,128,0.95)';
        if (v < -0.15) return 'rgba(248,113,113,0.95)';
        return 'rgba(251,191,36,0.95)';
      });

      const dataset = {
        label: 'Sentiment',
        data: scores,
        borderColor: 'rgba(96,165,250,0.95)',
        backgroundColor: 'transparent',
        fill: false,
        tension: 0.35,
        pointRadius: 4,
        pointBackgroundColor: pointColors,
        pointBorderColor: 'rgba(15,17,23,0.9)',
        pointBorderWidth: 1,
        segment: {
          borderColor: (ctx) => {
            const i = ctx.p0DataIndex;
            const a = scores[i];
            const b = scores[i + 1];
            if (a == null || b == null) return 'rgba(96,165,250,0.7)';
            const mid = (a + b) / 2;
            if (mid > 0.1) return 'rgba(74,222,128,0.55)';
            if (mid < -0.1) return 'rgba(248,113,113,0.55)';
            return 'rgba(96,165,250,0.75)';
          },
        },
      };

      try {
        new Chart(canvas, {
          type: 'line',
          data: { labels, datasets: [dataset] },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  afterBody: (items) => {
                    const i = items[0]?.dataIndex;
                    if (i == null) return '';
                    return excerpts[i] ? '\n' + excerpts[i] : '';
                  },
                },
              },
            },
            scales: {
              x: { grid: { display: false }, title: { display: true, text: 'Time', color: '#64748b' } },
              y: {
                min: -1,
                max: 1,
                grid: { color: 'rgba(255,255,255,0.06)' },
                ticks: { stepSize: 0.5, color: '#64748b' },
              },
            },
          },
        });
      } catch (err) {
        delete dataset.segment;
        try {
          new Chart(canvas, {
            type: 'line',
            data: { labels, datasets: [dataset] },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false } },
              scales: {
                x: { grid: { display: false } },
                y: { min: -1, max: 1, grid: { color: 'rgba(255,255,255,0.06)' } },
              },
            },
          });
        } catch (e2) {
          console.warn('Sentiment chart failed', e2);
        }
      }
    }
  }

  document.querySelectorAll('#followup-list input[type=checkbox]').forEach((cb) => {
    cb.addEventListener('change', async () => {
      const idx = cb.getAttribute('data-followup-idx');
      const res = await fetch(appUrl('/calls/' + page.id + '/followups/' + idx), {
        method: 'PATCH',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrf(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ _csrf: csrf() }),
      });
      if (res.ok) window.location.reload();
    });
  });

  const mf = document.getElementById('manual-followup-form');
  if (mf) {
    mf.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(mf);
      const res = await fetch(appUrl('/calls/' + page.id + '/followups'), {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() },
        credentials: 'same-origin',
        body: fd,
      });
      if (res.ok) window.location.reload();
    });
  }

  const nf = document.getElementById('note-form');
  if (nf) {
    nf.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(nf);
      const res = await fetch(appUrl('/calls/' + page.id + '/notes'), {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() },
        credentials: 'same-origin',
        body: fd,
      });
      if (res.ok) window.location.reload();
    });
  }
})();
