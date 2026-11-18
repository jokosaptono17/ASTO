<div x-data x-init="@if (session('toast')) $store.toast.show(@js(session('toast'))) @endif"
     class="pointer-events-none fixed inset-x-0 top-[max(1rem,env(safe-area-inset-top))] z-[60] flex justify-center px-4">
    <div x-show="$store.toast.text" x-cloak x-transition.opacity.duration.200ms role="status"
         class="flex max-w-sm items-center gap-2 rounded-2xl border border-neon/30 bg-bench/95 px-4 py-3 text-sm font-medium text-ink shadow-2xl shadow-black/60 backdrop-blur-xl">
        <i class="ri-checkbox-circle-fill text-lg text-neon"></i>
        <span x-text="$store.toast.text"></span>
    </div>
</div>
