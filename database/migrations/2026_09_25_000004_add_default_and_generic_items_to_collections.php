<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('name');
            $table->index(['user_id', 'is_default']);
        });

        Schema::create('collection_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('collection_id');
            $table->string('collectable_type');
            $table->uuid('collectable_id');
            $table->timestamps();

            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->unique(['collection_id', 'collectable_type', 'collectable_id'], 'collection_items_unique');
            $table->index(['collectable_type', 'collectable_id']);
        });

        DB::table('collection_property')->orderBy('id')->each(function (object $item): void {
            DB::table('collection_items')->insertOrIgnore([
                'id' => $item->id,
                'collection_id' => $item->collection_id,
                'collectable_type' => 'App\\Models\\PropertyListing',
                'collectable_id' => $item->property_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_items');
        Schema::table('collections', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'is_default']);
            $table->dropColumn('is_default');
        });
    }
};
