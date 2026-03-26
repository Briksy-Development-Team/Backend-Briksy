<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->uuid('service_group_id')->nullable()->after('type_id');
            $table->foreign('service_group_id')->references('id')->on('service_groups')->nullOnDelete();
            $table->index('service_group_id');
        });

        $links = DB::table('service_group_services')
            ->select('service_id', 'service_group_id')
            ->orderBy('created_at')
            ->get();

        $serviceToGroup = [];
        foreach ($links as $link) {
            if (!array_key_exists($link->service_id, $serviceToGroup)) {
                $serviceToGroup[$link->service_id] = $link->service_group_id;
            }
        }

        foreach ($serviceToGroup as $serviceId => $serviceGroupId) {
            DB::table('services')
                ->where('id', $serviceId)
                ->update([
                    'service_group_id' => $serviceGroupId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropForeign(['service_group_id']);
            $table->dropIndex(['service_group_id']);
            $table->dropColumn('service_group_id');
        });
    }
};

