<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * The separate Research & Innovation Office role duplicated the Research Director, who now runs the
 * office's programmes. Staff whose main role was the office become Research Directors; anyone who held it
 * as an extra duty loses it. The Repository Librarian role is added for library and senior staff who
 * view and download every uploaded file.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $directorId = $this->roleId('research_director');
        $this->roleId('librarian');

        $officerId = DB::table('roles')->where('name', 'research_officer')->where('guard_name', 'web')->value('id');

        DB::table('users')->where('role', 'research_officer')->update(['role' => 'research_director']);

        if ($officerId !== null) {
            $holders = DB::table('model_has_roles')->where('role_id', $officerId)->get();

            foreach ($holders as $holder) {
                $isDirector = DB::table('users')->where('id', $holder->model_id)->value('role') === 'research_director';

                if ($isDirector) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $directorId,
                        'model_type' => $holder->model_type,
                        'model_id' => $holder->model_id,
                    ]);
                }
            }

            DB::table('model_has_roles')->where('role_id', $officerId)->delete();
            DB::table('role_has_permissions')->where('role_id', $officerId)->delete();
            DB::table('roles')->where('id', $officerId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function roleId(string $name): int
    {
        $id = DB::table('roles')->where('name', $name)->where('guard_name', 'web')->value('id');

        return (int) ($id ?? DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function down(): void
    {
        // Who held the office role is not recorded once it is retired, so this migration is not reversed.
    }
};
