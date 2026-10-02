<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('password');
            }
            if (!Schema::hasColumn('users', 'tingkat_aktif_id')) {
                $table->unsignedBigInteger('tingkat_aktif_id')->nullable()->after('is_active');
            }
            $table->softDeletes();
        });

        // Create index for soft deletes and is_active
        Schema::table('users', function (Blueprint $table) {
            $table->unique(['email', 'deleted_at'], 'users_email_aktif_unique')->where('deleted_at', null);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_aktif_unique');
            $table->dropSoftDeletes();
            if (Schema::hasColumn('users', 'tingkat_aktif_id')) {
                $table->dropColumn('tingkat_aktif_id');
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
