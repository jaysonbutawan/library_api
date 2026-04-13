<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS after_borrow_transaction_update;

            CREATE TRIGGER after_borrow_transaction_update
            AFTER UPDATE ON borrow_transactions
            FOR EACH ROW
            BEGIN
                -- Fire only once: when fine_amount is first set (> 0)
                -- and the book has been returned (return_date is set)
                IF NEW.fine_amount > 0
                    AND NEW.return_date IS NOT NULL
                    AND (OLD.fine_amount IS NULL OR OLD.fine_amount = 0)
                THEN
                    INSERT INTO fines(
                        user_id,
                        transaction_id,
                        amount,
                        is_fully_paid,
                        created_at
                    )
                    VALUES (
                        NEW.user_id,
                        NEW.transaction_id,
                        NEW.fine_amount,
                        false,
                        NOW()
                    );
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS after_borrow_transaction_update;");
    }
};
