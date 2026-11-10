<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * People Management → Accounts requires a unique username per login.
     *
     * The column is added nullable so existing accounts keep working, then
     * backfilled from the email local-part (with a numeric suffix on collision)
     * before the unique index is applied. Application-level validation makes it
     * required for new accounts; the nullable column exists only so accounts
     * created before this feature keep a valid row.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 80)->nullable()->after('email');
        });

        $used = [];

        DB::table('users')
            ->orderBy('id')
            ->get(['id', 'email', 'name'])
            ->each(function ($user) use (&$used): void {
                $email = (string) ($user->email ?? '');
                $source = $email !== '' ? Str::before($email, '@') : (string) ($user->name ?? 'user');

                $base = Str::of($source)
                    ->lower()
                    ->replaceMatches('/[^a-z0-9._-]+/', '.')
                    ->trim('._-')
                    ->substr(0, 60)
                    ->value();

                if ($base === '') {
                    $base = 'user'.$user->id;
                }

                $candidate = $base;
                $suffix = 1;

                while (in_array($candidate, $used, true)) {
                    $candidate = $base.'.'.$suffix;
                    $suffix++;
                }

                $used[] = $candidate;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $candidate]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
