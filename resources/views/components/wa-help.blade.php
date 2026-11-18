{{-- Tombol bantuan WhatsApp admin. --}}
@props(['text' => null, 'label' => 'Bantuan via WhatsApp'])
<a href="{{ \App\Models\Setting::whatsappLink($text) }}" target="_blank" rel="noopener" {{ $attributes->merge(['class' => 'btn-wa']) }}>
    <i class="ri-whatsapp-line text-xl" aria-hidden="true"></i>
    <span>{{ $slot->isEmpty() ? $label : $slot }}</span>
</a>
