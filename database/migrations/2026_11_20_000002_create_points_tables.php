<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Poin HEBAT: poin reward tertutup (tidak bisa dibeli, dicairkan, atau ditransfer).
 * Semua perubahan saldo lewat buku besar `point_transactions` yang tidak pernah diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('points_balance')->default(0)->after('role');
            $table->unsignedInteger('points_earned')->default(0)->after('points_balance'); // total seumur hidup (untuk level)
        });

        // Buku besar: satu baris per perubahan saldo
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');                         // + masuk, − keluar
            $table->integer('balance_after');
            $table->string('kind', 12);                        // earn | grant | redeem | refund | adjust
            $table->string('rule', 30)->nullable();            // attend | speaker | streak7 | …
            $table->string('description', 200);
            $table->nullableMorphs('source');                  // AttendanceRecord, PointGrant, Redemption
            $table->string('idem_key', 80)->nullable()->unique(); // cegah poin ganda
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['kind', 'created_at']);
        });

        // Aturan poin otomatis (dapat diatur admin)
        Schema::create('point_rules', function (Blueprint $table) {
            $table->string('key', 30)->primary();
            $table->string('label', 100);
            $table->string('hint', 200)->nullable();
            $table->integer('points')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Pemberian poin untuk event (juara, improvement, temuan hazard, …)
        Schema::create('point_grants', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('status', 12)->default('draft');   // draft | pending | approved | rejected
            $table->unsignedInteger('total_points')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 200)->nullable();
            $table->timestamps();
        });
        Schema::create('point_grant_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_grant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points');
            $table->string('note', 120)->nullable();
            $table->unique(['point_grant_id', 'user_id']);
        });

        // Katalog hadiah
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('category', 30)->default('merchandise');
            $table->string('image_path')->nullable();
            $table->unsignedInteger('cost');
            $table->unsignedInteger('stock')->nullable();       // null = tidak terbatas
            $table->unsignedSmallInteger('per_user_limit')->nullable();
            $table->string('pickup_note', 200)->nullable();     // mis. "Ambil di ruang admin Plant, jam kerja"
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Penukaran → kupon QR
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('cost');
            $table->string('status', 12)->default('pending');   // pending | fulfilled | cancelled
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        $now = now();
        DB::table('point_rules')->insert([
            ['key' => 'attend', 'label' => 'Hadir P5M, safety talk, atau event', 'hint' => 'Lokasi terverifikasi atau dicatat host', 'points' => 5, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'speaker', 'label' => 'Menjadi pemateri', 'hint' => 'Tambahan untuk peran pemateri', 'points' => 20, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'streak7', 'label' => 'Hadir 7 hari beruntun', 'hint' => 'Bonus tiap kelipatan 7 hari berturut-turut', 'points' => 25, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        foreach (['redemptions', 'rewards', 'point_grant_recipients', 'point_grants', 'point_rules', 'point_transactions'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['points_balance', 'points_earned']));
    }
};
