<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SubDepartment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles: Super Admin, Admin Bagian, Pegawai
        $roleSuperAdmin = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'description' => 'Akses penuh seluruh sistem & master data organisasi']
        );

        $roleAdminBagian = Role::firstOrCreate(
            ['slug' => 'admin-bagian'],
            ['name' => 'Admin Bagian', 'description' => 'Pengelola data pegawai & presensi pada tingkat bagian/departemen']
        );

        $rolePegawai = Role::firstOrCreate(
            ['slug' => 'pegawai'],
            ['name' => 'Pegawai', 'description' => 'Staf / Pegawai Operasional']
        );

        // 2. Master Bagian & Sub Bagian
        $departmentsData = [
            [
                'name' => 'Teknologi Informasi',
                'code' => 'TI',
                'description' => 'Divisi Rekayasa Perangkat Lunak dan Jaringan',
                'subs' => [
                    ['name' => 'Software Engineering', 'code' => 'TI-SE'],
                    ['name' => 'Infrastruktur & Jaringan', 'code' => 'TI-NET'],
                    ['name' => 'IT Support & Helpdesk', 'code' => 'TI-SUP'],
                ],
            ],
            [
                'name' => 'Sumber Daya Manusia',
                'code' => 'SDM',
                'description' => 'Divisi Pengelolaan dan Pengembangan Aparatur Pegawai',
                'subs' => [
                    ['name' => 'Pengembangan & Diklat', 'code' => 'SDM-DIK'],
                    ['name' => 'Mutasi & Karir', 'code' => 'SDM-MUT'],
                    ['name' => 'Kesejahteraan Pegawai', 'code' => 'SDM-KES'],
                ],
            ],
            [
                'name' => 'Keuangan & Akuntansi',
                'code' => 'KEU',
                'description' => 'Divisi Pengelolaan Anggaran dan Laporan Keuangan',
                'subs' => [
                    ['name' => 'Perbendaharaan', 'code' => 'KEU-BEN'],
                    ['name' => 'Akuntansi & Verifikasi', 'code' => 'KEU-AKU'],
                    ['name' => 'Perencanaan Anggaran', 'code' => 'KEU-ANG'],
                ],
            ],
            [
                'name' => 'Umum & Protokoler',
                'code' => 'UMUM',
                'description' => 'Divisi Rumah Tangga, Aset dan Hubungan Masyarakat',
                'subs' => [
                    ['name' => 'Rumah Tangga & Sarpras', 'code' => 'UM-RTP'],
                    ['name' => 'Pengelolaan Aset', 'code' => 'UM-AST'],
                ],
            ],
        ];

        $createdSubs = [];
        $createdDeps = [];

        foreach ($departmentsData as $depData) {
            $department = Department::firstOrCreate(
                ['code' => $depData['code']],
                ['name' => $depData['name'], 'description' => $depData['description']]
            );
            $createdDeps[$depData['code']] = $department;

            foreach ($depData['subs'] as $sub) {
                $subDepartment = SubDepartment::firstOrCreate(
                    ['department_id' => $department->id, 'name' => $sub['name']],
                    ['code' => $sub['code']]
                );
                $createdSubs[$sub['code']] = $subDepartment;
            }
        }

        // 3. User Admin & Pegawai Utama
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator HR',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $adminEmployee = Employee::firstOrCreate(
            ['nip' => '198501012010011001'],
            [
                'user_id' => $adminUser->id,
                'name' => 'Administrator HR',
                'email' => 'admin@example.com',
                'phone' => '081234567890',
                'role_id' => $roleSuperAdmin->id,
                'department_id' => $createdDeps['SDM']->id,
                'sub_department_id' => $createdSubs['SDM-DIK']->id,
                'gender' => 'L',
                'address' => 'Jl. Merdeka No. 1, Jakarta',
                'status' => 'active',
            ]
        );

        // 4. Sample Staff Pegawai
        $sampleEmployees = [
            [
                'name' => 'Budi Santoso',
                'nip' => '199002152015021002',
                'email' => 'budi@example.com',
                'phone' => '081298765432',
                'role_id' => $rolePegawai->id,
                'dep_code' => 'TI',
                'sub_code' => 'TI-SE',
                'gender' => 'L',
                'address' => 'Jl. Sudirman No. 45, Jakarta',
            ],
            [
                'name' => 'Siti Rahmawati',
                'nip' => '199208202018012003',
                'email' => 'siti@example.com',
                'phone' => '081345678901',
                'role_id' => $rolePegawai->id,
                'dep_code' => 'KEU',
                'sub_code' => 'KEU-BEN',
                'gender' => 'P',
                'address' => 'Jl. Thamrin No. 12, Jakarta',
            ],
            [
                'name' => 'Ahmad Fauzi',
                'nip' => '198811102012031004',
                'email' => 'ahmad@example.com',
                'phone' => '081211223344',
                'role_id' => $roleAdminBagian->id,
                'dep_code' => 'SDM',
                'sub_code' => 'SDM-MUT',
                'gender' => 'L',
                'address' => 'Jl. Gatot Subroto No. 88, Jakarta',
            ],
            [
                'name' => 'Dewi Lestari',
                'nip' => '199505122020012005',
                'email' => 'dewi@example.com',
                'phone' => '081399887766',
                'role_id' => $rolePegawai->id,
                'dep_code' => 'UMUM',
                'sub_code' => 'UM-RTP',
                'gender' => 'P',
                'address' => 'Jl. Rasuna Said No. 20, Jakarta',
            ],
        ];

        $today = Carbon::today()->toDateString();
        $allEmployees = [$adminEmployee];

        foreach ($sampleEmployees as $empData) {
            $user = User::firstOrCreate(
                ['email' => $empData['email']],
                [
                    'name' => $empData['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $emp = Employee::firstOrCreate(
                ['nip' => $empData['nip']],
                [
                    'user_id' => $user->id,
                    'name' => $empData['name'],
                    'email' => $empData['email'],
                    'phone' => $empData['phone'],
                    'role_id' => $empData['role_id'],
                    'department_id' => $createdDeps[$empData['dep_code']]->id,
                    'sub_department_id' => $createdSubs[$empData['sub_code']]->id,
                    'gender' => $empData['gender'],
                    'address' => $empData['address'],
                    'status' => 'active',
                ]
            );
            $allEmployees[] = $emp;
        }

        // 5. Seed Attendance for today & yesterday
        $yesterday = Carbon::yesterday()->toDateString();

        foreach ($allEmployees as $index => $emp) {
            // Yesterday's attendance
            Attendance::firstOrCreate(
                ['employee_id' => $emp->id, 'date' => $yesterday],
                [
                    'check_in' => '07:55:00',
                    'check_out' => '17:05:00',
                    'status' => 'hadir',
                    'notes' => 'Tepat waktu',
                ]
            );

            // Today's attendance (different variations: hadir, terlambat, izin, sakit, dinas_pagi, dinas_sore, cuti)
            if ($index === 0) {
                Attendance::firstOrCreate(
                    ['employee_id' => $emp->id, 'date' => $today],
                    [
                        'check_in' => '07:45:00',
                        'check_out' => null,
                        'status' => 'hadir',
                        'notes' => 'Presensi pagi tepat waktu',
                    ]
                );
            } elseif ($index === 1) {
                Attendance::firstOrCreate(
                    ['employee_id' => $emp->id, 'date' => $today],
                    [
                        'check_in' => '06:55:00',
                        'check_out' => null,
                        'status' => 'dinas_pagi',
                        'notes' => 'Jadwal shift Dinas Pagi unit TI',
                    ]
                );
            } elseif ($index === 2) {
                Attendance::firstOrCreate(
                    ['employee_id' => $emp->id, 'date' => $today],
                    [
                        'check_in' => null,
                        'check_out' => null,
                        'status' => 'cuti',
                        'notes' => 'Cuti tahunan disetujui pimpinan',
                    ]
                );
            } elseif ($index === 3) {
                Attendance::firstOrCreate(
                    ['employee_id' => $emp->id, 'date' => $today],
                    [
                        'check_in' => '13:55:00',
                        'check_out' => null,
                        'status' => 'dinas_sore',
                        'notes' => 'Jadwal Dinas Sore',
                    ]
                );
            } elseif ($index === 4) {
                Attendance::firstOrCreate(
                    ['employee_id' => $emp->id, 'date' => $today],
                    [
                        'check_in' => null,
                        'check_out' => null,
                        'status' => 'izin',
                        'notes' => 'Izin keperluan keluarga',
                    ]
                );
            }
        }
    }
}
