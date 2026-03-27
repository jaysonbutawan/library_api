<?php

namespace App\Console\Commands;

use App\Http\Services\BorrowTransactionService;
use Illuminate\Console\Command;

class CleanupExpiredBorrowRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-expired-borrow-requests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(BorrowTransactionService $service)
    {
        $result = $service->cleanupExpiredRequests();
        $this->info("Cleaned up {$result['expired_count']} requests");
    }
}
