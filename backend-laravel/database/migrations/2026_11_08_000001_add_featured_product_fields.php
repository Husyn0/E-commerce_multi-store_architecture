<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false);
            $table->timestamp('featured_from')->nullable();
            $table->timestamp('featured_until')->nullable();
            $table->index(
                ['tenant_id', 'is_featured', 'featured_from', 'featured_until'],
                'ix_products_featured_period',
            );
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('ix_products_featured_period');
            $table->dropColumn(['is_featured', 'featured_from', 'featured_until']);
        });
    }
};
