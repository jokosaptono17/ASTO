<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kampanye pop-up: muncul saat aplikasi dibuka
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('popup_frequency', 10)->nullable()->after('format'); // once | daily | session
            $table->unsignedTinyInteger('popup_seconds')->default(3)->after('popup_frequency'); // tombol tutup aktif setelah N detik
            $table->boolean('popup_ack')->default(false)->after('popup_seconds');              // wajib "Saya sudah membaca"
            $table->json('target_departments')->nullable()->after('popup_ack');                 // null = semua karyawan
        });

        // Siapa sudah melihat / membaca pop-up
        Schema::create('campaign_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable(); // menekan "Saya sudah membaca"
            $table->timestamp('dismissed_at')->nullable();    // memilih "Jangan tampilkan lagi"
            $table->unique(['campaign_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_views');
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['popup_frequency', 'popup_seconds', 'popup_ack', 'target_departments']);
        });
    }
};
