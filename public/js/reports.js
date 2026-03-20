/**
 * Reports hub: charts for languages, playbook, weekly sentiment, monthly volume.
 * Uses the current URL query string (same defaults as PHP) — no Alpine on the filter form.
 */
(function () {
  const charts = {};

  function fmtDur(sec) {
    if (sec == null || sec === '') return '—';
    const n = Number(sec);
    if (Number.isNaN(n)) return '—';
    const m = Math.floor(n / 60);
    const s = Math.floor(n % 60);
    return m + 'm ' + String(s).padStart(2, '0') + 's';
  }

  function fmtHours(h) {
    if (h == null || h === '') return '—';
    return Number(h).toFixed(1) + ' h';
  }

  function queryFromLocation() {
    const s = window.location.search;
    return s && s !== '?' ? s : '';
  }

  function weekLabel(iso) {
    if (!iso) return '';
    const d = new Date(String(iso).slice(0, 10) + 'T12:00:00');
    if (Number.isNaN(d.getTime())) return String(iso);
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function monthLabel(ym) {
    if (!ym || ym.length < 7) return String(ym || '');
    const d = new Date(ym + '-01T12:00:00');
    if (Number.isNaN(d.getTime())) return ym;
    return d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
  }

  function setVisible(el, show) {
    if (!el) return;
    el.classList.toggle('hidden', !show);
  }

  function renderKpis(data) {
    const t = data.totals || {};
    const el = (id, text) => {
      const n = document.getElementById(id);
      if (n) n.textContent = text;
    };
    el('kpi-analyzed', t.analyzed_calls != null ? String(t.analyzed_calls) : '—');
    el('kpi-avglen', fmtDur(t.avg_duration_seconds));
    el('kpi-talk', fmtHours(t.total_talk_time_hours));

    const main = document.getElementById('kpi-lang-main');
    const sub = document.getElementById('kpi-lang-sub');
    const langs = data.languages || [];
    const nLang = t.language_count != null ? Number(t.language_count) : langs.length;
    const dom = t.dominant_language;
    const domPct = t.dominant_language_pct;

    if (main) {
      if (dom && String(dom).trim() !== '') {
        main.textContent = dom;
      } else if (nLang > 0) {
        main.textContent = String(nLang) + (nLang === 1 ? ' language' : ' languages');
      } else {
        main.textContent = '—';
      }
    }
    if (sub) {
      if (dom && domPct != null && !Number.isNaN(Number(domPct))) {
        const extra = nLang > 1 ? ' · ' + nLang + ' total' : '';
        sub.textContent = '~' + String(domPct) + '% of transcripts' + extra;
      } else if (nLang > 0) {
        sub.textContent = String(nLang) + (nLang === 1 ? ' language in mix' : ' languages detected');
      } else {
        sub.textContent = 'No transcript language in this filter (uploads still count in other KPIs).';
      }
    }
  }

  function renderCoaching(items) {
    const ul = document.getElementById('reports-coaching-list');
    if (!ul) return;
    ul.replaceChildren();
    if (!items || !items.length) {
      const li = document.createElement('li');
      li.className = 'text-slate-500 py-1';
      li.textContent =
        'No weak spots detected — add more calls with playbook coverage, or scores are even across questions.';
      ul.appendChild(li);
      return;
    }
    for (const x of items) {
      const li = document.createElement('li');
      li.className =
        'flex flex-wrap items-center justify-between gap-3 rounded-lg border border-surface-border/70 bg-slate-900/40 px-3 py-2.5';
      const left = document.createElement('div');
      left.className = 'min-w-0';
      const title = document.createElement('p');
      title.className = 'font-medium text-white';
      title.textContent = String(x.question_id ?? '');
      const stage = (x.stage && String(x.stage).trim()) || '';
      if (stage) {
        const st = document.createElement('p');
        st.className = 'text-xs text-slate-500 mt-0.5';
        st.textContent = stage;
        left.appendChild(title);
        left.appendChild(st);
      } else {
        left.appendChild(title);
      }
      const right = document.createElement('div');
      right.className = 'text-right shrink-0';
      const score = document.createElement('p');
      score.className = 'font-mono text-brand-400';
      score.textContent = x.avg_score != null ? Number(x.avg_score).toFixed(1) + '% avg' : '—';
      const n = document.createElement('p');
      n.className = 'text-[10px] text-slate-500 mt-0.5';
      n.textContent = (x.samples != null ? String(x.samples) : '0') + ' samples';
      right.appendChild(score);
      right.appendChild(n);
      li.appendChild(left);
      li.appendChild(right);
      ul.appendChild(li);
    }
  }

  function renderAgentTable(agents) {
    const tbody = document.getElementById('reports-agent-tbody');
    if (!tbody) return;
    tbody.replaceChildren();
    if (!agents || !agents.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 5;
      td.className = 'py-6 text-slate-500';
      td.textContent = 'No data for this filter.';
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }
    for (const row of agents) {
      const tr = document.createElement('tr');
      tr.className = 'text-slate-300';
      const cells = [
        { text: row.agent_name ?? '', className: 'py-2 pr-4 font-medium text-white' },
        { text: String(row.call_count ?? ''), className: 'py-2 pr-4' },
        {
          text: row.avg_sentiment != null ? Number(row.avg_sentiment).toFixed(2) : '—',
          className: 'py-2 pr-4 font-mono',
        },
        {
          text: row.avg_confidence != null ? Number(row.avg_confidence).toFixed(0) + '%' : '—',
          className: 'py-2 pr-4',
        },
        {
          text: row.avg_playbook_pct != null ? Number(row.avg_playbook_pct).toFixed(1) + '%' : '—',
          className: 'py-2',
        },
      ];
      for (const c of cells) {
        const td = document.createElement('td');
        td.className = c.className;
        td.textContent = c.text;
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    }
  }

  function renderCharts(data) {
    if (typeof Chart === 'undefined') {
      console.warn('Chart.js not loaded; reports charts skipped.');
      return;
    }

    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'transparent';

    const langs = data.languages || [];
    const langWrap = document.getElementById('chart-languages')?.parentElement;
    const langCtx = document.getElementById('chart-languages');
    const langEmpty = document.getElementById('chart-languages-empty');
    charts.lang?.destroy();
    charts.lang = null;
    if (langCtx) {
      if (!langs.length) {
        setVisible(langWrap, false);
        setVisible(langEmpty, true);
      } else {
        setVisible(langWrap, true);
        setVisible(langEmpty, false);
        charts.lang = new Chart(langCtx, {
          type: 'doughnut',
          data: {
            labels: langs.map((l) => l.label || l.language),
            datasets: [
              {
                data: langs.map((l) => l.count),
                backgroundColor: [
                  '#4f6ef7',
                  '#22c55e',
                  '#f59e0b',
                  '#ef4444',
                  '#a855f7',
                  '#06b6d4',
                  '#ec4899',
                  '#64748b',
                ],
                borderWidth: 0,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '58%',
            plugins: {
              legend: { position: 'right', labels: { boxWidth: 10 } },
              tooltip: {
                callbacks: {
                  label(ctx) {
                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                    const v = ctx.raw;
                    const pct = total ? Math.round((100 * v) / total) : 0;
                    return (ctx.label || '') + ': ' + v + ' (' + pct + '%)';
                  },
                },
              },
            },
          },
        });
      }
    }

    const heat = data.playbook_heatmap || [];
    const heatWithSamples = heat.filter((h) => (h.samples ?? 0) > 0);
    const pbCtx = document.getElementById('chart-playbook');
    const pbWrap = pbCtx?.parentElement;
    const pbEmpty = document.getElementById('chart-playbook-empty');
    charts.pb?.destroy();
    charts.pb = null;
    if (pbCtx) {
      if (!heatWithSamples.length) {
        setVisible(pbWrap, false);
        setVisible(pbEmpty, true);
      } else {
        setVisible(pbWrap, true);
        setVisible(pbEmpty, false);
        charts.pb = new Chart(pbCtx, {
          type: 'bar',
          data: {
            labels: heatWithSamples.map((h) => h.question_id),
            datasets: [
              {
                label: 'Avg coverage %',
                data: heatWithSamples.map((h) => h.avg_score),
                backgroundColor: heatWithSamples.map((h) => {
                  const v = h.avg_score;
                  if (v >= 70) return 'rgba(34,197,94,0.75)';
                  if (v >= 40) return 'rgba(245,158,11,0.75)';
                  return 'rgba(239,68,68,0.55)';
                }),
                borderRadius: 4,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
              x: { min: 0, max: 100, grid: { color: 'rgba(42,45,62,0.5)' } },
              y: { grid: { display: false } },
            },
          },
        });
      }
    }

    const wk = data.weekly_sentiment || [];
    const wCtx = document.getElementById('chart-weekly');
    const wWrap = wCtx?.parentElement;
    const wEmpty = document.getElementById('chart-weekly-empty');
    charts.w?.destroy();
    charts.w = null;
    if (wCtx) {
      const hasPoints = wk.some((x) => x.avg_sentiment != null && (x.calls ?? 0) > 0);
      if (!wk.length || !hasPoints) {
        setVisible(wWrap, false);
        setVisible(wEmpty, true);
      } else {
        setVisible(wWrap, true);
        setVisible(wEmpty, false);
        const pr = wk.length <= 2 ? 6 : wk.length <= 4 ? 4 : 3;
        charts.w = new Chart(wCtx, {
          type: 'line',
          data: {
            labels: wk.map((x) => weekLabel(x.week)),
            datasets: [
              {
                label: 'Avg sentiment',
                data: wk.map((x) => x.avg_sentiment),
                borderColor: '#4f6ef7',
                backgroundColor: 'rgba(79,110,247,0.15)',
                fill: true,
                tension: wk.length <= 2 ? 0.1 : 0.35,
                pointRadius: pr,
                pointHoverRadius: pr + 2,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  afterLabel(ctx) {
                    const i = ctx.dataIndex;
                    const row = wk[i];
                    if (row && row.calls != null) return row.calls + ' calls';
                    return '';
                  },
                },
              },
            },
            scales: {
              y: { min: -1, max: 1, grid: { color: 'rgba(42,45,62,0.5)' } },
              x: { grid: { display: false }, ticks: { maxRotation: 45 } },
            },
          },
        });
      }
    }

    const sm = data.sentiment_trend || [];
    const smCtx = document.getElementById('chart-sentiment-month');
    const smWrap = smCtx?.parentElement;
    const smEmpty = document.getElementById('chart-sentiment-empty');
    charts.sm?.destroy();
    charts.sm = null;
    if (smCtx) {
      const smNonZero = sm.filter((x) => (x.positive || 0) + (x.neutral || 0) + (x.negative || 0) > 0);
      if (!smNonZero.length) {
        setVisible(smWrap, false);
        setVisible(smEmpty, true);
      } else {
        setVisible(smWrap, true);
        setVisible(smEmpty, false);
        charts.sm = new Chart(smCtx, {
          type: 'bar',
          data: {
            labels: smNonZero.map((x) => monthLabel(x.month)),
            datasets: [
              { label: 'Positive', data: smNonZero.map((x) => x.positive), backgroundColor: 'rgba(34,197,94,0.85)', borderRadius: 4 },
              { label: 'Neutral', data: smNonZero.map((x) => x.neutral), backgroundColor: 'rgba(245,158,11,0.75)', borderRadius: 4 },
              { label: 'Negative', data: smNonZero.map((x) => x.negative), backgroundColor: 'rgba(239,68,68,0.75)', borderRadius: 4 },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
              x: { stacked: true, grid: { display: false } },
              y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(42,45,62,0.5)' } },
            },
          },
        });
      }
    }
  }

  async function load() {
    const qs = queryFromLocation();
    try {
      const res = await fetch(appUrl('/api/reports/overview') + qs, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      const data = await res.json();
      renderKpis(data);
      renderCoaching(data.playbook_focus || []);
      renderAgentTable(data.agents || []);
      renderCharts(data);
    } catch (e) {
      console.error(e);
      const ul = document.getElementById('reports-coaching-list');
      if (ul) {
        ul.replaceChildren();
        const li = document.createElement('li');
        li.className = 'text-rose-400 text-sm';
        li.textContent = 'Could not load reports. Refresh or check your connection.';
        ul.appendChild(li);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();
