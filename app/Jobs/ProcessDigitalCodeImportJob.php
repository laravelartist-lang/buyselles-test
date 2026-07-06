<?php

namespace App\Jobs;

use App\Imports\DigitalProductCodesImport;
use App\Models\Admin;
use App\Models\Seller;
use App\Notifications\DigitalCodeImportCompleteNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ProcessDigitalCodeImportJob implements ShouldQueue
{
    use Queueable;

    /** Number of times the job may be attempted. */
    public int $tries = 1;

    /** Maximum number of seconds the job should run. */
    public int $timeout = 300;

    /**
     * @param  string  $filePath  Absolute path to the uploaded xlsx/csv file
     * @param  string  $importedBy  Display name of the user who triggered the import
     * @param  int  $adminId  Admin to notify (0 = all super-admins, ignored if sellerId > 0)
     * @param  int  $sellerId  Seller to notify (0 = notify admin instead)
     */
    public function __construct(
        private readonly string $filePath,
        private readonly string $importedBy = 'Admin',
        private readonly int $adminId = 0,
        private readonly int $sellerId = 0,
    ) {
        $this->onQueue('slow');
    }

    public function handle(): void
    {
        try {
            $importer = app(DigitalProductCodesImport::class, ['sellerId' => $this->sellerId]);
            Excel::import($importer, $this->filePath);

            $summary = $importer->getSummary();
        } catch (\Throwable $e) {
            Log::error('DigitalCodeImport job failed', [
                'file' => $this->filePath,
                'error' => $e->getMessage(),
            ]);

            $summary = [
                'processed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => ['Job crashed: '.$e->getMessage()],
            ];
        }

        $this->notifyAdmins($summary);
        if (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }
    }

    /**
     * @param  array{processed: int, skipped: int, failed: int, errors: array<int, string>}  $summary
     */
    private function notifyAdmins(array $summary): void
    {
        $notification = new DigitalCodeImportCompleteNotification($summary, $this->importedBy);

        try {
            // Notify the seller who triggered the import
            if ($this->sellerId > 0) {
                $seller = Seller::find($this->sellerId);
                if ($seller) {
                    $seller->notify($notification);
                }

                return;
            }

            // Notify a specific admin
            if ($this->adminId > 0) {
                $admin = Admin::find($this->adminId);
                if ($admin) {
                    $admin->notify($notification);

                    return;
                }
            }

            // Fallback: notify all active super-admins
            Admin::query()
                ->where('status', 1)
                ->where('admin_role_id', 1)
                ->get()
                ->each(fn (Admin $admin) => $admin->notify($notification));
        } catch (\Throwable $e) {
            Log::warning('DigitalCodeImport: failed to send notification email', [
                'error' => $e->getMessage(),
                'summary' => $summary,
            ]);
        }
    }
}
