<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSearchIndexTable extends Migration
{
    public function up()
    {
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->string('searchable_type'); // User, Group, Message, etc.
            $table->unsignedBigInteger('searchable_id');
            $table->text('content'); // Searchable content
            $table->json('metadata')->nullable(); // Additional search data
            $table->timestamps();
            
            $table->index(['searchable_type', 'searchable_id']);
            $table->fullText('content');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('search_index');
    }
}