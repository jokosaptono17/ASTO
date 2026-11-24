<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak pertanyaan Mentari: untuk menemukan pertanyaan yang belum terjawab (dijadikan FAQ oleh admin),
 * umpan balik 👍/👎, dan pemakaian AI. Dibersihkan otomatis setelah 90 hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentari_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question', 300);
            $table->string('normalized', 300)->index();
            $table->string('intent', 30);
            $table->string('source', 20);                 // guide | knowledge | ai | fallback
            $table->string('provider', 20)->nullable();   // groq | gemini | openrouter | endpoint
            $table->boolean('answered')->default(true);
            $table->boolean('helpful')->nullable();
            $table->unsignedInteger('ms')->default(0);
            $table->timestamp('resolved_at')->nullable();  // admin menandai sudah ditangani
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['answered', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentari_logs');
    }
};
