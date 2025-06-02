<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyAccessTokenColumnInQuickbooksTokensTable extends Migration
{
    public function up()
    {
        Schema::table('quickbooks_tokens', function (Blueprint $table) {
            // Change the 'access_token' column to be larger
            $table->string('access_token', 1000)->change(); // Increase length to 1000
        });
    }

    public function down()
    {
        Schema::table('quickbooks_tokens', function (Blueprint $table) {
            // Rollback the column size change (if needed)
            $table->string('access_token', 255)->change();
        });
    }
}
