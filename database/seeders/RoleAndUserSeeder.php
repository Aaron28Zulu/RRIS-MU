<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create all application roles defined in UserRole enum
        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
            ]);
        }

        // 2. The university's schools (the schools migration also creates them; this keeps the seeder standalone)
        foreach (School::DIRECTORY as $slug => $name) {
            School::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        // 3. Define initial seed accounts for development and testing, spread across the schools
        $seedUsers = [
            [
                'name' => 'Admin User',
                'email' => 'admin@rris.mu.ac.zm',
                'role' => UserRole::ADMIN,
                'department' => 'ICT Directorate',
                'school' => null,
            ],
            [
                'name' => 'Dr. M. Phiri',
                'email' => 'dean@rris.mu.ac.zm',
                'role' => UserRole::DEAN,
                'department' => 'School of Business Studies',
                'school' => 'business-studies',
            ],
            [
                'name' => 'Prof. K. Lungu',
                'email' => 'dean.agriculture@rris.mu.ac.zm',
                'role' => UserRole::DEAN,
                'department' => 'School of Agriculture and Natural Resources',
                'school' => 'agriculture-natural-resources',
            ],
            [
                'name' => 'Mrs. G. Chileshe',
                'email' => 'librarian@rris.mu.ac.zm',
                'role' => UserRole::LIBRARIAN,
                'department' => 'University Library',
                'school' => null,
            ],
            [
                'name' => 'Prof. E. Banda',
                'email' => 'director@rris.mu.ac.zm',
                'role' => UserRole::RESEARCH_DIRECTOR,
                'department' => 'Directorate of Research and Graduate Studies',
                'school' => null,
            ],
            [
                'name' => 'Dr. C. Mulenga',
                'email' => 'cmulenga@rris.mu.ac.zm',
                'role' => UserRole::SUPERVISOR,
                'department' => 'Agricultural Sciences',
                'school' => 'agriculture-natural-resources',
            ],
            [
                'name' => 'Dr. J. Tembo',
                'email' => 'jtembo@rris.mu.ac.zm',
                'role' => UserRole::SUPERVISOR,
                'department' => 'Economics',
                'school' => 'business-studies',
            ],
            [
                'name' => 'Dr. N. Kabwe',
                'email' => 'nkabwe@rris.mu.ac.zm',
                'role' => UserRole::SUPERVISOR,
                'department' => 'Education',
                'school' => 'education',
            ],
            [
                'name' => 'Dr. S. Mwansa',
                'email' => 'smwansa@rris.mu.ac.zm',
                'role' => UserRole::SUPERVISOR,
                'department' => 'Public Health',
                'school' => 'medicine-health-sciences',
            ],
            [
                'name' => 'Mr. B. Chanda',
                'email' => 'bchanda@rris.mu.ac.zm',
                'role' => UserRole::SUPERVISOR,
                'department' => 'Computer Science',
                'school' => 'engineering-technology',
            ],
            [
                'name' => 'Dr. R. Musonda',
                'email' => 'rmusonda@rris.mu.ac.zm',
                'role' => UserRole::SUPERVISOR,
                'department' => 'Physics and Engineering',
                'school' => 'engineering-technology',
            ],
            [
                'name' => 'Dr. L. Sakala',
                'email' => 'lsakala@rris.mu.ac.zm',
                'role' => UserRole::SUPERVISOR,
                'department' => 'Development Studies',
                'school' => 'social-sciences',
            ],
            [
                'name' => 'Peter Mwaba',
                'email' => 'peter@rris.mu.ac.zm',
                'role' => UserRole::RESEARCHER,
                'department' => 'Economics',
                'school' => 'business-studies',
            ],
            [
                'name' => 'Lukundo Bwalya',
                'email' => 'lukundo@rris.mu.ac.zm',
                'role' => UserRole::RESEARCHER,
                'department' => 'Agricultural Sciences',
                'school' => 'agriculture-natural-resources',
            ],
            [
                'name' => 'Kelvin Mwape',
                'email' => 'kelvin@rris.mu.ac.zm',
                'role' => UserRole::STUDENT,
                'department' => 'Computer Science',
                'school' => 'engineering-technology',
            ],
            [
                'name' => 'Mutinta Hamoonga',
                'email' => 'mutinta@rris.mu.ac.zm',
                'role' => UserRole::RESEARCHER,
                'department' => 'Nursing',
                'school' => 'nursing-midwifery',
            ],
            [
                'name' => 'Chipo Lungu',
                'email' => 'chipo@rris.mu.ac.zm',
                'role' => UserRole::RESEARCHER,
                'department' => 'Education',
                'school' => 'education',
            ],
            [
                'name' => 'Natasha Mbewe',
                'email' => 'natasha@rris.mu.ac.zm',
                'role' => UserRole::RESEARCHER,
                'department' => 'Development Studies',
                'school' => 'social-sciences',
            ],
            [
                'name' => 'Joseph Banda',
                'email' => 'joseph@rris.mu.ac.zm',
                'role' => UserRole::RESEARCHER,
                'department' => 'Accounting and Finance',
                'school' => 'business-studies',
            ],
            [
                'name' => 'Mwila Kasonde',
                'email' => 'mwila@rris.mu.ac.zm',
                'role' => UserRole::RESEARCHER,
                'department' => 'Engineering',
                'school' => 'engineering-technology',
            ],
            [
                'name' => 'Thandiwe Mumba',
                'email' => 'thandiwe@rris.mu.ac.zm',
                'role' => UserRole::STUDENT,
                'department' => 'Computer Science',
                'school' => 'engineering-technology',
            ],
        ];

        // 4. Seed users and assign their respective Spatie roles
        foreach ($seedUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'role' => $userData['role'],
                    'department' => $userData['department'],
                    'school_id' => $userData['school'] ? School::where('slug', $userData['school'])->value('id') : null,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$userData['role']->value]);
        }
    }
}
