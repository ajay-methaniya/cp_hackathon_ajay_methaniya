/**
 * Dashboard: KPIs (Alpine), filters, saved sets. Charts are server-rendered HTML (see dashboard.index).
 */
document.addEventListener('alpine:init', () => {
  Alpine.data('dashboardPage', (initialFilters) => ({
    stats: {},
    keywords: [],
    filterModal: false,
    saveName: '',
    savedSets: [],
    filters: {
      date_from: initialFilters.date_from || '',
      date_to: initialFilters.date_to || '',
      agent_id: initialFilters.agent_id || '',
      sentiment: initialFilters.sentiment || '',
      min_duration: initialFilters.min_duration || '',
      max_duration: initialFilters.max_duration || '',
    },
    queryString() {
      const p = new URLSearchParams();
      Object.entries(this.filters).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) p.set(k, v);
      });
      const s = p.toString();
      return s ? '?' + s : '';
    },
    activeChips() {
      const chips = [];
      if (this.filters.date_from) chips.push({ key: 'date_from', label: 'From ' + this.filters.date_from, field: 'date_from' });
      if (this.filters.date_to) chips.push({ key: 'date_to', label: 'To ' + this.filters.date_to, field: 'date_to' });
      if (this.filters.agent_id) chips.push({ key: 'agent', label: 'Agent', field: 'agent_id' });
      if (this.filters.sentiment) chips.push({ key: 'sent', label: this.filters.sentiment, field: 'sentiment' });
      if (this.filters.min_duration) chips.push({ key: 'min', label: 'Min ' + this.filters.min_duration + 's', field: 'min_duration' });
      if (this.filters.max_duration) chips.push({ key: 'max', label: 'Max ' + this.filters.max_duration + 's', field: 'max_duration' });
      return chips;
    },
    removeChip(chip) {
      this.filters[chip.field] = '';
      window.location.href = appUrl('/dashboard') + this.queryString();
    },
    loadSaved(s) {
      const f = s.filters || {};
      this.filters = {
        date_from: f.date_from || '',
        date_to: f.date_to || '',
        agent_id: f.agent_id || '',
        sentiment: f.sentiment || '',
        min_duration: f.min_duration || '',
        max_duration: f.max_duration || '',
      };
      window.location.href = appUrl('/dashboard') + this.queryString();
    },
    removeSaved(idx) {
      this.savedSets.splice(idx, 1);
      localStorage.setItem('cp_promptx_filters', JSON.stringify(this.savedSets));
    },
    saveCurrentSet() {
      if (!this.saveName.trim()) return;
      this.savedSets.push({ name: this.saveName.trim(), filters: { ...this.filters } });
      localStorage.setItem('cp_promptx_filters', JSON.stringify(this.savedSets));
      this.saveName = '';
    },
    highlightTop() {
      document.querySelectorAll('[data-score="positive"]').forEach((el) => {
        el.classList.add('ring-2', 'ring-brand-500/60');
        setTimeout(() => el.classList.remove('ring-2', 'ring-brand-500/60'), 2000);
      });
    },
    trendArrow(cur, prev) {
      if (prev == null || cur == null) return '—';
      if (cur > prev) return '▲';
      if (cur < prev) return '▼';
      return '—';
    },
    trendClass(cur, prev) {
      if (prev == null || cur == null) return 'text-slate-500';
      if (cur > prev) return 'text-positive';
      if (cur < prev) return 'text-negative';
      return 'text-slate-500';
    },
    sentimentColor(v) {
      if (v == null) return 'text-slate-500';
      if (v >= 0.25) return 'text-positive';
      if (v <= -0.25) return 'text-negative';
      return 'text-neutral';
    },
    fmtSent(v) {
      if (v == null) return '—';
      return Number(v).toFixed(2);
    },
    fmtPct(v) {
      if (v == null) return '—';
      return Number(v).toFixed(1) + '%';
    },
    fmtPlaybook(v) {
      if (v == null || v === '') return '—';
      return Number(v).toFixed(1) + '%';
    },
    init() {
      try {
        this.savedSets = JSON.parse(localStorage.getItem('cp_promptx_filters') || '[]');
      } catch {
        this.savedSets = [];
      }
      const boot = window.__DASHBOARD_BOOTSTRAP;
      if (boot && typeof boot.stats === 'object' && boot.stats !== null) {
        this.stats = boot.stats;
        this.keywords = Array.isArray(boot.keywords) ? boot.keywords : [];
        return;
      }
      const qs = window.location.search || '';
      fetch(appUrl('/api/dashboard/stats') + qs, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      })
        .then((r) => (r.ok && (r.headers.get('content-type') || '').includes('application/json') ? r.json() : null))
        .then((j) => {
          if (j) this.stats = j;
        })
        .catch(() => {});
    },
  }));
});
