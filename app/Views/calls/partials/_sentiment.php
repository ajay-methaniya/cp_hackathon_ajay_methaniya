<?php
/** @var array<string,mixed> $analysis */
/** @var array<string,mixed> $call */
$evo = $analysis['sentiment_evolution'] ?? [];
if (!is_array($evo)) {
    $evo = [];
}
$conf = $analysis['agent_confidence_score'] ?? null;
$live = $analysis['agent_liveness_pct'] ?? null;
$prev = $analysis['previous_handling_score'] ?? null;

$ring = static function (?float $val, string $color): void {
    $v = $val === null ? 0 : max(0, min(100, $val));
    $radius = 36;
    $circ = 2 * M_PI * $radius;
    $off = $circ * (1 - $v / 100);
    ?>
    <svg width="96" height="96" viewBox="0 0 100 100" class="mx-auto">
        <circle cx="50" cy="50" r="<?= $radius ?>" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="8"/>
        <circle cx="50" cy="50" r="<?= $radius ?>" fill="none" stroke="<?= $color ?>" stroke-width="8"
                stroke-dasharray="<?= $circ ?>"
                stroke-dashoffset="<?= $off ?>"
                transform="rotate(-90 50 50)"
                stroke-linecap="round"/>
        <text x="50" y="54" text-anchor="middle" class="fill-white text-sm font-semibold"><?= $val === null ? '—' : round($v) ?></text>
    </svg>
    <?php
};
?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="rounded-xl ring-1 ring-inset ring-white/[0.06] bg-black/20 p-5 text-center">
        <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500 mb-2">Previous handling</p>
        <?php $ring(is_numeric($prev) ? (float) $prev : null, '#fbbf24'); ?>
    </div>
    <div class="rounded-xl ring-1 ring-inset ring-white/[0.06] bg-black/20 p-5 text-center">
        <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500 mb-2">Agent liveness</p>
        <?php $ring(is_numeric($live) ? (float) $live : null, '#60a5fa'); ?>
    </div>
    <div class="rounded-xl ring-1 ring-inset ring-white/[0.06] bg-black/20 p-5 text-center">
        <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500 mb-2">Confidence</p>
        <?php $ring(is_numeric($conf) ? (float) $conf : null, '#4ade80'); ?>
    </div>
</div>
<div class="rounded-xl ring-1 ring-inset ring-white/[0.06] bg-black/20 p-5">
    <h3 class="text-sm font-semibold text-white mb-1">Sentiment evolution</h3>
    <p class="text-xs text-slate-600 mb-4">Score over call time. Points are green / amber / red by polarity; line segments tint toward negative or positive between points.</p>
    <div class="relative h-56 w-full">
        <canvas id="chart-sentiment-evo"></canvas>
    </div>
    <p class="text-xs text-slate-600 mt-3">Hover points for score and transcript excerpt when the model provides one.</p>
</div>
<script type="application/json" id="sentiment-evolution-data"><?= json_encode($evo, JSON_THROW_ON_ERROR) ?></script>
