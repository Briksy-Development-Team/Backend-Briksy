<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        Schema::table('email_templates', function (Blueprint $table): void {
            if (!Schema::hasColumn('email_templates', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('key');
            }

            if (!Schema::hasColumn('email_templates', 'module')) {
                $table->string('module')->nullable()->after('variables');
            }

            if (!Schema::hasColumn('email_templates', 'event_key')) {
                $table->string('event_key')->nullable()->after('module');
            }

            if (!Schema::hasColumn('email_templates', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
            }
        });

        DB::table('email_templates')
            ->select(['id', 'key', 'status'])
            ->orderBy('created_at')
            ->get()
            ->each(function ($template): void {
                $updates = [];

                if (!Schema::hasColumn('email_templates', 'slug') || blank($template->key)) {
                    return;
                }

                $updates['slug'] = $template->key;

                if (Schema::hasColumn('email_templates', 'is_active')) {
                    $updates['is_active'] = $template->status === 'active';
                }

                DB::table('email_templates')->where('id', $template->id)->update($updates);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        Schema::table('email_templates', function (Blueprint $table): void {
            foreach (['slug', 'module', 'event_key', 'is_active'] as $column) {
                if (Schema::hasColumn('email_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
