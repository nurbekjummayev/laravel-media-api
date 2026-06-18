<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('file');
            $table->string('name');
            $table->string('ext', 16);
            $table->string('mime', 191)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('hash', 64)->nullable();
            $table->string('type', 16)->default('private'); // public|private
            $table->unsignedBigInteger('owner_id')->nullable()->index(); // egasi — config('media.owner_model')
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('attached')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
