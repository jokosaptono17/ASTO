<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siklus hidup event ujian untuk jangka panjang:
 * - category    : pengelompokan (Best Mekanik, Pre-test, Post-test, …) agar ratusan event tetap mudah dicari
 * - archived_at : event lama disimpan rapi di Arsip (tetap bisa dibuka & diekspor, tidak mengganggu daftar utama)
 * - deleted_at  : Tempat sampah — bisa dipulihkan 30 hari, setelah itu dihapus permanen otomatis
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->after('title')->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('opens_at');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropIndex(['opens_at']);
            $table->dropIndex(['category']);
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['category', 'archived_at', 'deleted_at']);
        });
    }
};
