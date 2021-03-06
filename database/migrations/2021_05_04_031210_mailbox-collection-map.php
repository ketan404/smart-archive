<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MailboxCollectionMap extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('collectionmailboxes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('address')->unique();
            $table->text('credentials')->nullable();
            $table->integer('collection_id')->unsigned();
            $table->timestamps();

            $table->foreign('collection_id')->references('id')->on('collections');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('collectionmailboxes');
    }
}
