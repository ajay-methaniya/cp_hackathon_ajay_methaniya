<?php
/** @var list<array<string,mixed>> $agents */
/** @var array<string,mixed> $filters */
$df = (string) ($filters['date_from'] ?? '');
$dt = (string) ($filters['date_to'] ?? '');
$ag = (string) ($filters['agent_id'] ?? '');
?>
<div id="reports-page" class="space-y-8 max-w-[1600px] mx-auto">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 print:hidden">
        <div>
            <h2 class="font-display text-2xl font-bold text-white tracking-tight"><?= e(t('reports.title', 'Intelligence reports')) ?></h2>
            <p class="text-sm text-slate-500 mt-1"><?= e(t('reports.subtitle', 'Team performance, languages, playbook depth, and sentiment trends — filtered like the dashboard.')) ?></p>
        </div>
        <form method="get" action="<?= e(url('/reports')) ?>" class="flex flex-wrap gap-2 items-end">
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-500">From</label>
                <input type="date" name="date_from" value="<?= e($df) ?>" required style="color-scheme: dark" class="block mt-0.5 rounded-lg bg-surface border border-surface-border px-2 py-1.5 text-xs text-white">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-500">To</label>
                <input type="date" name="date_to" value="<?= e($dt) ?>" required style="color-scheme: dark" class="block mt-0.5 rounded-lg bg-surface border border-surface-border px-2 py-1.5 text-xs text-white">
            </div>
            <div>
                <label class="text-[10px] uppercase tracking-wider text-slate-500">Agent</label>
                <select name="agent_id" class="block mt-0.5 rounded-lg bg-surface border border-surface-border px-2 py-1.5 text-xs text-white min-w-[140px]">
                    <option value="" <?= $ag === '' ? ' selected' : '' ?>>All</option>
                    <?php foreach ($agents as $a): ?>
                        <option value="<?= (int) $a['id'] ?>" <?= (string) (int) $a['id'] === $ag ? ' selected' : '' ?>><?= e((string) ($a['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-xs font-medium px-4 py-2">Apply</button>
            <a href="<?= e(url('/reports')) ?>" class="rounded-lg border border-surface-border text-slate-400 hover:text-white text-xs px-3 py-2">Reset</a>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" id="report-kpis">
        <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow">
            <p class="text-xs text-slate-500">Analyzed calls</p>
            <p class="mt-1 text-2xl font-display font-bold text-white" id="kpi-analyzed">—</p>
        </div>
        <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow">
            <p class="text-xs text-slate-500">Avg call length</p>
            <p class="mt-1 text-2xl font-display font-bold text-brand-400" id="kpi-avglen">—</p>
        </div>
        <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow">
            <p class="text-xs text-slate-500">Total talk time</p>
            <p class="mt-1 text-2xl font-display font-bold text-sky-400/90" id="kpi-talk">—</p>
        </div>
        <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow">
            <p class="text-xs text-slate-500">Language mix</p>
            <p class="mt-1 text-2xl font-display font-bold text-violet-300/90" id="kpi-lang-main">—</p>
            <p class="mt-0.5 text-xs text-slate-500 leading-snug" id="kpi-lang-sub">&nbsp;</p>
        </div>
    </div>

    <div class="rounded-xl border border-surface-border bg-surface-card p-5 shadow-glow">
        <h3 class="text-sm font-semibold text-white mb-1">Coaching priorities</h3>
        <p class="text-xs text-slate-500 mb-4">Lowest average playbook coverage (Q1–Q15) in this range — use for 1:1s and training.</p>
        <ul id="reports-coaching-list" class="space-y-2 text-sm text-slate-300">
            <li class="text-slate-500 py-2">Loading…</li>
        </ul>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="rounded-xl border border-surface-border bg-surface-card p-5 shadow-glow">
            <h3 class="text-sm font-semibold text-white mb-1">Agent leaderboard</h3>
            <p class="text-xs text-slate-500 mb-4">Avg confidence &amp; playbook coverage by rep.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-500 border-b border-surface-border">
                        <tr>
                            <th class="pb-2 pr-4">Agent</th>
                            <th class="pb-2 pr-4">Calls</th>
                            <th class="pb-2 pr-4">Sentiment</th>
                            <th class="pb-2 pr-4">Conf.</th>
                            <th class="pb-2">Playbook</th>
                        </tr>
                    </thead>
                    <tbody id="reports-agent-tbody" class="divide-y divide-surface-border"></tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-surface-border bg-surface-card p-5 shadow-glow">
            <h3 class="text-sm font-semibold text-white mb-1">Calls by spoken language</h3>
            <p class="text-xs text-slate-500 mb-3">Share of transcripts with a detected language (donut shows mix; single language still has a ring).</p>
            <div class="relative h-[220px] w-full min-h-[200px]">
                <canvas id="chart-languages" class="max-h-full"></canvas>
            </div>
            <p id="chart-languages-empty" class="hidden text-xs text-slate-500 mt-2">No transcript language data for this filter.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="rounded-xl border border-surface-border bg-surface-card p-5 shadow-glow">
            <h3 class="text-sm font-semibold text-white mb-1">Playbook coverage by question</h3>
            <p class="text-xs text-slate-500 mb-4">Only questions that appear in at least one analyzed call; bar = average coverage % (full = 100%, partial = 50%).</p>
            <div class="relative h-[320px] w-full min-h-[280px]">
                <canvas id="chart-playbook" class="max-h-full"></canvas>
            </div>
            <p id="chart-playbook-empty" class="hidden text-xs text-slate-500 mt-2">No playbook coverage JSON in this range.</p>
        </div>
        <div class="rounded-xl border border-surface-border bg-surface-card p-5 shadow-glow">
            <h3 class="text-sm font-semibold text-white mb-1">Weekly sentiment (avg score)</h3>
            <p class="text-xs text-slate-500 mb-3">Weeks start Monday; scoped to your date range and effective call day.</p>
            <div class="relative h-[280px] w-full min-h-[240px]">
                <canvas id="chart-weekly" class="max-h-full"></canvas>
            </div>
            <p id="chart-weekly-empty" class="hidden text-xs text-slate-500 mt-2">Not enough analyzed calls to plot weeks in this range.</p>
        </div>
    </div>

    <div class="rounded-xl border border-surface-border bg-surface-card p-5 shadow-glow">
        <h3 class="text-sm font-semibold text-white mb-1">Sentiment volume by month</h3>
        <p class="text-xs text-slate-500 mb-3">Stacked counts by calendar month (effective call day).</p>
        <div class="relative h-[220px] w-full min-h-[180px] max-w-4xl">
            <canvas id="chart-sentiment-month" class="max-h-full"></canvas>
        </div>
        <p id="chart-sentiment-empty" class="hidden text-xs text-slate-500 mt-2">No sentiment breakdown for this filter.</p>
    </div>
</div>
