<?php
/** @var array<string,mixed> $call */
/** @var array<string,mixed>|null $transcript */
/** @var array<string,mixed> $analysis */
$callId = (int) $call['id'];
$status = (string) $call['status'];
$langCode = is_array($transcript) ? (string) ($transcript['language'] ?? '') : '';
$langLabel = $langCode !== '' ? transcription_language_label($langCode) : '—';
$hint = isset($call['whisper_language_hint']) && (string) $call['whisper_language_hint'] !== ''
    ? (string) $call['whisper_language_hint']
    : null;
$hintLabel = $hint !== null ? transcription_language_label($hint) : null;
$sent = (string) ($analysis['overall_sentiment'] ?? 'neutral');
$sentScore = $analysis['sentiment_score'] ?? null;
$playbook = isset($analysis['sales_coverage_score_pct']) ? (float) $analysis['sales_coverage_score_pct'] : null;
if ($playbook === null && !empty($analysis['sales_question_coverage']) && is_array($analysis['sales_question_coverage'])) {
    $playbook = \App\Services\SalesPlaybookService::scoreCoverage($analysis['sales_question_coverage']);
}
$sentBadge = match ($sent) {
    'positive' => 'bg-emerald-500/16 text-emerald-200 ring-1 ring-emerald-500/20',
    'negative' => 'bg-rose-500/14 text-rose-200 ring-1 ring-rose-500/25',
    default => 'bg-amber-500/12 text-amber-200 ring-1 ring-amber-500/20',
};
$scoreColor = \App\Services\SentimentService::scoreColor($sentScore !== null ? (float) $sentScore : null);
$pbColor = $playbook === null ? 'text-slate-300' : ($playbook >= 50 ? 'text-emerald-300' : ($playbook >= 25 ? 'text-amber-200' : 'text-rose-300'));
$summaryText = (string) ($analysis['call_summary'] ?? ($status === 'complete' ? '' : 'Analysis is running — summary will appear here when complete.'));
?>
<div class="relative overflow-hidden rounded-2xl ring-1 ring-white/[0.07] bg-surface-card/50 backdrop-blur-sm shadow-[0_12px_48px_-16px_rgba(0,0,0,0.75)] print:shadow-none print:ring-slate-300">
    <div class="absolute inset-0 pointer-events-none opacity-[0.12]" style="background-image:radial-gradient(ellipse 80% 50% at 10% -20%,rgba(79,110,247,0.7),transparent 55%),radial-gradient(ellipse 60% 40% at 95% 100%,rgba(139,92,246,0.25),transparent 45%);" aria-hidden="true"></div>
    <div class="relative px-5 py-7 md:px-8 md:py-8">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
            <div class="min-w-0 space-y-4 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold <?= e($sentBadge) ?>">
                        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span><?= e(ucfirst($sent)) ?>
                    </span>
                    <?php if ($status === 'complete'): ?>
                        <span class="text-[11px] text-slate-600 font-mono tabular-nums">#<?= $callId ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="font-display text-2xl md:text-3xl font-bold text-white tracking-tight leading-tight pr-4">
                    <?= e((string) ($call['title'] ?? 'Call')) ?>
                </h1>
                <?php if ($summaryText !== ''): ?>
                    <div class="max-w-3xl space-y-3">
                        <p class="text-sm text-slate-400 leading-[1.65]">
                            <?= e($summaryText) ?>
                        </p>
                        <script type="application/json" id="call-summary-json"><?= json_encode($summaryText, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                        <button type="button" id="copy-summary-btn" class="text-xs font-medium text-brand-400 hover:text-brand-300 transition-colors inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <span id="copy-summary-label">Copy summary</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0 print:hidden">
                <button type="button" onclick="window.print()" class="cx-btn-ghost text-xs px-4 py-2.5 ring-1 ring-inset ring-white/[0.08]">
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print
                    </span>
                </button>
                <a href="<?= e(url('/calls')) ?>" class="cx-btn-primary text-xs py-2.5 px-4">All calls</a>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 md:gap-3">
            <div class="rounded-xl px-3.5 py-3 bg-black/20 ring-1 ring-inset ring-white/[0.06]">
                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Duration</p>
                <p class="mt-1 text-base md:text-lg font-display font-semibold text-white tabular-nums">
                    <?php
                    if ($call['audio_duration'] !== null) {
                        $ds = (int) $call['audio_duration'];
                        $dm = intdiv($ds, 60);
                        $dr = $ds % 60;
                        echo $dm . 'm ' . str_pad((string) $dr, 2, '0', STR_PAD_LEFT) . 's';
                    } else {
                        echo '—';
                    }
                    ?>
                </p>
            </div>
            <div class="rounded-xl px-3.5 py-3 bg-black/20 ring-1 ring-inset ring-white/[0.06]">
                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Sentiment</p>
                <p class="mt-1 text-base md:text-lg font-display font-semibold tabular-nums <?= e($scoreColor) ?>">
                    <?= $sentScore !== null ? e(number_format((float) $sentScore, 2)) : '—' ?>
                </p>
            </div>
            <div class="rounded-xl px-3.5 py-3 bg-black/20 ring-1 ring-inset ring-white/[0.06]">
                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Playbook</p>
                <p class="mt-1 text-base md:text-lg font-display font-semibold tabular-nums <?= e($pbColor) ?>">
                    <?= $playbook !== null ? e((string) $playbook) . '%' : '—' ?>
                </p>
            </div>
            <div class="rounded-xl px-3.5 py-3 bg-black/20 ring-1 ring-inset ring-white/[0.06]">
                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Language</p>
                <p class="mt-1 text-sm font-medium text-slate-200 leading-tight" title="<?= e($langCode) ?>"><?= e($langLabel) ?></p>
                <?php if ($hintLabel !== null): ?>
                    <p class="text-[10px] text-slate-600 mt-0.5">Hint: <?= e($hintLabel) ?></p>
                <?php endif; ?>
            </div>
            <div class="rounded-xl px-3.5 py-3 bg-black/20 ring-1 ring-inset ring-white/[0.06]">
                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Agent</p>
                <p class="mt-1 text-sm font-medium text-slate-200 truncate"><?= e((string) ($call['agent_name'] ?? '—')) ?></p>
            </div>
            <div class="rounded-xl px-3.5 py-3 bg-black/20 ring-1 ring-inset ring-white/[0.06]">
                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Model</p>
                <p class="mt-1 text-[11px] font-mono text-slate-400 truncate" title="<?= e((string) ($analysis['gpt_model_used'] ?? '')) ?>"><?= e((string) ($analysis['gpt_model_used'] ?? '—')) ?></p>
            </div>
        </div>
    </div>
</div>
