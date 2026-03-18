<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckProductionUsers extends Command
{
    protected $signature = 'users:check-production';

    protected $description = 'Cek user yang role/jabatannya Production atau Produksi (untuk dropdown Add Team Member)';

    public function handle(): int
    {
        $this->info('Mencari user dengan role/jabatan Production atau Produksi...');
        $this->newLine();

        $roleList = ['Production', 'Produksi'];

        $byRole = User::with('employee')
            ->where('is_active', true)
            ->whereIn('role', $roleList)
            ->get();

        $byJabatan = User::with('employee')
            ->where('is_active', true)
            ->whereHas('employee', fn ($q) => $q->whereIn('jabatan_saat_ini', $roleList))
            ->whereNotIn('id', $byRole->pluck('id'))
            ->get();

        $all = $byRole->merge($byJabatan)->unique('id');

        if ($all->isEmpty()) {
            $this->warn('Tidak ada user aktif dengan role/jabatan Production atau Produksi.');
            $this->line('Pastikan:');
            $this->line('  1. Migrasi 2026_02_25_100000_make_users_role_varchar_for_production sudah dijalankan (users.role = VARCHAR).');
            $this->line('  2. Ada user dengan users.role = "Production" atau "Produksi", ATAU employees.jabatan_saat_ini = "Production"/"Produksi".');
            $this->line('  3. User tersebut is_active = 1.');
            return self::FAILURE;
        }

        $this->info('Ditemukan ' . $all->count() . ' user:');
        $this->table(
            ['ID', 'Name', 'Email', 'users.role', 'jabatan_saat_ini'],
            $all->map(fn ($u) => [
                $u->id,
                $u->name ?? '-',
                $u->email ?? '-',
                $u->role ?? '-',
                $u->employee?->jabatan_saat_ini ?? '-',
            ])->toArray()
        );

        $this->newLine();
        $this->info('User di atas seharusnya muncul di dropdown Produksi di Add Team Member.');
        return self::SUCCESS;
    }
}
