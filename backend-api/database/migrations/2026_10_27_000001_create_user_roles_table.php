<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalization (1NF / 4NF): `users.roles` held a JSON list, a multi-valued
     * fact packed into one column. Each role an account holds is now its own
     * row. `users.role` stays as the single PRIMARY role.
     */
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 50);
            $table->timestamps();

            $table->unique(['user_id', 'role']);
            $table->index('role');
        });

        foreach (DB::table('users')->select('id', 'role', 'roles')->orderBy('id')->get() as $user) {
            $roles = json_decode($user->roles ?? '[]', true) ?: [];
            if ($user->role && !in_array($user->role, $roles, true)) {
                array_unshift($roles, $user->role);
            }

            foreach (array_values(array_unique($roles)) as $role) {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role' => $role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('role');
        });

        foreach (DB::table('users')->select('id')->get() as $user) {
            $roles = DB::table('user_roles')->where('user_id', $user->id)->orderBy('id')->pluck('role')->all();
            DB::table('users')->where('id', $user->id)->update(['roles' => json_encode($roles)]);
        }

        Schema::dropIfExists('user_roles');
    }
};
