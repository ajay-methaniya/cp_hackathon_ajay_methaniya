<?php
/** @var string $content */
/** @var string $title */
$appName = e(config('app.name', 'CP Prompt-X'));
$pageTitle = e($title ?? 'Dashboard');
$pageHeading = e($header_title ?? $title ?? 'Dashboard');
$userName = e($_SESSION['user_name'] ?? 'User');
$csrf = e(csrf_token());
$reqUri = request_path();
$theme = app_theme();
$locale = app_locale();
$localeOptions = \App\Support\I18n::locales();
$isLight = $theme === 'light';
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrf ?>">
    <title><?= $pageTitle ?> — <?= $appName ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Syne:wght@600;700;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('/css/app.css')) ?>">
    <script>window.__APP_BASE__ = <?= json_encode(base_path()) ?>;</script>
</head>
<body class="h-full font-sans theme-<?= e($theme) ?>">
<div class="min-h-full flex">
    <aside class="w-[15.5rem] shrink-0 min-h-screen flex flex-col print:hidden bg-[#11131a]/90 backdrop-blur-xl border-r border-white/[0.05]">
        <div class="px-5 pt-8 pb-6">
            <a href="<?= e(url('/dashboard')) ?>" class="block font-display text-lg font-bold text-white tracking-tight leading-tight"><?= $appName ?></a>
            <p class="text-[11px] text-slate-500 mt-1.5 font-medium tracking-wide"><?= e(t('shell.tagline', 'Call intelligence')) ?></p>
        </div>
        <nav class="flex-1 px-3 pb-6 space-y-0.5 text-[13px] font-medium" aria-label="Main">
            <?php
            $navItem = static function (string $href, string $label, bool $on) use ($isLight): string {
                if ($isLight) {
                    $bar = $on
                        ? 'bg-brand-500'
                        : 'bg-slate-300 group-hover:bg-slate-400';
                    $row = $on
                        ? 'text-brand-800 bg-brand-500/10 ring-1 ring-brand-500/20'
                        : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100';
                } else {
                    $bar = $on
                        ? 'bg-brand-400 shadow-[0_0_14px_rgba(79,110,247,0.45)]'
                        : 'bg-slate-700/40 group-hover:bg-slate-600/50';
                    $row = $on
                        ? 'text-white bg-white/[0.06] ring-1 ring-white/[0.06]'
                        : 'text-slate-500 hover:text-slate-200 hover:bg-white/[0.03]';
                }

                return '<a href="' . e($href) . '" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors ' . e($row) . '">'
                    . '<span class="h-5 w-0.5 shrink-0 rounded-full ' . e($bar) . '" aria-hidden="true"></span>'
                    . e($label) . '</a>';
            };
            echo $navItem(url('/dashboard'), t('nav.dashboard', 'Dashboard'), str_starts_with($reqUri, '/dashboard'));
            echo $navItem(url('/calls'), t('nav.calls', 'All calls'), str_starts_with($reqUri, '/calls') && !str_contains($reqUri, '/upload'));
            echo $navItem(url('/calls/upload'), t('nav.upload', 'Upload'), str_contains($reqUri, '/calls/upload'));
            echo $navItem(url('/reports'), t('nav.reports', 'Reports'), str_starts_with($reqUri, '/reports'));
            ?>
        </nav>
        <div class="mt-auto px-4 py-4 border-t border-white/[0.05]">
            <p class="text-[13px] text-slate-400 truncate font-medium"><?= $userName ?></p>
            <form action="<?= e(url('/auth/logout')) ?>" method="post" class="mt-2">
                <?= csrf_field() ?>
                <button type="submit" class="text-xs text-slate-500 hover:text-brand-400 font-medium transition-colors"><?= e(t('shell.sign_out', 'Sign out')) ?></button>
            </form>
        </div>
    </aside>
    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-14 shrink-0 flex items-center justify-between gap-3 px-6 md:px-8 border-b border-white/[0.05] bg-surface/40 backdrop-blur-md print:hidden">
            <h1 class="font-display text-base md:text-lg font-semibold text-white tracking-tight"><?= $pageHeading ?></h1>
            <div class="flex items-center gap-2">
                <label class="text-xs text-slate-500 hidden sm:inline-block" for="pref-language"><?= e(t('pref.language', 'Language')) ?></label>
                <select id="pref-language" class="cx-input text-xs py-1.5 w-auto min-w-[7.5rem]">
                    <?php foreach ($localeOptions as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $code === $locale ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="text-xs text-slate-500 hidden sm:inline-block" for="pref-theme"><?= e(t('pref.theme', 'Theme')) ?></label>
                <select id="pref-theme" class="cx-input text-xs py-1.5 w-auto min-w-[6rem]">
                    <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>><?= e(t('pref.dark', 'Dark')) ?></option>
                    <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>><?= e(t('pref.light', 'Light')) ?></option>
                </select>
                <span id="pref-save-indicator" class="text-[11px] text-emerald-300 hidden"><?= e(t('pref.saved', 'Saved')) ?></span>
            </div>
        </header>
        <main class="flex-1 p-5 md:p-8 overflow-auto">
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="mb-4 rounded-xl border border-negative/30 bg-negative/10 px-4 py-3 text-sm text-negative">
                    <?= e($_SESSION['flash_error']) ?>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<script src="<?= e(url('/js/vendor/chart.umd.min.js')) ?>"></script>
<script src="<?= e(url('/js/app.js')) ?>"></script>
<script defer src="<?= e(url('/js/preferences.js')) ?>"></script>
<?php if (!empty($extra_scripts)): ?>
    <?= $extra_scripts ?>
<?php endif; ?>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</body>
</html>
