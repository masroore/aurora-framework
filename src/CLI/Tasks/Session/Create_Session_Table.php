<?php

class CLI_Tasks_Session_Create_Session_Table
{
    /**
     * Make changes to the database.
     */
    public function up(): void
    {
        Schema::table(Config::get('session.table'), static function ($table): void {
            $table->create();

            // The session table consists simply of an ID, a UNIX timestamp to
            // indicate the expiration time, and a blob field which will hold
            // the serialized form of the session payload.
            $table->string('id')->length(40)->primary('session_primary');

            $table->integer('last_activity');

            $table->text('data');
        });
    }

    /**
     * Revert the changes to the database.
     */
    public function down(): void
    {
        Schema::table(Config::get('session.table'), static function ($table): void {
            $table->drop();
        });
    }
}
