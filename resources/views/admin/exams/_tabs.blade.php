<nav class="mb-5 flex flex-wrap gap-2 lg:hidden" aria-label="Menu ujian">
    @foreach ([['admin.exams.index', 'Event ujian', 'ri-medal-2-line', 'admin.exams.index'], ['admin.exams.bank', 'Bank soal', 'ri-question-answer-line', 'admin.exams.bank*']] as [$route, $label, $icon, $pattern])
        <a href="{{ route($route) }}" class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-2 text-sm font-semibold {{ request()->routeIs($pattern) ? 'bg-neon text-[#04170d]' : 'bg-white/[0.06] text-fog' }}"><i class="{{ $icon }}"></i>{{ $label }}</a>
    @endforeach
</nav>
