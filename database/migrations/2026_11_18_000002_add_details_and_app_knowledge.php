<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('grade', 10)->nullable()->after('position');
            $table->string('supervisor', 120)->nullable()->after('grade');          // nama GL wali
            $table->string('supervisor_nrp', 30)->nullable()->index()->after('supervisor');
            $table->string('location', 100)->nullable()->after('supervisor_nrp');  // lokasi / area kerja
            $table->string('program', 20)->nullable()->after('location');          // IDP, MAP, dll
        });

        Schema::table('apps', function (Blueprint $table) {
            $table->text('description')->nullable()->after('tagline');   // "apa itu …"
            $table->text('howto')->nullable()->after('description');     // "cara pakai …"
            $table->string('pic_name', 100)->nullable()->after('howto');
            $table->string('pic_phone', 30)->nullable()->after('pic_name');
        });

        // Penjelasan awal untuk aplikasi bawaan (hanya bila masih kosong; admin bisa mengubahnya)
        $descriptions = [
            'Mekanik Pintar' => 'LMS (Learning Management System) Plant ASTO di mekanikpintar.com. Di sini mekanik melihat jadwal training, mengerjakan pre-test & post-test, melihat nilai, poin, dan sertifikat training.',
            'Induksi & PKA' => 'Sistem ujian induksi dan PKA Plant ASTO, termasuk data identitas peserta.',
            'Workshop' => 'Aplikasi workshop: BANDARA (papan status servis unit hauling) dan MoReNHo (monitoring pekerjaan redo/ulang).',
            'Monata' => 'Monata adalah aplikasi monitoring aktivitas dan manajemen tyre (ban) unit — pencatatan pekerjaan tyreman dan kondisi ban.',
            'Digital Assessment' => 'Portal Integrated Assessment Digital KPP untuk asesmen kompetensi mekanik.',
            'SHE System' => 'Portal SHE Plant ASTO: induksi SHE K3, induksi plant, pendaftaran MCU, dan soal BBS.',
            'Shop Manual' => 'Folder Google Drive berisi shop manual Komatsu (one for all + ultra) untuk referensi perbaikan.',
            'Scania WSS' => 'Scania Workshop Suite (WSS) resmi Scania: manual book, partbook, dan informasi servis unit Scania.',
            'Soal Ujian' => 'Gateway sistem ujian mekanik Plant ASTO.',
            'Meca Aid' => 'Folder Google Drive MeCa AID: berbagi materi dan dokumen khusus mekanik.',
            'Dashboard Training' => 'Dashboard monitoring hasil ujian dan review soal training.',
            'ID Card' => 'Kartu identitas digital karyawan Plant ASTO.',
            'Verifikasi Sertifikat' => 'Cek keaslian sertifikat training dengan memindai QR pada sertifikat.',
        ];
        foreach ($descriptions as $name => $text) {
            DB::table('apps')->where('name', $name)->whereNull('description')->update(['description' => $text]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['supervisor_nrp']);
            $table->dropColumn(['grade', 'supervisor', 'supervisor_nrp', 'location', 'program']);
        });
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn(['description', 'howto', 'pic_name', 'pic_phone']);
        });
    }
};
