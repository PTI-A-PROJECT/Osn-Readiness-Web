<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions for resources
        $permissions = [
            // Users permissions
            'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.delete',
            // TingkatSeleksi permissions
            'tingkat_seleksi.viewAny', 'tingkat_seleksi.view', 'tingkat_seleksi.update',
            // Kompetensi permissions
            'kompetensi.viewAny', 'kompetensi.view', 'kompetensi.create', 'kompetensi.update', 'kompetensi.delete',
            // Materi permissions
            'materi.viewAny', 'materi.view', 'materi.create', 'materi.update', 'materi.delete',
            // KonteksSoal permissions
            'konteks_soal.viewAny', 'konteks_soal.view', 'konteks_soal.create', 'konteks_soal.update', 'konteks_soal.delete',
            // Soal permissions
            'soal.viewAny', 'soal.view', 'soal.create', 'soal.update', 'soal.delete',
            // Pembahasan permissions
            'pembahasan.viewAny', 'pembahasan.view', 'pembahasan.create', 'pembahasan.update', 'pembahasan.delete',
            // AturanPemetaan permissions
            'aturan_pemetaan.viewAny', 'aturan_pemetaan.view', 'aturan_pemetaan.update',
            // Quiz permissions
            'quiz.viewAny', 'quiz.view', 'quiz.create', 'quiz.update', 'quiz.delete',
            // Simulasi permissions
            'simulasi.viewAny', 'simulasi.view', 'simulasi.create', 'simulasi.update', 'simulasi.delete',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $siswa = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web']);

        // Assign all permissions to Super Admin
        $superAdmin->syncPermissions(Permission::all());

        // Siswa role has no special permissions by default
        $siswa->syncPermissions([]);

        // Reset cached permissions again
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
