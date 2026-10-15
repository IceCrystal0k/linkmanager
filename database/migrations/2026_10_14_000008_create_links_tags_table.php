<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('links_tags', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedInteger('link_id');
            $table->unsignedInteger('tag_id');
            $table->primary(['link_id', 'tag_id']);
            $table->index('tag_id', 'links_tags_tag_id_foreign');

            $table->foreign('link_id', 'links_tags_link_id_foreign')
                ->references('id')->on('links')->onDelete('cascade');
            $table->foreign('tag_id', 'links_tags_tag_id_fk')
                ->references('id')->on('tags')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('links_tags');
    }
};
