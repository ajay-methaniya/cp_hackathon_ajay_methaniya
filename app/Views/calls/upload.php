<?php
/** @var list<array<string,mixed>> $agents */
/** @var int $current_user_id */
/** @var list<array{code:string,label:string}> $transcription_languages */
$maxMb = (int) (config('storage.max_upload_bytes', 100 * 1024 * 1024) / 1024 / 1024);
$formats = implode(', ', config('storage.allowed_extensions', []));
$langs = $transcription_languages ?? config('transcription_languages', []);
if (!is_array($langs)) {
    $langs = [];
}
?>
<script>
window.__CURRENT_USER_ID = <?= (int) ($current_user_id ?? 0) ?>;
window.__CALL_DATE_DEFAULT = <?= json_encode((new \DateTimeImmutable('today'))->format('Y-m-d'), JSON_THROW_ON_ERROR) ?>;
</script>
<div class="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8" x-data="uploadPage()">
    <div>
        <div
            class="rounded-2xl border-2 border-dashed border-surface-border bg-surface-card p-8 text-center transition hover:border-brand-500/50"
            @dragover.prevent="dragover=true"
            @dragleave.prevent="dragover=false"
            @drop.prevent="onDrop($event)"
            :class="dragover ? 'border-brand-500 shadow-glow' : ''"
        >
            <input type="file" x-ref="file" class="hidden" accept="audio/*,.mp3,.wav,.m4a,.ogg,.mp4,.webm" @change="onFile($event)">
            <p class="font-display text-lg text-white mb-2">Drop audio here</p>
            <p class="text-xs text-slate-500 mb-4">Formats: <?= e($formats) ?> · Max <?= (int) $maxMb ?> MB · <span class="text-slate-400">Multilingual transcription (auto-detect or pick a language)</span></p>
            <button type="button" @click="$refs.file.click()" class="rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-sm px-4 py-2">Choose file</button>
            <p class="text-xs text-slate-400 mt-4" x-text="fileName || 'No file selected'"></p>
            <div class="mt-4 h-2 rounded-full bg-surface overflow-hidden" x-show="progress > 0 && progress < 100">
                <div class="h-full bg-brand-500 transition-all" :style="'width:' + progress + '%'"></div>
            </div>
            <p id="status-message" class="mt-4 text-sm text-brand-400" x-text="statusText"></p>
        </div>
    </div>
    <form class="space-y-4 rounded-2xl border border-surface-border bg-surface-card p-6 shadow-glow" @submit.prevent="submitUpload">
        <?= csrf_field() ?>
        <input type="hidden" name="xhr" value="1">
        <div>
            <label class="text-xs text-slate-500">Call title *</label>
            <input type="text" name="title" x-model="fields.title" required class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white">
        </div>
        <div>
            <label class="text-xs text-slate-500">Contact name</label>
            <input type="text" name="contact_name" x-model="fields.contact_name" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white">
        </div>
        <div>
            <label class="text-xs text-slate-500">Contact role</label>
            <input type="text" name="contact_role" x-model="fields.contact_role" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white">
        </div>
        <div>
            <label class="text-xs text-slate-500">Contact tenure</label>
            <input type="text" name="contact_tenure" x-model="fields.contact_tenure" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white">
        </div>
        <div>
            <label class="text-xs text-slate-500">Call date</label>
            <input type="date" name="call_date" x-model="fields.call_date" style="color-scheme: dark" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white">
        </div>
        <div>
            <label class="text-xs text-slate-500">Transcription language</label>
            <select name="whisper_language" x-model="fields.whisper_language" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white">
                <?php foreach ($langs as $opt): ?>
                    <?php if (!is_array($opt)) {
                        continue;
                    } ?>
                    <option value="<?= e((string) ($opt['code'] ?? '')) ?>"><?= e((string) ($opt['label'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-[11px] text-slate-600 mt-1">Whisper auto-detects; set a language if the wrong one is picked up.</p>
        </div>
        <div>
            <label class="text-xs text-slate-500">Assign to agent</label>
            <select name="agent_user_id" x-model="fields.agent_user_id" class="mt-1 w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white">
                <?php foreach ($agents as $ag): ?>
                    <option value="<?= (int) $ag['id'] ?>" <?= ((int) $ag['id'] === (int) ($current_user_id ?? 0)) ? 'selected' : '' ?>><?= e($ag['name']) ?> (<?= e($ag['role']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-medium py-2.5 text-sm" :disabled="!file || uploading">
            Upload &amp; analyze
        </button>
    </form>
</div>
