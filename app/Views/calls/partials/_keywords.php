<?php
/** @var array<string,mixed> $analysis */
$kws = $analysis['keywords_discussed'] ?? [];
if (!is_array($kws)) {
    $kws = [];
}
$catColor = static function (string $cat): string {
    return match (strtolower($cat)) {
        'financial' => 'text-emerald-400 border-emerald-500/40 bg-emerald-500/10',
        'technical' => 'text-sky-400 border-sky-500/40 bg-sky-500/10',
        'people' => 'text-fuchsia-400 border-fuchsia-500/40 bg-fuchsia-500/10',
        default => 'text-amber-400 border-amber-500/40 bg-amber-500/10',
    };
};
?>
<div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow">
    <h3 class="text-sm font-semibold text-white mb-4">Keywords discussed</h3>
    <?php if ($kws === []): ?>
        <p class="text-sm text-slate-500">No keywords extracted yet.</p>
    <?php else: ?>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($kws as $kw): ?>
                <?php if (!is_array($kw)) {
                    continue;
                } ?>
                <?php
                $w = (string) ($kw['word'] ?? '');
                $cnt = (int) ($kw['count'] ?? 0);
                $cat = (string) ($kw['category'] ?? 'Process');
                ?>
                <button type="button"
                        class="keyword-tag rounded-full border px-3 py-1 text-xs font-medium transition hover:ring-2 hover:ring-brand-500/40 <?= $catColor($cat) ?>"
                        data-keyword="<?= e(strtolower($w)) ?>">
                    <?= e($w) ?>
                    <span class="opacity-70">×<?= $cnt ?></span>
                    <span class="ml-1 text-[10px] uppercase tracking-wide opacity-60"><?= e($cat) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
