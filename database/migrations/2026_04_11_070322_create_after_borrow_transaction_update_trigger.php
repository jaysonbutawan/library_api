<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS after_borrow_transaction_update;

            CREATE TRIGGER after_borrow_transaction_update
            AFTER UPDATE ON borrow_transactions
            FOR EACH ROW
            BEGIN
                DECLARE existing_total DECIMAL(10,2);

                IF NEW.fine_amount > 0 AND NEW.status = 'overdue' THEN

                    SELECT IFNULL(SUM(amount), 0)
                    INTO existing_total
                    FROM fines
                    WHERE user_id = NEW.user_id
                    AND paid_status = 'unpaid';

                    INSERT INTO fines(
                        user_id,
                        transaction_id,
                        amount,
                        paid_status,
                        created_at
                    )
                    VALUES (
                        NEW.user_id,
                        NEW.transaction_id,
                        existing_total + NEW.fine_amount,
                        'unpaid',
                        NOW()
                    );

                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS after_borrow_transaction_update;
        ");
    }
};
