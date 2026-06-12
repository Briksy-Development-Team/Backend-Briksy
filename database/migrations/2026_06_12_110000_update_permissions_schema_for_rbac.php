<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            if (!Schema::hasColumn('permissions', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }

            if (!Schema::hasColumn('permissions', 'action')) {
                $table->string('action', 50)->nullable()->after('module');
            }

            if (!Schema::hasColumn('permissions', 'description')) {
                $table->text('description')->nullable()->after('action');
            }

            if (!Schema::hasColumn('permissions', 'guard_name')) {
                $table->string('guard_name')->default('api')->after('description');
            }

            if (!Schema::hasColumn('permissions', 'is_system')) {
                $table->boolean('is_system')->default(true)->after('guard_name');
            }

            $table->index(['module', 'action'], 'permissions_module_action_idx');
        });

        Schema::table('role_permissions', function (Blueprint $table): void {
            $table->unique(['role_id', 'permission_id'], 'role_permissions_role_permission_unique');
            $table->index('role_id', 'role_permissions_role_id_idx');
            $table->index('permission_id', 'role_permissions_permission_id_idx');
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('permission_id');
            $table->enum('effect', ['allow', 'deny'])->default('allow');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->unique(['user_id', 'permission_id'], 'user_permissions_user_permission_unique');
            $table->index('user_id', 'user_permissions_user_id_idx');
            $table->index('permission_id', 'user_permissions_permission_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');

        Schema::table('role_permissions', function (Blueprint $table): void {
            $table->dropUnique('role_permissions_role_permission_unique');
            $table->dropIndex('role_permissions_role_id_idx');
            $table->dropIndex('role_permissions_permission_id_idx');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropIndex('permissions_module_action_idx');
            $table->dropColumn(['display_name', 'action', 'description', 'guard_name', 'is_system']);
        });
    }
};
