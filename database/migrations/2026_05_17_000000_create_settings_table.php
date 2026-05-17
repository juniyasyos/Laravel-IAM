<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('key')->unique()->index();
            $table->string('group')->index();  // 'sso', 'iam', 'auth', 'fortify'
            $table->longText('value');
            $table->string('type')->default('string'); // string, integer, boolean, array, json
            $table->text('description')->nullable();

            // Input configuration for UI
            $table->string('input_type')->default('text'); // text, number, toggle, select, textarea, email, url
            $table->json('select_options')->nullable();     // For select/radio/checkbox
            $table->json('validation_rules')->nullable();   // ['required', 'min:300', 'max:3600']

            // Security & metadata
            $table->boolean('is_readonly')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->string('environment')->nullable();
            $table->string('category')->nullable();  // For UI grouping

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['group', 'key']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
