<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuickbooksTokensTable extends Migration
{
    public function up()
    {
        Schema::create('quickbooks_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('realm_id')->unique();
            $table->string('access_token');
            $table->string('refresh_token');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('quickbooks_tokens');
    }
}
