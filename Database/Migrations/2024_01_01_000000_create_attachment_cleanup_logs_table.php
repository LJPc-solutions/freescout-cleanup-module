<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttachmentCleanupLogsTable extends Migration
{
    public function up()
    {
        Schema::create('cleanup_attachment_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('attachment_id')->unsigned();
            $table->integer('conversation_id')->unsigned();
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->unsigned();
            $table->string('storage_path');
            $table->timestamp('attachment_created_at');
            $table->timestamp('cleaned_at');
            $table->timestamps();
            
            $table->index('attachment_id');
            $table->index('conversation_id');
            $table->index('cleaned_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cleanup_attachment_logs');
    }
}