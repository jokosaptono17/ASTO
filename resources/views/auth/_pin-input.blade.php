{{-- Isian PIN dengan papan angka. $name = nama field, $auto = kirim otomatis. --}}
@php $auto = $auto ?? true; @endphp
<div x-data="pinPad(6, {{ $auto ? 'true' : 'false' }})" @if ($keypad ?? true) @keydown.window="if (!$event.target.closest('input,textarea')) keydown($event)" @endif class="select-none">
    <input type="password" name="{{ $name }}" x-model="value" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="{{ $autocomplete ?? 'current-password' }}"
           @input="sync($event)" class="sr-only" aria-label="{{ $label ?? 'PIN 6 angka' }}" {{ ($focus ?? false) ? 'autofocus' : '' }}>
    <div class="flex justify-center gap-2.5" @click="$el.previousElementSibling.focus()">
        <template x-for="i in length" :key="i">
            <span class="pin-cell" :data-filled="value.length >= i ? 1 : 0">
                <span x-show="value.length >= i" class="h-3 w-3 rounded-full bg-neon shadow-[0_0_10px_var(--color-neon)]"></span>
            </span>
        </template>
    </div>
    @if ($keypad ?? true)
        <div class="mx-auto mt-6 grid max-w-[300px] grid-cols-3 gap-2">
            @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $d)
                <button type="button" class="pin-key" @click="press('{{ $d }}')">{{ $d }}</button>
            @endforeach
            <button type="button" class="pin-key text-base text-mist" @click="clear()" aria-label="Hapus semua"><i class="ri-delete-back-2-line text-2xl"></i></button>
            <button type="button" class="pin-key" @click="press('0')">0</button>
            <button type="button" class="pin-key text-mist" @click="back()" aria-label="Hapus satu angka"><i class="ri-arrow-left-line text-2xl"></i></button>
        </div>
    @endif
</div>
