<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UrlSuppression extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
	    Schema::create('url_suppressions', function (Blueprint $table){
		    $table->increments('id');
		    $table->integer('collection_id');
		    $table->string('url_start_pattern');
		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
	   Schema::drop('url_suppressions');
    }
}
