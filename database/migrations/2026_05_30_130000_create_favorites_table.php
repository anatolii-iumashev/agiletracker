<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('favoritable_type')->nullable();
            $table->unsignedBigInteger('favoritable_id')->nullable();
            $table->timestamps();

            $table->index(['favoritable_type', 'favoritable_id']);
            $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'unique_user_favoritable');
            $table->unique(['user_id', 'url'], 'unique_user_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
