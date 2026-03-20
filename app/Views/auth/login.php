<div class="rounded-2xl border border-surface-border bg-surface-card p-8 shadow-glow">
    <h1 class="font-display text-2xl font-bold text-white mb-2">Welcome back</h1>
    <p class="text-sm text-slate-400 mb-6">Sign in to your intelligence workspace.</p>
    <form method="post" action="<?= e(url('/auth/login')) ?>" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1">Email</label>
            <input type="email" name="email" required class="w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1">Password</label>
            <input type="password" name="password" required class="w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-medium py-2.5 text-sm transition">Sign in</button>
    </form>
    <p class="text-center text-xs text-slate-500 mt-6">
        No account? <a href="<?= e(url('/auth/register')) ?>" class="text-brand-500 hover:text-brand-400">Register</a>
    </p>
</div>
