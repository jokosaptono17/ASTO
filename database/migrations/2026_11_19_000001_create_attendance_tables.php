<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jenis kegiatan: P5M, Safety Talk, Safety Alert, … (dikelola admin)
        Schema::create('attendance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('color', 9)->default('#3DFF8E');
            $table->string('icon', 60)->default('ri-team-fill');
            $table->boolean('track_fatigue')->default(true);   // tanyakan jam tidur
            $table->boolean('allow_speaker')->default(true);   // ada peran Pemateri
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Satu sesi = satu kali P5M / safety talk di satu tempat & waktu
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_type_id')->constrained()->restrictOnDelete();
            $table->string('title', 200)->nullable();            // topik / judul materi
            $table->date('date');
            $table->string('shift', 5)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->unsignedSmallInteger('late_after')->default(10); // menit setelah mulai
            $table->string('location', 100)->nullable();
            $table->string('company', 100)->nullable();
            $table->string('department', 100)->nullable();     // departemen pelaksana
            $table->json('target_departments')->nullable();    // seksi peserta (untuk "belum hadir")
            $table->string('checkin_mode', 10)->default('qr');  // qr | open
            $table->string('code', 12)->unique();
            $table->string('secret', 64);
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('speaker_nrp', 30)->nullable();
            $table->string('speaker_name', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['date', 'opens_at', 'closes_at']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nrp', 30);
            $table->string('name', 120);
            $table->string('position', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('company', 100)->nullable();
            $table->string('role', 12)->default('audience');   // audience | pemateri
            $table->time('sleep_at')->nullable();
            $table->time('wake_at')->nullable();
            $table->unsignedSmallInteger('sleep_minutes')->nullable();
            $table->string('fatigue', 10)->nullable();          // fit | coaching | risk
            $table->dateTime('checked_in_at');
            $table->boolean('is_late')->default(false);
            $table->string('method', 10)->default('qr');        // qr | kode | open | manual
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->timestamps();
            $table->unique(['attendance_session_id', 'nrp']);
            $table->index('nrp');
            $table->index('checked_in_at');
        });

        $now = now();
        DB::table('attendance_types')->insert([
            ['name' => 'P5M', 'color' => '#3DFF8E', 'icon' => 'ri-team-fill', 'track_fatigue' => true, 'allow_speaker' => true, 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Safety Talk', 'color' => '#FFC23D', 'icon' => 'ri-shield-check-fill', 'track_fatigue' => true, 'allow_speaker' => true, 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Safety Alert', 'color' => '#FF5D5D', 'icon' => 'ri-alarm-warning-fill', 'track_fatigue' => true, 'allow_speaker' => true, 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('attendance_types');
    }
};
