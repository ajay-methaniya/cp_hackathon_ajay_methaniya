<?php
/** @var list<array<string,mixed>> $calls */
/** @var list<array<string,mixed>> $agents */
/** @var array<string,mixed> $filters */
/** @var string $sort */
/** @var string $dir */
/** @var array<string, string|int> $list_query_base */
/** @var array{total:int,page:int,per_page:int,total_pages:int,from:int,to:int} $pagination */

$callsQ = static function (array $extra = []) use ($list_query_base): string {
    return http_build_query(array_merge($list_query_base, $extra));
};

$sortToggle = static function (string $col, string $currentSort, string $currentDir): string {
    if ($currentSort === $col && $currentDir === 'asc') {
        return 'desc';
    }

    return 'asc';
};

$thSort = static function (string $col, string $label) use ($sort, $dir, $callsQ, $sortToggle): string {
    $next = $sortToggle($col, $sort, $dir);
    $active = $sort === $col;
    $arrow = $active ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
    $href = e(url('/calls?' . $callsQ(['sort' => $col, 'dir' => $next, 'page' => 1])));
    $cls = 'inline-flex items-center gap-1 rounded-md px-1 -mx-1 py-0.5 font-medium transition-colors '
        . ($active ? 'text-white' : 'text-slate-500 hover:text-slate-200');

    return '<a href="' . $href . '" class="' . $cls . '">' . e($label) . '<span class="text-[10px] font-normal text-slate-600">' . e($arrow) . '</span></a>';
};

$fmtDur = static function (?int $sec): string {
    if ($sec === null) {
        return '—';
    }
    $m = intdiv($sec, 60);
    $s = $sec % 60;

    return $m . ':' . str_pad((string) $s, 2, '0', STR_PAD_LEFT);
};

$fmtCallDay = static function (array $c): string {
    $d = isset($c['call_date']) && $c['call_date'] !== null && $c['call_date'] !== ''
        ? (string) $c['call_date']
        : substr((string) ($c['created_at'] ?? ''), 0, 10);
    if ($d === '' || strlen($d) < 10) {
        return '—';
    }
    $ts = strtotime($d);

    return $ts ? date('M j, Y', $ts) : '—';
};

$statusUi = static function (string $st): array {
    return match ($st) {
        'complete' => ['Complete', 'bg-emerald-500/16 text-emerald-200'],
        'failed' => ['Failed', 'bg-rose-500/14 text-rose-200'],
        'analyzing', 'transcribing' => [ucfirst($st), 'bg-amber-500/12 text-amber-100'],
        default => [ucfirst($st), 'bg-slate-600/25 text-slate-300'],
    };
};

