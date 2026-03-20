<?php
/** @var string $content */
/** @var string $title */
$appName = e(config('app.name', 'CP Prompt-X'));
$pageTitle = e($title ?? 'Auth');
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — <?= $appName ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('/css/app.css')) ?>">
    <script>window.__APP_BASE__ = <?= json_encode(base_path()) ?>;</script>
</head>
<body class="h-full font-sans bg-surface flex items-center justify-center p-6">
<div class="w-full max-w-md">
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="mb-4 rounded-lg border border-negative/40 bg-negative/10 px-4 py-3 text-sm text-negative">
            <?= e($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
    <?= $content ?>
</div>
</body>
</html>
