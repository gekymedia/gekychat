<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        DB::table('bot_settings')->insert([
            [
                'key' => 'llm_provider',
                'value' => 'ollama',
                'type' => 'string',
                'description' => 'LLM Provider: ollama, openai, etc.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ollama_api_url',
                'value' => 'http://localhost:11434',
                'type' => 'string',
                'description' => 'Ollama API URL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ollama_model',
                'value' => 'llama3.2',
                'type' => 'string',
                'description' => 'Ollama model name (e.g., llama3.2, mistral, qwen)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'use_llm',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Enable LLM for bot responses',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'llm_temperature',
                'value' => '0.7',
                'type' => 'string',
                'description' => 'LLM temperature (0.0-1.0)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'llm_max_tokens',
                'value' => '500',
                'type' => 'string',
                'description' => 'Maximum tokens for LLM response',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
    }
};
