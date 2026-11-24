<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ujian Event (Best Mekanik, Best GL, pre/post test training, dll.).
 * Kunci jawaban tidak pernah dikirim ke HP peserta; nilai dihitung di server.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bank soal dikelompokkan per paket (dulu: kolom KodeUjian di Google Sheet)
        Schema::create('question_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_set_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('image_path', 500)->nullable();     // path disk public atau URL luar
            $table->json('options');                            // {"A": "...", "B": "...", ...}
            $table->char('answer', 1);
            $table->text('explanation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['question_set_id', 'is_active']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('code', 30)->unique();               // diumumkan pengawas saat ujian dimulai
            $table->string('audience', 10)->default('assigned'); // assigned | open
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->unsignedTinyInteger('pass_score')->default(70);
            $table->string('result_mode', 10)->default('score'); // hidden | score | review
            $table->unsignedTinyInteger('max_violations')->default(3); // 0 = tidak dibatasi
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Sumber soal: paket + jumlah yang diambil acak (null = semua)
        Schema::create('exam_question_set', function (Blueprint $table) {
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_set_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('take')->nullable();
            $table->primary(['exam_id', 'question_set_id']);
        });

        // Peserta yang ditunjuk admin (kandidat Best Mekanik, Best GL, …)
        Schema::create('exam_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'user_id']);
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->string('status', 12)->default('in_progress'); // in_progress | submitted | disqualified
            $table->string('finish_reason', 12)->nullable();       // user | timeout | violations | admin
            $table->dateTime('started_at');
            $table->dateTime('deadline_at');
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedSmallInteger('total')->default(0);
            $table->unsignedSmallInteger('answered')->default(0);
            $table->unsignedSmallInteger('correct')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedTinyInteger('violations')->default(0);
            $table->json('violation_log')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'user_id', 'attempt_no']);
            $table->index(['exam_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        // Lembar jawaban: satu baris per soal, urutan soal & opsi sudah diacak per peserta
        Schema::create('exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('option_order', 8);                  // mis. "CADB": opsi asli yang tampil sebagai A, B, C, D
            $table->char('choice', 1)->nullable();              // kunci opsi ASLI yang dipilih
            $table->boolean('is_correct')->nullable();
            $table->boolean('flagged')->default(false);
            $table->dateTime('answered_at')->nullable();
            $table->unique(['exam_attempt_id', 'question_id']);
            $table->index(['question_id', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_participants');
        Schema::dropIfExists('exam_question_set');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_sets');
    }
};
