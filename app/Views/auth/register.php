<div class="rounded-2xl border border-surface-border bg-surface-card p-8 shadow-glow">
    <h1 class="font-display text-2xl font-bold text-white mb-2">Create account</h1>
    <p class="text-sm text-slate-400 mb-6">Start analyzing calls in minutes.</p>
    <form method="post" action="<?= e(url('/auth/register')) ?>" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1">Name</label>
            <input type="text" name="name" required class="w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1">Email</label>
            <input type="email" name="email" required class="w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1">Password (min 8)</label>
            <input type="password" name="password" minlength="8" required class="w-full rounded-lg bg-surface border border-surface-border px-3 py-2 text-sm text-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-medium py-2.5 text-sm transition">Register</button>
    </form>
    <p class="text-center text-xs text-slate-500 mt-6">
        Have an account? <a href="<?= e(url('/auth/login')) ?>" class="text-brand-500 hover:text-brand-400">Sign in</a>
    </p>
</div>
