<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('collection_id')->unsigned();
            $table->bigInteger('created_by')->unsigned();
            $table->string('title');
            $table->string('path');
            $table->string('type');
            $table->bigInteger('size');
            $table->longText('text_content');
            $table->timestamps();
            $table->softDeletes();
        });
        //add foreign keys
        Schema::table('documents', function(Blueprint $table){
            $table->foreign('collection_id')->references('id')->on('collections');
            $table->foreign('created_by')->references('id')->on('users');
        });

       // Full Text Index
       DB::statement('ALTER TABLE documents ADD FULLTEXT fulltext_index (title, text_content)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('documents');
    }
}
