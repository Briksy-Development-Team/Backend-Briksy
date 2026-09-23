<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inquiries')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table): void {
            if (! Schema::hasColumn('inquiries', 'email_delivery_status')) {
                $table->string('email_delivery_status', 24)->default('pending')->after('status');
            }
            if (! Schema::hasColumn('inquiries', 'email_delivery_error')) {
                $table->text('email_delivery_error')->nullable()->after('email_delivery_status');
            }
            if (! Schema::hasColumn('inquiries', 'email_recipient')) {
                $table->string('email_recipient', 150)->nullable()->after('email_delivery_error');
            }
            if (! Schema::hasColumn('inquiries', 'email_sent_at')) {
                $table->timestamp('email_sent_at')->nullable()->after('email_recipient');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inquiries')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('inquiries', 'email_sent_at') ? 'email_sent_at' : null,
                Schema::hasColumn('inquiries', 'email_recipient') ? 'email_recipient' : null,
                Schema::hasColumn('inquiries', 'email_delivery_error') ? 'email_delivery_error' : null,
                Schema::hasColumn('inquiries', 'email_delivery_status') ? 'email_delivery_status' : null,
            ]);
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
