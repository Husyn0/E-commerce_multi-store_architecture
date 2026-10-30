<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'icon') || ! Schema::hasColumn('categories', 'image_url')) {
            Schema::table('categories', function (Blueprint $table): void {
                if (! Schema::hasColumn('categories', 'icon')) {
                    $table->string('icon', 32)->nullable();
                }
                if (! Schema::hasColumn('categories', 'image_url')) {
                    $table->string('image_url', 2048)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('product_images', 'media_type')) {
            Schema::table('product_images', function (Blueprint $table): void {
                $table->enum('media_type', ['image', 'video'])->default('image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'icon') || Schema::hasColumn('categories', 'image_url')) {
            Schema::table('categories', function (Blueprint $table): void {
                $columns = array_values(array_filter(
                    ['icon', 'image_url'],
                    fn (string $column): bool => Schema::hasColumn('categories', $column),
                ));
                $table->dropColumn($columns);
            });
        }

        if (Schema::hasColumn('product_images', 'media_type')) {
            Schema::table('product_images', function (Blueprint $table): void {
                $table->dropColumn('media_type');
            });
        }
    }
};
