<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Membuat / menjadikan seorang karyawan sebagai Administrator.
 * Contoh: php artisan astoone:admin KT12345 "JOKO SAPTONO"
 */
class MakeAdmin extends Command
{
    protected $signature = 'astoone:admin {nrp} {name?} {--role=admin : admin atau she}';

    protected $description = 'Buat atau jadikan NRP sebagai admin portal';

    public function handle(): int
    {
        $role = $this->option('role');
        if (! in_array($role, ['admin', 'she'], true)) {
            $this->error('Peran harus admin atau she.');

            return self::FAILURE;
        }

        $nrp = User::normalizeNrp($this->argument('nrp'));
        $user = User::firstWhere('nrp', $nrp);
        if (! $user) {
            $name = $this->argument('name') ?: $this->ask('Nama lengkap');
            $user = User::create(['nrp' => $nrp, 'name' => $name, 'role' => $role, 'is_active' => true]);
            $this->info("Pengguna {$user->name} dibuat sebagai {$user->roleLabel()}.");
        } else {
            $user->update(['role' => $role, 'is_active' => true]);
            $this->info("{$user->name} sekarang {$user->roleLabel()}.");
        }
        $this->line($user->isActivated()
            ? 'Akun sudah aktif — masuk dengan NRP dan PIN seperti biasa.'
            : "Buka portal, masuk dengan NRP {$nrp}, konfirmasi nama depan, lalu buat PIN.");

        return self::SUCCESS;
    }
}
