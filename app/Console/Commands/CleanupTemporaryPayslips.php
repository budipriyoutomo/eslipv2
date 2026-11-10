<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payslip\GeneratePayslipPdf;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Safety net for PDFs left behind when a worker died between generating and deleting a payslip PDF.
 */
#[Signature('eslip:cleanup-temp {--hours=24 : Delete temporary PDFs older than this many hours}')]
#[Description('Delete temporary payslip PDFs older than the given age')]
final class CleanupTemporaryPayslips extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk(GeneratePayslipPdf::DISK);
        $cutoff = now()->subHours(max(1, (int) $this->option('hours')))->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles(GeneratePayslipPdf::DIRECTORY) as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
                $deleted++;
            }
        }

        foreach (array_reverse($disk->allDirectories(GeneratePayslipPdf::DIRECTORY)) as $directory) {
            if ($disk->allFiles($directory) === []) {
                $disk->deleteDirectory($directory);
            }
        }

        $this->components->info("{$deleted} file PDF sementara dihapus.");

        return self::SUCCESS;
    }
}
