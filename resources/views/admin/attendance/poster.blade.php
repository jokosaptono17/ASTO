{{-- Poster QR absensi untuk ditempel di lokasi (A4). Satu QR untuk semua absensi. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head', ['title' => 'Poster QR absensi'])
    <style>
        @page { size: A4 portrait; margin: 0; }
        @media print { .no-print { display: none !important; } body { background: #fff !important; } .sheet { box-shadow: none !important; margin: 0 !important; } }
        .sheet { width: 210mm; height: 297mm; }
    </style>
</head>
<body class="bg-pit print:bg-white">
    <div class="no-print sticky top-0 z-10 flex flex-wrap items-center gap-3 border-b border-white/10 bg-bench/95 px-5 py-3 backdrop-blur">
        <a href="{{ route('admin.attendance.index') }}" class="icon-btn" aria-label="Kembali"><i class="ri-arrow-left-line text-lg"></i></a>
        <p class="grow font-display font-semibold">Poster QR absensi</p>
        <form class="flex items-center gap-2 text-sm">
            <label for="lokasi" class="text-mist">Label lokasi</label>
            <select id="lokasi" name="lokasi" class="field !w-auto !py-2" onchange="this.form.submit()">
                <option value="">Tanpa label</option>
                @foreach ($locations as $l) <option @selected($place === $l->name)>{{ $l->name }}</option> @endforeach
            </select>
        </form>
        <button onclick="window.print()" class="btn-neon !min-h-10 text-sm"><i class="ri-printer-line text-lg"></i> Cetak</button>
    </div>

    <main class="sheet relative mx-auto my-8 flex flex-col overflow-hidden bg-white text-[#06120d] shadow-2xl print:my-0">
        <div class="h-[14mm] bg-[#06120d]" style="background-image: repeating-linear-gradient(-45deg, #ffc23d 0 9mm, #06120d 9mm 18mm)"></div>
        <header class="flex items-center gap-[6mm] px-[16mm] pt-[12mm]">
            <img src="/images/plant-asto.png" alt="" class="h-[22mm] w-auto">
            <div class="grow">
                <p class="font-display text-[13mm] leading-none font-bold tracking-tight">ABSENSI</p>
                <p class="mt-[2mm] text-[5mm] font-semibold text-[#0c5a35]">P5M · Safety Talk · Event</p>
            </div>
            <img src="/images/kpp-mining.png" alt="" class="h-[14mm] w-auto">
        </header>

        <section class="mt-[10mm] flex flex-col items-center px-[16mm]">
            <div class="rounded-[8mm] border-[2.5mm] border-[#06120d] p-[5mm]" x-data="qrImage(@js($url), 900)">
                <canvas x-ref="canvas" class="block !h-[118mm] !w-[118mm]"></canvas>
            </div>
            @if ($place)
                <p class="mt-[7mm] inline-flex items-center gap-[2mm] rounded-full bg-[#06120d] px-[7mm] py-[2.5mm] font-display text-[7mm] font-bold text-[#3dff8e]"><i class="ri-map-pin-2-fill"></i> {{ $place }}</p>
            @else
                <p class="mt-[7mm] font-display text-[7mm] font-bold">Scan untuk absen</p>
            @endif
            <p class="mt-[2mm] text-[4.2mm] text-[#3d5a4c]">atau buka <b>{{ preg_replace('#^https?://#', '', $url) }}</b></p>
        </section>

        <ol class="mx-[16mm] mt-[10mm] grid grid-cols-3 gap-[5mm]">
            @foreach ([['ri-qr-scan-2-line', 'Scan QR', 'Pakai kamera HP. Masuk dengan NRP & PIN bila diminta.'], ['ri-map-pin-2-line', 'Izinkan lokasi', 'GPS memastikan Anda berada di area kegiatan.'], ['ri-moon-clear-line', 'Pilih & isi', 'Pilih kegiatan, isi jam tidur, lalu tekan Absen.']] as $i => [$icon, $title, $text])
                <li class="rounded-[5mm] bg-[#eef6f1] p-[5mm]">
                    <span class="flex items-center gap-[2.5mm]"><span class="grid h-[9mm] w-[9mm] place-items-center rounded-full bg-[#06120d] font-display text-[4.5mm] font-bold text-[#3dff8e]">{{ $i + 1 }}</span><i class="{{ $icon }} text-[7mm] text-[#0c5a35]"></i></span>
                    <p class="mt-[3mm] font-display text-[5mm] font-bold">{{ $title }}</p>
                    <p class="mt-[1mm] text-[3.6mm] leading-snug text-[#3d5a4c]">{{ $text }}</p>
                </li>
            @endforeach
        </ol>

        <footer class="mt-auto flex items-center justify-between px-[16mm] pb-[10mm] text-[3.6mm] text-[#3d5a4c]">
            <span>Satu HP untuk satu karyawan · Absen di luar area ditolak</span>
            <span class="font-display font-bold text-[#06120d]">KITA SATU, KITA BISA, KITA HEBAT</span>
        </footer>
    </main>
</body>
</html>
