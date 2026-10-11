<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vanity slug for shareable public professional profiles (e.g. phoenix.com/alambadaniel).
     * One global namespace, so the column lives on users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('passport');
        });

        // Backfill existing health professionals with a unique slug.
        User::withTrashed()
            ->whereIn('user_type', ['doctor', 'nurse', 'other_professional'])
            ->whereNull('slug')
            ->orderBy('id')
            ->get()
            ->each(function (User $user) {
                $user->slug = User::generateSlug($user->name ?: ('pro' . $user->id));
                $user->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
