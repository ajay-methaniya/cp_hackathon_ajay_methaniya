<?php
/** @var list<array<string,mixed>> $agents */
/** @var array<string,mixed> $filters */
/** @var array<string,mixed>|null $dashboard_bootstrap */
/** @var string $export_calls_url */
$df = (string) ($filters['date_from'] ?? '');
$dt = (string) ($filters['date_to'] ?? '');

$db = $dashboard_bootstrap ?? ['stats' => [], 'keywords' => []];
$dashStats = is_array($db['stats'] ?? null) ? $db['stats'] : [];
$dashKeywords = is_array($db['keywords'] ?? null) ? $db['keywords'] : [];
$sd = $dashStats['sentiment_distribution'] ?? [];
$dashSent = [
    'positive' => (int) ($sd['positive'] ?? 0),
    'neutral' => (int) ($sd['neutral'] ?? 0),
    'negative' => (int) ($sd['negative'] ?? 0),
];
$dashTimeline = is_array($dashStats['timeline'] ?? null) ? $dashStats['timeline'] : [];
$kwMax = 1;
foreach ($dashKeywords as $k) {
    if (is_array($k)) {
        $kwMax = max($kwMax, (int) ($k['count'] ?? 0));
    }
}
$tlMax = 1;
foreach ($dashTimeline as $pt) {
    if (is_array($pt)) {
        $tlMax = max($tlMax, (int) ($pt['count'] ?? 0));
    }
}
$sentTotal = max(1, $dashSent['positive'] + $dashSent['neutral'] + $dashSent['negative']);
$totalCallsKpi = (int) ($dashStats['total_calls'] ?? 0);
$insights = [];
$topKw = $dashKeywords[0] ?? null;
if (is_array($topKw) && trim((string) ($topKw['word'] ?? '')) !== '') {
    $insights[] = [
        'label' => 'Top theme',
        'body' => sprintf('%s · %d mentions', (string) $topKw['word'], (int) ($topKw['count'] ?? 0)),
    ];
}
$peakDay = '';
$peakC = 0;
foreach ($dashTimeline as $pt) {
    if (!is_array($pt)) {
        continue;
    }
    $cc = (int) ($pt['count'] ?? 0);
    if ($cc > $peakC) {
        $peakC = $cc;
        $peakDay = (string) ($pt['day'] ?? '');
    }
}
if ($peakDay !== '' && $peakC > 0) {
    $insights[] = [
        'label' => 'Peak volume',
        'body' => sprintf('%s · %d calls', $peakDay, $peakC),
    ];
}
if ($sentTotal >= 1) {
    $negPct = (int) round(100 * $dashSent['negative'] / $sentTotal);
    if ($negPct >= 25 && $dashSent['negative'] > 0) {
        $insights[] = [
            'label' => 'Coaching',
            'body' => sprintf('%d%% of analyzed calls are negative — review objections and next steps.', $negPct),
        ];
    } elseif ($dashSent['positive'] > 0 && $dashSent['negative'] >= 0 && $dashSent['positive'] >= $dashSent['negative'] * 2) {
        $insights[] = [
            'label' => 'Momentum',
            'body' => 'Positive sentiment outweighs negative — double down on what is working.',
        ];
    }
}
if ($totalCallsKpi > 0 && $insights === []) {
    $insights[] = [
        'label' => 'Snapshot',
        'body' => sprintf('%d calls in this window. Use filters to drill into agents or tone.', $totalCallsKpi),
    ];
}
$rangeLabel = '';
try {
    if ($df !== '' && $dt !== '') {
        $rangeLabel = (new \DateTimeImmutable($df))->format('M j') . ' – ' . (new \DateTimeImmutable($dt))->format('M j, Y');
    }
} catch (\Throwable) {
    $rangeLabel = $df !== '' || $dt !== '' ? $df . ' – ' . $dt : '';
}
$tlCount = count($dashTimeline);
$tlLabelStep = $tlCount > 0 ? max(1, (int) ceil($tlCount / 8)) : 1;
$isLightTheme = app_theme() === 'light';
?>
<script>window.__DASHBOARD_FILTERS = <?= json_encode($filters, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script>window.__DASHBOARD_BOOTSTRAP = <?= json_encode($dashboard_bootstrap ?? ['stats' => [], 'keywords' => []], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<div
    x-data="dashboardPage(window.__DASHBOARD_FILTERS || {})"
    x-init="init()"
    class="space-y-6 motion-reduce:transition-none"
>
    <div class="flex flex-col xl:flex-row gap-6 xl:gap-8 xl:items-start">
        <aside class="w-full xl:w-72 xl:shrink-0 space-y-4">
            <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow xl:sticky xl:top-6 transition-shadow duration-200 hover:shadow-[0_0_28px_rgba(79,110,247,0.12)]">
                <div class="flex items-center justify-between mb-3 gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-white tracking-tight">Filters</h2>
                        <p class="text-[10px] text-slate-600 mt-0.5">Refine the window below</p>
                    </div>
                    <button type="button" @click="filterModal = true" class="text-xs font-medium text-brand-500 hover:text-brand-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/50 rounded px-1.5 py-0.5">Saved</button>
                </div>
                <form method="get" action="<?= e(url('/dashboard')) ?>" class="space-y-3 text-sm">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] uppercase tracking-wider text-slate-500">From</label>
                            <input type="date" name="date_from" value="<?= e($df) ?>" required style="color-scheme: dark" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-2 py-1.5 text-white text-xs focus:border-brand-500/50 focus:ring-1 focus:ring-brand-500/30 focus:outline-none transition-colors">
                        </div>
                        <div>
                            <label class="text-[10px] uppercase tracking-wider text-slate-500">To</label>
                            <input type="date" name="date_to" value="<?= e($dt) ?>" required style="color-scheme: dark" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-2 py-1.5 text-white text-xs focus:border-brand-500/50 focus:ring-1 focus:ring-brand-500/30 focus:outline-none transition-colors">
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wider text-slate-500">Agent</label>
                        <select name="agent_id" x-model="filters.agent_id" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-2 py-1.5 text-white text-xs focus:border-brand-500/50 focus:ring-1 focus:ring-brand-500/30 focus:outline-none transition-colors">
                            <option value="">All</option>
                            <?php foreach ($agents as $ag): ?>
                                <option value="<?= (int) $ag['id'] ?>"><?= e($ag['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wider text-slate-500">Sentiment</label>
                        <select name="sentiment" x-model="filters.sentiment" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-2 py-1.5 text-white text-xs focus:border-brand-500/50 focus:ring-1 focus:ring-brand-500/30 focus:outline-none transition-colors">
                            <option value="">All</option>
                            <option value="positive">Positive</option>
                            <option value="neutral">Neutral</option>
                            <option value="negative">Negative</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] uppercase tracking-wider text-slate-500">Min s</label>
                            <input type="number" name="min_duration" x-model="filters.min_duration" min="0" placeholder="—" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-2 py-1.5 text-white text-xs placeholder-slate-600 focus:border-brand-500/50 focus:ring-1 focus:ring-brand-500/30 focus:outline-none transition-colors">
                        </div>
                        <div>
                            <label class="text-[10px] uppercase tracking-wider text-slate-500">Max s</label>
                            <input type="number" name="max_duration" x-model="filters.max_duration" min="0" placeholder="—" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-2 py-1.5 text-white text-xs placeholder-slate-600 focus:border-brand-500/50 focus:ring-1 focus:ring-brand-500/30 focus:outline-none transition-colors">
                        </div>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-xs font-semibold py-2.5 shadow-lg shadow-brand-600/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400/60 transition-colors">Apply</button>
                        <a href="<?= e(url('/dashboard')) ?>" class="inline-flex items-center justify-center px-3 py-2.5 rounded-lg border border-surface-border text-xs text-slate-400 hover:text-white hover:border-slate-500 transition-colors">Reset</a>
                    </div>
                </form>
            </div>
            <div class="flex flex-wrap gap-2" x-show="activeChips().length">
                <template x-for="chip in activeChips()" :key="chip.key">
                    <button type="button" @click="removeChip(chip)" class="inline-flex items-center gap-1.5 rounded-full border border-surface-border bg-surface-hover px-2.5 py-1 text-xs text-slate-300 hover:border-brand-500/30 hover:bg-surface-card transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40">
                        <span x-text="chip.label"></span>
                        <span class="text-slate-500" aria-hidden="true">✕</span>
                    </button>
                </template>
            </div>
        </aside>
        <div class="flex-1 min-w-0 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-surface-border/80">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Overview</p>
                    <h2 class="mt-1 text-lg font-display font-semibold <?= $isLightTheme ? 'text-slate-900' : 'text-white' ?>"><?= $rangeLabel !== '' ? e($rangeLabel) : 'Dashboard' ?></h2>
                    <p class="text-xs text-slate-500 mt-0.5">KPIs and charts respect filters on the left</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="<?= e($export_calls_url ?? url('/dashboard/export')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-surface-border bg-surface/80 px-3 py-2 text-xs font-medium text-slate-300 hover:text-white hover:border-slate-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 transition-colors" title="Up to 1000 rows, UTF-8 CSV">
                        <span class="text-slate-500" aria-hidden="true">⬇</span>
                        Export CSV
                    </a>
                    <button type="button" @click="highlightTop()" title="Temporarily highlights rows with positive sentiment in the table below" class="inline-flex items-center gap-2 rounded-lg border border-brand-500/35 bg-brand-500/10 px-3 py-2 text-xs font-medium text-brand-300 hover:bg-brand-500/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400/50 transition-colors">
                        <span aria-hidden="true">✦</span>
                        Highlight positives
                    </button>
                </div>
            </div>
            <?php if ($insights !== []): ?>
                <div class="<?= $isLightTheme
                    ? 'rounded-xl border border-slate-200 bg-white p-4 shadow-sm'
                    : 'rounded-xl border border-brand-500/20 bg-gradient-to-br from-brand-900/20 via-surface-card to-surface-card p-4 shadow-glow' ?>">
                    <p class="text-[10px] font-semibold uppercase tracking-wider <?= $isLightTheme ? 'text-brand-600' : 'text-brand-400/90' ?> mb-3">Signals</p>
                    <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($insights as $ins): ?>
                            <li class="<?= $isLightTheme
                                ? 'rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5'
                                : 'rounded-lg border border-surface-border/60 bg-surface/40 px-3 py-2.5' ?>">
                                <p class="text-[10px] uppercase tracking-wider <?= $isLightTheme ? 'text-slate-500' : 'text-slate-500' ?>"><?= e((string) ($ins['label'] ?? '')) ?></p>
                                <p class="text-xs leading-snug mt-1 <?= $isLightTheme ? 'text-slate-700' : 'text-slate-200' ?>"><?= e((string) ($ins['body'] ?? '')) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4" id="kpi-row">
                <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow relative overflow-hidden transition-all duration-200 hover:border-brand-500/25 hover:shadow-[0_0_24px_rgba(79,110,247,0.12)] group">
                    <p class="text-xs text-slate-500">Total calls</p>
                    <p class="mt-1 text-2xl font-display font-bold text-white" x-text="stats.total_calls ?? '—'"></p>
                    <p class="text-xs mt-1" :class="trendClass(stats.total_calls, stats.total_calls_previous)">
                        <span x-text="trendArrow(stats.total_calls, stats.total_calls_previous)"></span>
                        vs prior window
                    </p>
                </div>
                <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow transition-all duration-200 hover:border-brand-500/25 hover:shadow-[0_0_24px_rgba(79,110,247,0.12)]">
                    <p class="text-xs text-slate-500">Avg sentiment</p>
                    <p class="mt-1 text-2xl font-display font-bold" :class="sentimentColor(stats.kpis?.avg_sentiment)" x-text="fmtSent(stats.kpis?.avg_sentiment)"></p>
                </div>
                <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow transition-all duration-200 hover:border-brand-500/25 hover:shadow-[0_0_24px_rgba(79,110,247,0.12)]">
                    <p class="text-xs text-slate-500">Avg confidence</p>
                    <p class="mt-1 text-2xl font-display font-bold text-brand-400" x-text="fmtPct(stats.kpis?.avg_confidence)"></p>
                </div>
                <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow relative overflow-hidden transition-all duration-200 hover:border-emerald-500/30 hover:shadow-[0_0_24px_rgba(52,211,153,0.1)]">
                    <p class="text-xs text-slate-500">Playbook coverage</p>
                    <p class="mt-1 text-2xl font-display font-bold text-emerald-400/90" x-text="fmtPlaybook(stats.kpis?.avg_playbook_coverage_pct)"></p>
                    <p class="text-[10px] text-slate-600 mt-1">Q1–Q15 sales library</p>
                </div>
                <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow transition-all duration-200 hover:border-amber-500/25 hover:shadow-[0_0_24px_rgba(245,158,11,0.08)]">
                    <p class="text-xs text-slate-500">Open follow-ups</p>
                    <p class="mt-1 text-2xl font-display font-bold text-neutral" x-text="stats.kpis?.pending_followups ?? 0"></p>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow transition-shadow duration-200 hover:shadow-[0_0_28px_rgba(79,110,247,0.08)]">
                    <h3 class="text-sm font-semibold text-white mb-1">Top keywords</h3>
                    <p class="text-[11px] text-slate-500 mb-3">From AI-extracted keywords on completed calls</p>
                    <?php if ($dashKeywords === []): ?>
                        <p class="text-sm text-slate-500 py-8 text-center">No keyword data in this range. Complete analysis includes keywords from transcripts.</p>
                    <?php else: ?>
                        <div class="space-y-2 max-h-[220px] overflow-y-auto pr-1">
                            <?php foreach ($dashKeywords as $k): ?>
                                <?php if (!is_array($k)) {
                                    continue;
                                } ?>
                                <?php
                                $w = (string) ($k['word'] ?? '');
                                $cnt = (int) ($k['count'] ?? 0);
                                $pct = $kwMax > 0 ? (int) round(100 * $cnt / $kwMax) : 0;
                                $pct = max($cnt > 0 ? 12 : 0, min(100, $pct));
                                ?>
                                <div class="flex items-center gap-2 text-xs rounded-lg -mx-1 px-1 py-1 hover:bg-white/[0.04] transition-colors">
                                    <span class="w-28 truncate text-slate-300 shrink-0" title="<?= e($w) ?>"><?= e($w) ?></span>
                                    <div class="flex-1 h-6 rounded-md bg-surface border border-surface-border/50 overflow-hidden min-w-0">
                                        <div class="h-full rounded-md bg-gradient-to-r from-brand-700/80 to-brand-500/90 flex items-center justify-end pr-2 text-[10px] font-medium text-white tabular-nums transition-[width] duration-500" style="width: <?= $pct ?>%; min-width: <?= $cnt > 0 ? '1.75rem' : '0' ?>"><?= $cnt ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow transition-shadow duration-200 hover:shadow-[0_0_28px_rgba(79,110,247,0.08)]">
                    <h3 class="text-sm font-semibold text-white mb-1">Sentiment distribution</h3>
                    <p class="text-[11px] text-slate-500 mb-3">Completed calls in range</p>
                    <div class="min-h-[220px] flex flex-col justify-center gap-5">
                        <div class="flex h-16 rounded-xl overflow-hidden w-full text-xs font-bold text-white shadow-lg ring-1 ring-white/10">
                            <?php if ($dashSent['positive'] > 0): ?>
                                <div class="bg-positive flex items-center justify-center min-w-0 px-1" style="flex: <?= max(1, $dashSent['positive']) ?>"><?= $dashSent['positive'] ?></div>
                            <?php endif; ?>
                            <?php if ($dashSent['neutral'] > 0): ?>
                                <div class="bg-amber-500 flex items-center justify-center min-w-0 px-1 text-slate-900" style="flex: <?= max(1, $dashSent['neutral']) ?>"><?= $dashSent['neutral'] ?></div>
                            <?php endif; ?>
                            <?php if ($dashSent['negative'] > 0): ?>
                                <div class="bg-negative flex items-center justify-center min-w-0 px-1" style="flex: <?= max(1, $dashSent['negative']) ?>"><?= $dashSent['negative'] ?></div>
                            <?php endif; ?>
                            <?php if ($dashSent['positive'] + $dashSent['neutral'] + $dashSent['negative'] === 0): ?>
                                <div class="flex-1 bg-surface-hover flex items-center justify-center text-slate-500 font-normal">No analyzed calls in range</div>
                            <?php endif; ?>
                        </div>
                        <ul class="text-xs text-slate-400 space-y-1.5">
                            <li class="flex justify-between gap-2"><span class="text-positive">Positive</span><span class="text-white tabular-nums"><?= $dashSent['positive'] ?> <span class="text-slate-600">(<?= round(100 * $dashSent['positive'] / $sentTotal) ?>%)</span></span></li>
                            <li class="flex justify-between gap-2"><span class="text-amber-400">Neutral</span><span class="text-white tabular-nums"><?= $dashSent['neutral'] ?> <span class="text-slate-600">(<?= round(100 * $dashSent['neutral'] / $sentTotal) ?>%)</span></span></li>
                            <li class="flex justify-between gap-2"><span class="text-negative">Negative</span><span class="text-white tabular-nums"><?= $dashSent['negative'] ?> <span class="text-slate-600">(<?= round(100 * $dashSent['negative'] / $sentTotal) ?>%)</span></span></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow transition-shadow duration-200 hover:shadow-[0_0_28px_rgba(79,110,247,0.08)]">
                <h3 class="text-sm font-semibold text-white mb-1">Calls timeline</h3>
                <p class="text-[11px] text-slate-500 mb-3">Hover a bar for count · Day labels sampled when the range is long</p>
                <?php if ($dashTimeline === []): ?>
                    <p class="text-sm text-slate-500 py-6">No days in range.</p>
                <?php else: ?>
                    <div class="rounded-xl border border-surface-border/60 bg-surface/40 p-3 sm:p-4">
                        <div class="flex items-end justify-between gap-0.5 sm:gap-1 h-44 px-0.5">
                            <?php foreach ($dashTimeline as $tlIdx => $pt): ?>
                                <?php if (!is_array($pt)) {
                                    continue;
                                } ?>
                                <?php
                                $day = (string) ($pt['day'] ?? '');
                                $c = (int) ($pt['count'] ?? 0);
                                $hPct = $tlMax > 0 && $c > 0 ? max(10, (int) round(100 * $c / $tlMax)) : ($c > 0 ? 10 : 3);
                                $showDayLabel = ($tlCount <= 14) || ((int) $tlIdx % $tlLabelStep === 0) || ((int) $tlIdx === $tlCount - 1);
                                $dayNum = '';
                                if ($day !== '') {
                                    try {
                                        $dayNum = (new \DateTimeImmutable($day))->format('j');
                                    } catch (\Throwable) {
                                        $dayNum = ltrim((string) substr($day, 8), '0') ?: '—';
                                    }
                                }
                                ?>
                                <div class="flex flex-col items-center justify-end h-full flex-1 min-w-0 max-w-[1.25rem] sm:max-w-[1.5rem] group" title="<?= e($day) ?>: <?= $c ?> call(s)">
                                    <span class="text-[10px] font-semibold text-brand-200 mb-0.5 min-h-[1.125rem] leading-none opacity-0 group-hover:opacity-100 transition-opacity tabular-nums"><?= $c > 0 ? (string) $c : '' ?></span>
                                    <div class="w-full max-w-[14px] h-[7.5rem] flex flex-col justify-end mx-auto rounded-t-md overflow-hidden bg-surface-border/25">
                                        <div class="w-full rounded-t-md bg-gradient-to-t from-brand-800 to-brand-400/90 shadow-[0_-4px_18px_rgba(79,110,247,0.25)] origin-bottom transition-transform group-hover:scale-y-[1.03] group-hover:from-brand-700 group-hover:to-brand-300" style="height: <?= $hPct ?>%; min-height: <?= $c > 0 ? '6px' : '3px' ?>"></div>
                                    </div>
                                    <span class="text-[9px] text-slate-500 mt-1 tabular-nums <?= $showDayLabel ? '' : 'invisible pointer-events-none' ?>"<?= $showDayLabel ? '' : ' aria-hidden="true"' ?>><?= $showDayLabel ? e($dayNum) : '·' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-[10px] text-slate-500 mt-3 pt-3 border-t border-surface-border/50 font-mono">
                            <span><?= e($dashTimeline[0]['day'] ?? '') ?></span>
                            <?php if ($peakDay !== '' && $peakC > 0): ?>
                                <span class="text-brand-400 font-sans font-medium">Peak <?= e($peakDay) ?> · <?= $peakC ?> calls</span>
                            <?php endif; ?>
                            <span><?= e($dashTimeline[$tlCount - 1]['day'] ?? '') ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="rounded-xl border border-surface-border bg-surface-card overflow-hidden shadow-glow">
                <div class="px-4 py-3 border-b border-surface-border flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-white">Recent calls</h3>
                    <a href="<?= e(url('/calls')) ?>" class="text-xs font-medium text-brand-500 hover:text-brand-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/50 rounded px-1">View all →</a>
                </div>
                <div class="overflow-x-auto max-h-[min(28rem,55vh)] overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs text-slate-500 border-b border-surface-border bg-surface-card/95 sticky top-0 z-10 backdrop-blur-sm">
                            <tr>
                                <th class="px-4 py-2">Title</th>
                                <th class="px-4 py-2">Agent</th>
                                <th class="px-4 py-2">Date</th>
                                <th class="px-4 py-2">Duration</th>
                                <th class="px-4 py-2">Sentiment</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-border">
                            <?php
                            $recent = \App\Models\Call::recentWithMeta(10, $filters);
                            foreach ($recent as $rc):
                            ?>
                                <tr class="hover:bg-surface-hover/50 transition-colors" data-score="<?= e((string) ($rc['overall_sentiment'] ?? '')) ?>">
                                    <td class="px-4 py-2 text-white font-medium"><?= e($rc['title']) ?></td>
                                    <td class="px-4 py-2 text-slate-400"><?= e($rc['agent_name'] ?? '') ?></td>
                                    <td class="px-4 py-2 text-slate-400"><?= e($rc['call_date'] ?? substr((string) $rc['created_at'], 0, 10)) ?></td>
                                    <td class="px-4 py-2 text-slate-400"><?= $rc['audio_duration'] !== null ? (int) $rc['audio_duration'] . 's' : '—' ?></td>
                                    <td class="px-4 py-2">
                                        <?php $s = $rc['overall_sentiment'] ?? 'neutral'; ?>
                                        <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs <?= \App\Services\SentimentService::badgeClass((string) $s) ?>">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span><?= e($s) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-slate-400"><?= e($rc['status']) ?></td>
                                    <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                                        <a href="<?= e(url('/calls/' . (int) $rc['id'])) ?>" class="text-brand-500 text-xs font-medium hover:text-brand-400 focus:outline-none focus-visible:underline">View</a>
                                        <button type="button" class="text-negative/90 hover:text-negative text-xs focus:outline-none focus-visible:ring-1 focus-visible:ring-negative/50 rounded px-0.5" data-delete-call="<?= (int) $rc['id'] ?>">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($recent === []): ?>
                                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No calls yet. <a href="<?= e(url('/calls/upload')) ?>" class="text-brand-500">Upload one</a>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div x-show="filterModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @keydown.escape.window="filterModal = false">
        <div class="w-full max-w-md rounded-xl border border-surface-border bg-surface-card p-6 shadow-glow" @click.outside="filterModal = false">
            <h3 class="font-display text-lg text-white mb-2">Saved filter sets</h3>
            <p class="text-xs text-slate-500 mb-4">Stored in this browser (localStorage).</p>
            <div class="space-y-2 max-h-48 overflow-y-auto mb-4">
                <template x-for="(s, idx) in savedSets" :key="idx">
                    <div class="flex items-center justify-between rounded-lg border border-surface-border px-3 py-2 text-xs">
                        <span x-text="s.name" class="text-slate-300"></span>
                        <div class="flex gap-2">
                            <button type="button" class="text-brand-500" @click="loadSaved(s)">Load</button>
                            <button type="button" class="text-negative" @click="removeSaved(idx)">Remove</button>
                        </div>
                    </div>
                </template>
            </div>
            <div class="flex gap-2">
                <input type="text" x-model="saveName" placeholder="Name this filter set" class="flex-1 rounded-lg bg-surface border border-surface-border px-3 py-2 text-xs text-white">
                <button type="button" @click="saveCurrentSet()" class="rounded-lg bg-brand-600 px-3 py-2 text-xs text-white">Save</button>
            </div>
            <button type="button" class="mt-4 w-full rounded-lg border border-surface-border py-2 text-xs text-slate-400" @click="filterModal = false">Close</button>
        </div>
    </div>
</div>
