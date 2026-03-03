<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->uuid('id')->primary()->change();

            $table->uuid('organization_id')->nullable()->after('id');
            $table->string('email')->unique()->change();
            $table->string('first_name')->after('email');
            $table->string('last_name')->after('first_name');
            $table->text('password_hash')->after('last_name');
            $table->boolean('id_verified')->default(false);
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn([
                'organization_id',
                'first_name',
                'last_name',
                'password_hash',
                'deleted_at'
            ]);
        });
    }
};
