<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builder_projects', function (Blueprint $table): void {
            $table->string('state', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('builder_projects', function (Blueprint $table): void {
            $table->string('state', 10)->nullable()->change();
        });
    }
};