$p = $pagination;
?>
<div id="calls-list-page" class="space-y-6 max-w-[1380px] mx-auto">
    <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-end sm:justify-between gap-4 print:hidden">
        <div>
            <p class="text-sm text-slate-400 leading-relaxed max-w-lg">Browse, search, and manage recorded calls in one place.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= e(url('/reports')) ?>" class="cx-btn-ghost text-xs px-4"><?= e(t('nav.reports', 'Reports')) ?></a>
            <a href="<?= e(url('/calls/upload')) ?>" class="cx-btn-primary text-sm px-5"><?= e(t('calls.upload', 'Upload call')) ?></a>
        </div>
    </div>

    <div class="rounded-2xl ring-1 ring-white/[0.06] bg-surface-card/35 backdrop-blur-sm overflow-hidden shadow-[0_8px_48px_-12px_rgba(0,0,0,0.65)] print:ring-0 print:shadow-none">
        <form method="get" action="<?= e(url('/calls')) ?>" class="p-5 md:p-6 space-y-5 border-b border-white/[0.05]">
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= e($dir) ?>">
            <div class="flex flex-col gap-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"><?= e(t('common.filters', 'Filters')) ?></span>
                <p class="text-xs text-slate-600"><?= e(t('calls.filters_hint', 'Narrow by date, owner, sentiment, or free text.')) ?></p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-x-4 gap-y-4">
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1.5">From</label>
                    <input type="date" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>" required style="color-scheme: dark" class="cx-input text-xs py-2">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1.5">To</label>
                    <input type="date" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>" required style="color-scheme: dark" class="cx-input text-xs py-2">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1.5">Agent</label>
                    <select name="agent_id" class="cx-input text-xs py-2 cursor-pointer">
                        <option value="">All agents</option>
                        <?php foreach ($agents as $ag): ?>
                            <option value="<?= (int) $ag['id'] ?>" <?= ((string) ($filters['agent_id'] ?? '') === (string) $ag['id']) ? 'selected' : '' ?>><?= e($ag['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1.5">Sentiment</label>
                    <select name="sentiment" class="cx-input text-xs py-2 cursor-pointer">
                        <option value="">Any sentiment</option>
                        <?php foreach (['positive', 'neutral', 'negative'] as $s): ?>
                            <option value="<?= $s ?>" <?= (($filters['sentiment'] ?? '') === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-medium text-slate-500 mb-1.5">Search</label>
                    <input type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Title or contact name…" autocomplete="off" class="cx-input text-xs py-2">
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 pt-1 border-t border-white/[0.04]">
                <label class="sr-only" for="calls-per-page">Rows per page</label>
                <select id="calls-per-page" name="per_page" class="cx-input text-xs py-2 w-auto min-w-[7rem] cursor-pointer">
                    <?php foreach ([10, 25, 50] as $pp): ?>
                        <option value="<?= $pp ?>" <?= (int) $p['per_page'] === $pp ? 'selected' : '' ?>><?= $pp ?> per page</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="cx-btn-primary text-xs py-2 px-5">Apply filters</button>
                <a href="<?= e(url('/calls')) ?>" class="cx-btn-ghost text-xs px-4">Reset all</a>
            </div>
        </form>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-5 py-3 bg-black/15 text-xs">
            <p class="text-slate-500">
                <?php if ($p['total'] === 0): ?>
                    <span class="text-slate-500">No calls match your filters.</span>
                <?php else: ?>
                    Showing <span class="text-slate-300 tabular-nums"><?= (int) $p['from'] ?>–<?= (int) $p['to'] ?></span>
                    <span class="text-slate-600 mx-1">·</span>
                    <span class="text-slate-400 tabular-nums"><?= (int) $p['total'] ?> total</span>
                <?php endif; ?>
            </p>
            <div id="calls-bulk-bar" class="hidden flex flex-wrap items-center gap-3">
                <span class="text-slate-500"><span id="calls-bulk-count" class="text-slate-200 font-semibold tabular-nums">0</span> selected</span>
                <button type="button" id="calls-bulk-delete" class="rounded-lg bg-rose-500/12 hover:bg-rose-500/20 text-rose-200 text-xs font-medium px-3 py-1.5 ring-1 ring-inset ring-rose-500/25 transition-colors">Delete selected</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-[13px]">
                <thead>
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 border-b border-white/[0.06] bg-black/20">
                        <th class="pl-5 pr-1 py-3 w-10">
                            <input type="checkbox" id="calls-select-all" <?= $calls === [] ? 'disabled' : '' ?> class="rounded border-0 bg-[#1a1d27] text-brand-500 ring-1 ring-inset ring-white/[0.08] focus:ring-brand-500/50 disabled:opacity-30" title="Select all on this page" aria-label="Select all on this page">
                        </th>
                        <th class="px-2 py-3"><?= $thSort('title', 'Title') ?></th>
                        <th class="px-2 py-3 hidden md:table-cell"><?= $thSort('agent', 'Agent') ?></th>
                        <th class="px-2 py-3 hidden lg:table-cell"><?= $thSort('call_date', 'Date') ?></th>
                        <th class="px-2 py-3 hidden sm:table-cell text-right"><?= $thSort('duration', 'Length') ?></th>
                        <th class="px-2 py-3"><?= $thSort('status', 'Status') ?></th>
                        <th class="px-2 py-3">Sentiment</th>
                        <th class="pr-5 pl-2 py-3 text-right w-32"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    <?php foreach ($calls as $c):
                        $id = (int) $c['id'];
                        $st = (string) ($c['status'] ?? 'uploaded');
                        [$stLabel, $stCls] = $statusUi($st);
                        $href = url('/calls/' . $id);
                        $inProg = in_array($st, ['analyzing', 'transcribing', 'uploaded'], true);
                        ?>
                        <tr data-call-row data-href="<?= e($href) ?>" class="group transition-colors hover:bg-white/[0.025] cursor-pointer">
                            <td class="pl-5 pr-1 py-2 align-middle" onclick="event.stopPropagation();">
                                <input type="checkbox" data-call-select value="<?= $id ?>" class="rounded border-0 bg-[#1a1d27] text-brand-500 ring-1 ring-inset ring-white/[0.08]" aria-label="Select call <?= $id ?>">
                            </td>
                            <td class="px-2 py-2 align-middle">
                                <p class="font-medium text-slate-100 group-hover:text-white transition-colors truncate max-w-[200px] sm:max-w-[280px]"><?= e((string) ($c['title'] ?? '')) ?></p>
                                <?php if (!empty($c['contact_name'])): ?>
                                    <p class="text-[11px] text-slate-500 truncate max-w-[200px] sm:max-w-[280px] mt-0.5"><?= e((string) $c['contact_name']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 py-2 text-slate-400 align-middle hidden md:table-cell"><?= e((string) ($c['agent_name'] ?? '')) ?></td>
                            <td class="px-2 py-2 text-slate-500 align-middle hidden lg:table-cell whitespace-nowrap tabular-nums"><?= e($fmtCallDay($c)) ?></td>
                            <td class="px-2 py-2 text-slate-500 align-middle hidden sm:table-cell text-right font-mono tabular-nums text-xs"><?= e($fmtDur(isset($c['audio_duration']) ? (int) $c['audio_duration'] : null)) ?></td>
                            <td class="px-2 py-2 align-middle">
                                <span class="cx-pill <?= e($stCls) ?>"><?php if ($inProg && $st !== 'uploaded'): ?><span class="mr-1.5 inline-block h-1 w-1 rounded-full bg-amber-400 animate-pulse align-middle" aria-hidden="true"></span><?php endif; ?><?= e($stLabel) ?></span>
                            </td>
                            <td class="px-2 py-2 align-middle">
                                <?php
                                $sent = $c['overall_sentiment'] ?? null;
                                if ($sent === null || $sent === ''): ?>
                                    <span class="text-slate-600 text-xs">—</span>
                                <?php else:
                                    $s = (string) $sent; ?>
                                    <span class="cx-pill capitalize <?= e(\App\Services\SentimentService::badgeClass($s)) ?>"><?= e($s) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="pr-5 pl-2 py-2 text-right align-middle whitespace-nowrap" onclick="event.stopPropagation();">
                                <a href="<?= e($href) ?>" class="text-xs font-medium text-brand-400 hover:text-brand-300 mr-3">View</a>
                                <button type="button" class="text-xs font-medium text-slate-500 hover:text-rose-400 transition-colors" data-delete-call="<?= $id ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($calls === []): ?>
                        <tr>
                            <td colspan="8" class="px-5 py-16 text-center">
                                <p class="text-slate-400 text-sm font-medium">No calls match these filters</p>
                                <p class="text-slate-600 text-xs mt-2 max-w-sm mx-auto leading-relaxed">Try a wider date range, clear search, or upload a new recording.</p>
                                <a href="<?= e(url('/calls/upload')) ?>" class="inline-flex mt-5 cx-btn-primary text-xs py-2 px-4">Upload call</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($p['total_pages'] > 1): ?>
            <nav class="flex flex-wrap items-center justify-center gap-1.5 px-4 py-4 border-t border-white/[0.05] bg-black/10" aria-label="Pagination">
                <?php
                $cur = (int) $p['page'];
            $last = (int) $p['total_pages'];
            $prev = max(1, $cur - 1);
            $next = min($last, $cur + 1);
            $pgCls = 'min-w-[2.25rem] px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors text-center';
            ?>
                <a href="<?= e(url('/calls?' . $callsQ(['page' => $prev]))) ?>" class="<?= e($pgCls) ?> <?= $cur <= 1 ? 'text-slate-600 pointer-events-none' : 'text-slate-400 hover:bg-white/[0.05] hover:text-slate-200' ?>">Prev</a>
                <?php
                $winStart = max(1, $cur - 2);
            $winEnd = min($last, $cur + 2);
            if ($winStart > 1): ?>
                    <a href="<?= e(url('/calls?' . $callsQ(['page' => 1]))) ?>" class="<?= e($pgCls) ?> text-slate-500 hover:bg-white/[0.05]">1</a>
                    <?php if ($winStart > 2): ?><span class="text-slate-600 px-1">…</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $winStart; $i <= $winEnd; $i++): ?>
                    <?php if ($i === $cur): ?>
                        <span class="<?= e($pgCls) ?> bg-brand-500/20 text-white ring-1 ring-brand-500/30"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(url('/calls?' . $callsQ(['page' => $i]))) ?>" class="<?= e($pgCls) ?> text-slate-500 hover:bg-white/[0.05]"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($winEnd < $last): ?>
                    <?php if ($winEnd < $last - 1): ?><span class="text-slate-600 px-1">…</span><?php endif; ?>
                    <a href="<?= e(url('/calls?' . $callsQ(['page' => $last]))) ?>" class="<?= e($pgCls) ?> text-slate-500 hover:bg-white/[0.05]"><?= $last ?></a>
                <?php endif; ?>
                <a href="<?= e(url('/calls?' . $callsQ(['page' => $next]))) ?>" class="<?= e($pgCls) ?> <?= $cur >= $last ? 'text-slate-600 pointer-events-none' : 'text-slate-400 hover:bg-white/[0.05] hover:text-slate-200' ?>">Next</a>
            </nav>
        <?php endif; ?>
    </div>
</div>
