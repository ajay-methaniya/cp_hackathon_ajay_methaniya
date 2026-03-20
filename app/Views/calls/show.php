<?php
/** @var array<string,mixed> $call */
/** @var array<string,mixed>|null $transcript */
/** @var array<string,mixed> $analysis */
/** @var list<array<string,mixed>> $notes */
$callId = (int) $call['id'];
$status = (string) $call['status'];
$audioUrl = url('/calls/' . $callId . '/audio');
$transcriptBlocks = \App\Support\CallTranscriptPresenter::blocks(is_array($transcript) ? $transcript : null);
$hasTimedSegments = false;
foreach ($transcriptBlocks as $tb) {
    if (isset($tb['start']) && $tb['start'] !== null) {
        $hasTimedSegments = true;
        break;
    }
}
?>
<div id="call-analysis-root" class="space-y-8 max-w-[1600px] mx-auto print:max-w-none" x-data="{ tab: 'summary' }">
    <?php include __DIR__ . '/partials/_call_hero.php'; ?>

    <?php if ($status !== 'complete'): ?>
        <div class="rounded-xl ring-1 ring-brand-500/25 bg-brand-500/10 px-4 py-3 text-sm text-brand-100 print:hidden" id="processing-banner">
            Processing this call… This page refreshes when analysis is ready.
        </div>
    <?php endif; ?>

    <div class="rounded-2xl ring-1 ring-white/[0.07] bg-surface-card/30 backdrop-blur-sm overflow-hidden shadow-[0_8px_40px_-14px_rgba(0,0,0,0.65)] print:shadow-none print:ring-slate-300">
        <div class="p-2 md:p-3 border-b border-white/[0.06] print:hidden">
            <div class="flex flex-wrap gap-1 p-1 rounded-xl bg-black/25 ring-1 ring-inset ring-white/[0.05]">
                <button type="button" id="tab-btn-summary" @click="tab='summary'" :class="tab==='summary' ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/25' : 'text-slate-500 hover:text-white hover:bg-white/[0.05]'" class="rounded-lg px-3.5 py-2 text-xs font-semibold transition-all">Audio &amp; transcript</button>
                <button type="button" @click="tab='sentiment'" :class="tab==='sentiment' ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/25' : 'text-slate-500 hover:text-white hover:bg-white/[0.05]'" class="rounded-lg px-3.5 py-2 text-xs font-semibold transition-all">Performance</button>
                <button type="button" @click="tab='business'" :class="tab==='business' ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/25' : 'text-slate-500 hover:text-white hover:bg-white/[0.05]'" class="rounded-lg px-3.5 py-2 text-xs font-semibold transition-all">Playbook &amp; keywords</button>
                <button type="button" @click="tab='actions'" :class="tab==='actions' ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/25' : 'text-slate-500 hover:text-white hover:bg-white/[0.05]'" class="rounded-lg px-3.5 py-2 text-xs font-semibold transition-all">Follow-ups</button>
            </div>
        </div>

        <div class="p-4 md:p-6 lg:p-8">
            <div x-show="tab==='summary'" x-cloak>
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    <div class="xl:col-span-2 space-y-6">
                        <div class="rounded-xl ring-1 ring-white/[0.06] bg-black/20 p-5">
                            <div class="flex items-center justify-between gap-2 mb-4">
                                <h2 class="text-sm font-semibold text-white">Recording</h2>
                                <?php if (is_array($transcript) && !empty($transcript['language'])): ?>
                                    <span class="text-[10px] uppercase tracking-wider text-slate-500">Transcript · <?= e(strtoupper((string) $transcript['language'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="h-14 rounded-lg bg-gradient-to-r from-brand-900/50 via-brand-500/15 to-brand-900/50 mb-3 flex items-end gap-px px-1 overflow-hidden cursor-pointer ring-1 ring-inset ring-white/[0.06] hover:ring-brand-500/25 transition-shadow" id="waveform-bars" title="Click to seek in the recording" role="img" aria-label="Waveform — click to seek">
                                <?php
                                $bars = 56;
                                for ($i = 0; $i < $bars; $i++) {
                                    $h = 22 + (crc32((string) $i . (string) $callId) % 58);
                                    echo '<span class="flex-1 bg-brand-400/75 hover:bg-brand-300 rounded-t transition-colors" style="height:' . $h . '%" data-bar-index="' . $i . '"></span>';
                                }
                                ?>
                            </div>
                            <audio id="call-audio" src="<?= e($audioUrl) ?>" class="w-full rounded-lg" controls preload="metadata"></audio>
                            <div class="flex flex-wrap gap-3 mt-3 text-xs text-slate-500 items-center">
                                <label class="inline-flex items-center gap-2">Speed
                                    <select id="audio-rate" class="cx-input text-xs py-1.5 w-auto min-w-[4.5rem]">
                                        <option value="0.75">0.75×</option>
                                        <option value="1" selected>1×</option>
                                        <option value="1.25">1.25×</option>
                                        <option value="1.5">1.5×</option>
                                    </select>
                                </label>
                                <span class="text-[11px] text-slate-600 hidden sm:inline">Space — play/pause when not typing</span>
                                <span id="audio-time" class="ml-auto font-mono text-slate-400 tabular-nums">0:00 / —</span>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-3">Key topics</h3>
                            <div class="flex flex-wrap gap-2">
                                <?php
                                $topics = $analysis['key_topics'] ?? [];
                                if (!is_array($topics)) {
                                    $topics = [];
                                }
                                foreach ($topics as $t):
                                ?>
                                    <span class="rounded-lg bg-brand-500/10 text-slate-200 px-3 py-1.5 text-xs ring-1 ring-inset ring-brand-500/20"><?= e((string) $t) ?></span>
                                <?php endforeach; ?>
                                <?php if ($topics === []): ?>
                                    <span class="text-sm text-slate-600">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-3">
                                <div>
                                    <h3 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Transcript</h3>
                                    <?php if ($hasTimedSegments): ?>
                                        <p class="text-xs text-slate-600 mt-1">Click a timestamp to jump in the player.</p>
                                    <?php else: ?>
                                        <p class="text-xs text-slate-600 mt-1">Speaker labels parsed from text; no word-level timings for seek.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap gap-2 print:hidden">
                                    <input type="search" id="transcript-filter" placeholder="Find in transcript…" class="cx-input text-xs py-2 min-w-[12rem] flex-1 sm:flex-none sm:min-w-[14rem]" autocomplete="off">
                                    <button type="button" id="copy-transcript-btn" class="cx-btn-ghost text-xs px-3 py-2 ring-1 ring-inset ring-white/[0.08]">Copy all</button>
                                </div>
                            </div>
                            <div id="transcript-scroll" class="max-h-[min(28rem,55vh)] overflow-y-auto rounded-xl ring-1 ring-inset ring-white/[0.07] bg-[#0d0f14] p-4 space-y-3">
                                <?php if ($transcriptBlocks === []): ?>
                                    <p class="text-sm text-slate-500"><?= e((string) ($transcript['raw_text'] ?? 'Transcript pending…')) ?></p>
                                <?php else: ?>
                                    <?php foreach ($transcriptBlocks as $b): ?>
                                        <?php
                                        $sp = (int) ($b['speaker'] ?? 0) % 2;
                                        $cls = $sp === 0 ? 'cx-msg--0' : 'cx-msg--1';
                                        $start = $b['start'] ?? null;
                                        $label = (string) ($b['label'] ?? '');
                                        ?>
                                        <article class="cx-msg <?= e($cls) ?> transcript-block" data-start="<?= $start !== null ? e((string) $start) : '' ?>">
                                            <div class="cx-msg-meta">
                                                <?php if ($start !== null && $label !== ''): ?>
                                                    <button type="button" class="cx-msg-time seek-to" data-seek="<?= e((string) $start) ?>"><?= e($label) ?></button>
                                                <?php elseif ($label !== ''): ?>
                                                    <span class="cx-msg-speaker"><?= e($label) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="cx-msg-body"><?= nl2br(e((string) ($b['text'] ?? ''))) ?></div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <aside class="space-y-4 print:hidden">
                        <div class="rounded-xl ring-1 ring-white/[0.06] bg-black/20 p-5 text-sm space-y-3">
                            <div class="flex justify-between gap-2">
                                <span class="text-slate-500">Call date</span>
                                <span class="text-slate-100 font-medium tabular-nums"><?= e((string) ($call['call_date'] ?? substr((string) $call['created_at'], 0, 10))) ?></span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-slate-500">Contact</span>
                                <span class="text-slate-100 font-medium"><?= e((string) ($call['contact_name'] ?? '—')) ?></span>
                            </div>
                            <div class="flex justify-between gap-2 items-center">
                                <span class="text-slate-500">Pipeline</span>
                                <span class="cx-pill <?= $status === 'complete' ? 'bg-emerald-500/14 text-emerald-200' : ($status === 'failed' ? 'bg-rose-500/12 text-rose-200' : 'bg-amber-500/10 text-amber-200') ?>"><?= e($status) ?></span>
                            </div>
                        </div>
                        <div class="rounded-xl ring-1 ring-white/[0.06] bg-black/20 p-5">
                            <h3 class="text-sm font-semibold text-white mb-3">Notes</h3>
                            <form id="note-form" class="space-y-2">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <textarea name="note" rows="3" class="cx-input text-xs" placeholder="Add coaching notes, context, or next steps…"></textarea>
                                <button type="submit" class="cx-btn-primary text-xs py-2 px-4">Save note</button>
                            </form>
                            <ul class="mt-4 space-y-3 text-xs text-slate-500">
                                <?php foreach ($notes as $n): ?>
                                    <li class="border-t border-white/[0.06] pt-3 first:border-0 first:pt-0">
                                        <p class="text-slate-300 leading-relaxed"><?= nl2br(e((string) ($n['note'] ?? ''))) ?></p>
                                        <p class="mt-1.5 text-slate-600"><?= e((string) ($n['author_name'] ?? '')) ?> · <?= e((string) ($n['created_at'] ?? '')) ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </aside>
                </div>
            </div>

            <div x-show="tab==='sentiment'" x-cloak>
                <?php include __DIR__ . '/partials/_sentiment.php'; ?>
            </div>

            <div x-show="tab==='business'" x-cloak>
                <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
                    <div class="xl:col-span-3 space-y-6 min-w-0">
                        <?php include __DIR__ . '/partials/_questionnaire.php'; ?>
                    </div>
                    <div class="xl:col-span-2 xl:sticky xl:top-4 xl:self-start min-w-0">
                        <?php include __DIR__ . '/partials/_keywords.php'; ?>
                    </div>
                </div>
            </div>

            <div x-show="tab==='actions'" x-cloak>
                <?php include __DIR__ . '/partials/_followups.php'; ?>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="transcript-blocks-json"><?= json_encode($transcriptBlocks, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
