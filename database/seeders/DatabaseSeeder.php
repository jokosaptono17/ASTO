<?php

namespace Database\Seeders;

use App\Models\AppLink;
use App\Models\Category;
use App\Models\MentariFaq;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Isi awal portal: kategori, aplikasi dari portal lama, dan FAQ Mentari.
 * Aman dijalankan ulang (tidak menggandakan data).
 * Admin pertama: atur ADMIN_NRP & ADMIN_NAME di .env, atau pakai
 * `php artisan astoone:admin NRP "NAMA"`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $cat = [];
        foreach ([
            ['Training & Pengembangan', 'ri-graduation-cap-line'],
            ['Safety & SHE', 'ri-shield-check-line'],
            ['Workshop & Maintenance', 'ri-tools-line'],
            ['Dokumen & Manual', 'ri-book-open-line'],
            ['Monitoring & Data', 'ri-line-chart-line'],
        ] as $i => [$name, $icon]) {
            $cat[$name] = Category::firstOrCreate(['name' => $name], ['icon' => $icon, 'sort_order' => ($i + 1) * 10])->id;
        }

        $apps = [
            // Utama (baris atas beranda)
            ['Mekanik Pintar', 'LMS training, ujian & sertifikat mekanik', 'https://mekanikpintar.com', 'ri-graduation-cap-fill', '#FACC15', 'featured', 'Training & Pengembangan'],
            ['Induksi & PKA', 'Ujian induksi, PKA & identitas karyawan', 'https://induksi.mekanikpintar.com', 'ri-shield-user-fill', '#3BFF8A', 'featured', 'Safety & SHE'],
            ['Workshop', 'BANDARA service board & monitoring redo MoReNHo', 'https://workshop.mekanikpintar.com', 'ri-tools-fill', '#38BDF8', 'featured', 'Workshop & Maintenance'],
            ['Monata', 'Monitoring aktivitas & manajemen tyre', 'https://tyre.mekanikpintar.com/login', 'ri-steering-2-fill', '#A78BFA', 'featured', 'Workshop & Maintenance'],
            ['Digital Assessment', 'Portal Integrated Assessment Digital mekanik', 'https://1.kppmining.com/Digitalassesment', 'ri-award-fill', '#FB7185', 'featured', 'Training & Pengembangan'],
            // Pintasan (baris kedua)
            ['SHE System', 'Induksi SHE K3, induksi plant, daftar MCU, soal BBS', 'https://she-landing-page.vercel.app/', 'ri-first-aid-kit-fill', '#4ADE80', 'grid', 'Safety & SHE'],
            ['Shop Manual', 'Komatsu shop manual one for all + ultra', 'https://drive.google.com/drive/folders/137ifhVlOjI2xyqqPg4Qw-uMSEsVs8iLR?hl=ID', 'ri-book-3-fill', '#F472B6', 'grid', 'Dokumen & Manual'],
            ['Scania WSS', 'Scania Workshop Suite: manual book & partbook', 'https://wio.scania.com/sws/en-GB', 'ri-truck-fill', '#60A5FA', 'grid', 'Dokumen & Manual'],
            ['Soal Ujian', 'Gateway sistem ujian mekanik Plant ASTO', 'https://ppdasto.github.io/soal_ujian/', 'ri-file-list-3-fill', '#818CF8', 'grid', 'Training & Pengembangan'],
            // Daftar lengkap
            ['Meca Aid', 'Google Drive sharing MeCa AID khusus mekanik', 'https://drive.google.com/drive/folders/1UGQuEbgfUJpQ7h3fab0f5yOL1q2SZz3o?hl=ID', 'ri-folder-shared-fill', '#2DD4BF', 'list', 'Dokumen & Manual'],
            ['Dashboard Training', 'Monitoring hasil ujian dan review soal', 'https://script.google.com/macros/s/AKfycbzXPy8vWkEXDe9-lMjLyg_gJXxQJm-QrOquVu8V-UrcamPF_ZGrCzcVkXhoCe5B-Jit/exec', 'ri-bar-chart-box-fill', '#FB923C', 'list', 'Monitoring & Data'],
            ['ID Card', 'Kartu identitas digital karyawan', 'https://idcard.mekanikpintar.com', 'ri-id-card-fill', '#22D3EE', 'list', 'Safety & SHE'],
            ['Verifikasi Sertifikat', 'Cek keaslian sertifikat training lewat QR', 'https://sertifikat.mekanikpintar.com', 'ri-qr-code-fill', '#FDE047', 'list', 'Training & Pengembangan'],
        ];

        foreach ($apps as $i => [$name, $tagline, $url, $icon, $color, $placement, $category]) {
            AppLink::firstOrCreate(['name' => $name], [
                'tagline' => $tagline, 'url' => $url, 'icon_type' => 'remix', 'icon' => $icon, 'color' => $color,
                'placement' => $placement, 'category_id' => $cat[$category], 'sort_order' => ($i + 1) * 10,
                // Google Drive / Apps Script butuh login Google sehingga tidak dicek statusnya
                'health_check' => ! str_contains($url, 'google.com'),
            ]);
        }

        foreach ([
            ['Jam kerja admin / helpdesk', 'jam kerja,jam admin,helpdesk,hubungi admin,kontak admin', 'Admin AstoOne bisa dihubungi lewat WhatsApp. Sertakan nama, NRP, dan kendala Anda supaya cepat ditangani.', null, null],
            ['Cara memasang AstoOne di HP', 'pasang di hp,cara install,tambah ke layar utama', 'Buka AstoOne di Chrome, ketuk menu ⋮ lalu "Instal aplikasi". Ikon AstoOne akan muncul di layar utama seperti aplikasi biasa.', 'Buka Profil', '/profil'],
        ] as [$q, $k, $a, $label, $url]) {
            MentariFaq::firstOrCreate(['question' => $q], ['keywords' => $k, 'answer' => $a, 'link_label' => $label, 'link_url' => $url]);
        }

        if ($nrp = env('ADMIN_NRP')) {
            User::firstOrCreate(['nrp' => User::normalizeNrp($nrp)], ['name' => env('ADMIN_NAME', 'ADMIN'), 'role' => 'admin', 'is_active' => true]);
        }
    }
}
