<?php
/** @var array<string,mixed> $analysis */
/** @var array<string,mixed> $call */
$coverage = $analysis['sales_question_coverage'] ?? [];
if (!is_array($coverage)) {
    $coverage = [];
}
$scorePct = isset($analysis['sales_coverage_score_pct']) ? (float) $analysis['sales_coverage_score_pct'] : null;
if ($scorePct === null && $coverage !== []) {
    $scorePct = \App\Services\SalesPlaybookService::scoreCoverage($coverage);
}
$statusStyle = static function (string $s): string {
    return match ($s) {
        'covered' => 'border-positive/50 bg-positive/10 text-positive',
        'partial' => 'border-neutral/50 bg-neutral/10 text-neutral',
        'unclear' => 'border-slate-500/40 bg-slate-500/10 text-slate-400',
        default => 'border-surface-border bg-surface-hover/50 text-slate-500',
    };
};
$byStage = [];
foreach ($coverage as $row) {
    if (!is_array($row)) {
        continue;
    }
    $st = (string) ($row['stage'] ?? 'Other');
    $byStage[$st][] = $row;
}
$stages = ['Discovery', 'Qualification', 'Sales', 'Objection Handling'];
?>
<div class="space-y-6">
    <div class="rounded-xl border border-surface-border bg-surface-card p-5 shadow-glow overflow-hidden relative">
        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-brand-500/10 blur-2xl pointer-events-none" aria-hidden="true"></div>
        <div class="flex flex-col sm:flex-row sm:items-start gap-6">
            <div class="flex-shrink-0">
                <div class="relative h-28 w-28 mx-auto sm:mx-0">
                    <svg class="h-28 w-28 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                        <?php
                        $pct = $scorePct !== null ? max(0, min(100, $scorePct)) : 0;
                        $dashRest = 100 - $pct;
                        ?>
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="rgba(42,45,62,0.9)" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="url(#playGrad)" stroke-width="3"
                            stroke-dasharray="<?= e((string) $pct) . ' ' . e((string) $dashRest) ?>" stroke-linecap="round"/>
                        <defs>
                            <linearGradient id="playGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#4f6ef7"/>
                                <stop offset="100%" stop-color="#22c55e"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="text-2xl font-display font-bold text-white"><?= $scorePct !== null ? e((string) $scorePct) . '%' : '—' ?></span>
                        <span class="text-[10px] uppercase tracking-wider text-slate-500">playbook</span>
                    </div>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold text-white mb-1">Common Sales Question Library</h3>
                <p class="text-xs text-slate-500 mb-3">AI-mapped to Q1–Q15 (kitchen cabinet sales). Covered = addressed in substance; partial = brief touch.</p>
                <?php if ($coverage === []): ?>
                    <p class="text-sm text-slate-500">Playbook scores appear after analysis completes.</p>
                <?php else: ?>
                    <div class="flex flex-wrap gap-2 text-[11px]">
                        <?php
                        $counts = ['covered' => 0, 'partial' => 0, 'not_addressed' => 0, 'unclear' => 0];
                        foreach ($coverage as $r) {
                            if (!is_array($r)) {
                                continue;
                            }
                            $k = (string) ($r['status'] ?? '');
                            if (isset($counts[$k])) {
                                $counts[$k]++;
                            }
                        }
                        ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-positive/30 px-2 py-0.5 text-positive">● <?= (int) $counts['covered'] ?> full</span>
                        <span class="inline-flex items-center gap-1 rounded-full border border-neutral/30 px-2 py-0.5 text-neutral">● <?= (int) $counts['partial'] ?> partial</span>
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-600/50 px-2 py-0.5 text-slate-400">● <?= (int) $counts['not_addressed'] ?> missed</span>
                        <?php if ($counts['unclear'] > 0): ?>
                            <span class="inline-flex items-center gap-1 rounded-full border border-slate-600/50 px-2 py-0.5 text-slate-500">● <?= (int) $counts['unclear'] ?> unclear</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($coverage !== []): ?>
        <div class="space-y-4">
            <?php foreach ($stages as $stageName): ?>
                <?php
                $rows = $byStage[$stageName] ?? [];
                if ($rows === []) {
                    continue;
                }
                ?>
                <div>
                    <h4 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-2"><?= e($stageName) ?></h4>
                    <ul class="space-y-2">
                        <?php foreach ($rows as $item): ?>
                            <?php
                            if (!is_array($item)) {
                                continue;
                            }
                            $st = (string) ($item['status'] ?? 'not_addressed');
                            $badge = $statusStyle($st);
                            $excerpt = isset($item['evidence_excerpt']) && (string) $item['evidence_excerpt'] !== '' ? (string) $item['evidence_excerpt'] : null;
                            ?>
                            <li class="rounded-lg border border-surface-border bg-surface/80 p-3">
                                <div class="flex flex-wrap items-start gap-2 justify-between">
                                    <div class="flex items-start gap-2 min-w-0">
                                        <span class="text-xs font-mono text-brand-400 shrink-0"><?= e((string) ($item['question_id'] ?? '')) ?></span>
                                        <p class="text-xs text-slate-200 leading-snug"><?= e((string) ($item['question_text'] ?? '')) ?></p>
                                    </div>
                                    <span class="shrink-0 rounded-md border px-2 py-0.5 text-[10px] font-medium capitalize <?= $badge ?>"><?= e($st) ?></span>
                                </div>
                                <?php if ($excerpt !== null): ?>
                                    <p class="mt-2 text-[11px] text-slate-500 border-l-2 border-brand-500/40 pl-2 italic">“<?= e($excerpt) ?>”</p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-surface-border bg-surface-card/50 p-4">
        <h3 class="text-sm font-semibold text-white mb-4">Business signals</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm mb-4">
            <div>
                <dt class="text-xs text-slate-500">Contact role</dt>
                <dd class="text-slate-200"><?= e((string) ($call['contact_role'] ?? '—')) ?></dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Tenure</dt>
                <dd class="text-slate-200"><?= e((string) ($call['contact_tenure'] ?? '—')) ?></dd>
            </div>
        </dl>
        <div class="grid grid-cols-2 gap-2 text-xs">
            <?php
            $items = [
                ['label' => 'Budget discussed', 'on' => !empty($analysis['budget_discussed'])],
                ['label' => 'Related project', 'on' => !empty($analysis['related_project'])],
                ['label' => 'Business strategy', 'on' => !empty($analysis['business_strategy'])],
                ['label' => 'Marketing', 'on' => !empty($analysis['marketing_discussed'])],
            ];
            foreach ($items as $it):
            ?>
                <div class="flex items-center gap-2 rounded-lg border border-surface-border px-3 py-2 <?= $it['on'] ? 'bg-brand-500/10 border-brand-500/30' : 'opacity-60' ?>">
                    <span class="text-lg"><?= $it['on'] ? '☑' : '☐' ?></span>
                    <span class="text-slate-300"><?= e($it['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-[11px] text-slate-500 mt-3">AI-detected — verify against the transcript.</p>
    </div>
</div>
