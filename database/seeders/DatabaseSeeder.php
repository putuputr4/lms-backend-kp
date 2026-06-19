<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        User::firstOrCreate(
            ['email' => 'superadmin@lms.ac.id'],
            [
                'nama_lengkap' => 'Super Administrator',
                'password'     => Hash::make('SuperAdmin123!'),
                'role'         => 'super_admin',
                'nim_nip'      => 'SA-001',
                'is_active'    => true,
            ]
        );

        // Admin
        User::firstOrCreate(
            ['email' => 'admin@lms.ac.id'],
            [
                'nama_lengkap' => 'Administrator LMS',
                'password'     => Hash::make('Admin123!'),
                'role'         => 'admin',
                'nim_nip'      => 'ADM-001',
                'is_active'    => true,
            ]
        );

        // Dosen
        $dosen = User::firstOrCreate(
            ['email' => 'putuputr4@gmail.com'],
            [
                'nama_lengkap' => 'Putu Putra',
                'password'     => Hash::make('Dosen123!'),
                'role'         => 'dosen',
                'nim_nip'      => '220030056',
                'is_active'    => true,
            ]
        );

        // Mahasiswa contoh
        $mahasiswas = [
            ['nama' => 'Ahmad Fauzi',    'nim' => '2024010001', 'email' => 'ahmad@mhs.ac.id'],
            ['nama' => 'Siti Rahayu',    'nim' => '2024010002', 'email' => 'siti@mhs.ac.id'],
            ['nama' => 'Budi Prasetyo',  'nim' => '2024010003', 'email' => 'budi.p@mhs.ac.id'],
            ['nama' => 'Dewi Anggraini', 'nim' => '2024010004', 'email' => 'dewi@mhs.ac.id'],
            ['nama' => 'Rizky Ramadhan', 'nim' => '2024010005', 'email' => 'rizky@mhs.ac.id'],
        ];

        foreach ($mahasiswas as $mhs) {
            User::firstOrCreate(
                ['email' => $mhs['email']],
                [
                    'nama_lengkap' => $mhs['nama'],
                    'password'     => Hash::make('Mhs123!'),
                    'role'         => 'mahasiswa',
                    'nim_nip'      => $mhs['nim'],
                    'is_active'    => true,
                ]
            );
        }

        $this->command->info('✅ Data awal berhasil di-seed!');
        $this->command->info('');
        $this->command->info('=== AKUN LOGIN ===');
        $this->command->info('Super Admin : superadmin@lms.ac.id / SuperAdmin123!');
        $this->command->info('Admin       : admin@lms.ac.id / Admin123!');
        $this->command->info('Dosen       : putuputr4@gmail.com / Dosen123!');
        $this->command->info('Mahasiswa   : ahmad@mhs.ac.id / Mhs123!');
        $this->command->info('==================');
    }
}