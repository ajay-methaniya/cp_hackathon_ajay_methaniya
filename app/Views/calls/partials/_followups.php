<?php
/** @var array<string,mixed> $analysis */
/** @var int $callId */
$actions = $analysis['follow_up_actions'] ?? [];
if (!is_array($actions)) {
    $actions = [];
}
$pos = $analysis['positive_observations'] ?? [];
$neg = $analysis['negative_observations'] ?? [];
if (!is_array($pos)) {
    $pos = [];
}
if (!is_array($neg)) {
    $neg = [];
}
$priClass = static function (string $p): string {
    return match (strtolower($p)) {
        'high' => 'bg-negative/20 text-negative border-negative/40',
        'low' => 'bg-slate-500/20 text-slate-300 border-slate-500/40',
        default => 'bg-neutral/20 text-neutral border-neutral/40',
    };
};
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="rounded-xl border border-surface-border bg-surface-card p-4 shadow-glow">
        <h3 class="text-sm font-semibold text-white mb-4">Follow-up actions</h3>
        <ul class="space-y-3 text-sm" id="followup-list">
            <?php foreach ($actions as $idx => $act): ?>
                <?php if (!is_array($act)) {
                    continue;
                } ?>
                <li class="flex gap-3 items-start rounded-lg border border-surface-border px-3 py-2 <?= !empty($act['completed']) ? 'opacity-50' : '' ?>">
                    <input type="checkbox" class="mt-1" data-followup-idx="<?= (int) $idx ?>"
                           <?= !empty($act['completed']) ? 'checked' : '' ?>>
                    <div class="flex-1">
                        <p class="<?= !empty($act['completed']) ? 'line-through text-slate-500' : 'text-slate-200' ?>"><?= e((string) ($act['action'] ?? '')) ?></p>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <span class="text-[10px] rounded-full border px-2 py-0.5 <?= $priClass((string) ($act['priority'] ?? 'medium')) ?>"><?= e((string) ($act['priority'] ?? '')) ?></span>
                            <span class="text-[10px] text-slate-500"><?= e((string) ($act['owner'] ?? '')) ?></span>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <form id="manual-followup-form" class="mt-4 flex flex-col gap-2">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="text" name="action" placeholder="Add manual action" class="rounded-lg bg-surface border border-surface-border px-3 py-2 text-xs text-white">
            <div class="flex gap-2">
                <select name="priority" class="rounded-lg bg-surface border border-surface-border px-2 py-1.5 text-xs text-white">
                    <option>High</option>
                    <option selected>Medium</option>
                    <option>Low</option>
                </select>
                <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs text-white">Add</button>
            </div>
        </form>
    </div>
    <div class="space-y-4">
        <div class="rounded-xl border border-positive/30 bg-positive/5 p-4">
            <h3 class="text-sm font-semibold text-positive mb-2">Positive observations</h3>
            <ul class="list-disc list-inside text-sm text-slate-200 space-y-1">
                <?php foreach ($pos as $p): ?>
                    <li><?= e((string) $p) ?></li>
                <?php endforeach; ?>
                <?php if ($pos === []): ?>
                    <li class="text-slate-500 list-none">—</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="rounded-xl border border-negative/30 bg-negative/5 p-4">
            <h3 class="text-sm font-semibold text-negative mb-2">Areas to improve</h3>
            <ul class="list-disc list-inside text-sm text-slate-200 space-y-1">
                <?php foreach ($neg as $n): ?>
                    <li><?= e((string) $n) ?></li>
                <?php endforeach; ?>
                <?php if ($neg === []): ?>
                    <li class="text-slate-500 list-none">—</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
