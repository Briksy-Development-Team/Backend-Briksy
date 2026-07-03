<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'orders' => 'reference_no',
            'inquiries' => 'reference_no',
            'property_listings' => 'generated_id',
            'services' => 'generated_id',
            'organizations' => 'generated_id',
            'plan_requests' => 'request_code',
        ];

        foreach ($tables as $tableName => $columnName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columnName): void {
                if (!Schema::hasColumn($tableName, $columnName)) {
                    $table->string($columnName)->nullable()->unique()->after('id');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'orders' => 'reference_no',
            'inquiries' => 'reference_no',
            'property_listings' => 'generated_id',
            'services' => 'generated_id',
            'organizations' => 'generated_id',
            'plan_requests' => 'request_code',
        ];

        foreach ($tables as $tableName => $columnName) {
            if (Schema::hasColumn($tableName, $columnName)) {
                Schema::table($tableName, function (Blueprint $table) use ($columnName): void {
                    $table->dropColumn($columnName);
                });
            }
        }
    }
};
