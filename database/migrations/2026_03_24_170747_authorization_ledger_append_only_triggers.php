<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS authorization_ledger_block_update
BEFORE UPDATE ON authorization_ledger
BEGIN
  SELECT RAISE(ABORT, 'authorization_ledger is append-only');
END;
SQL);
            DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS authorization_ledger_block_delete
BEFORE DELETE ON authorization_ledger
BEGIN
  SELECT RAISE(ABORT, 'authorization_ledger is append-only');
END;
SQL);

            return;
        }

        if ($driver === 'mysql') {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER authorization_ledger_block_update BEFORE UPDATE ON authorization_ledger
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'authorization_ledger is append-only';
SQL);
            DB::unprepared(<<<'SQL'
CREATE TRIGGER authorization_ledger_block_delete BEFORE DELETE ON authorization_ledger
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'authorization_ledger is append-only';
SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS authorization_ledger_block_update;');
            DB::unprepared('DROP TRIGGER IF EXISTS authorization_ledger_block_delete;');

            return;
        }

        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS authorization_ledger_block_update;');
            DB::unprepared('DROP TRIGGER IF EXISTS authorization_ledger_block_delete;');
        }
    }
};
