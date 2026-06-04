<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop old tables that depend on messages
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('message_reactions');

        // 2. Drop foreign keys on conversations
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['last_message_id']);
        });

        // 3. Drop foreign keys on participants
        Schema::table('participants', function (Blueprint $table) {
            $table->dropForeign(['last_read_message_id']);
        });

        // 4. Drop messages table
        Schema::dropIfExists('messages');

        // 5. Alter columns to string
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('last_message_id')->nullable()->change();
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->string('last_read_message_id')->nullable()->change();
            $table->string('last_delivered_message_id')->nullable()->after('last_read_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We cannot fully revert dropping the messages table without recreating the schema,
        // which is out of scope for this transition migration.
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('last_delivered_message_id');
        });
    }
};
