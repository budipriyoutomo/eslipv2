<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

it('deletes temporary PDFs older than the cutoff only', function (): void {
    Storage::fake('local');
    $disk = Storage::disk('local');
    $disk->put('payslips/tmp/1/old.pdf', 'x');
    $disk->put('payslips/tmp/2/new.pdf', 'x');
    $disk->put('logos/keep.png', 'x');
    touch($disk->path('payslips/tmp/1/old.pdf'), now()->subHours(30)->getTimestamp());

    $this->artisan('eslip:cleanup-temp')
        ->expectsOutputToContain('1 file PDF sementara dihapus.')
        ->assertSuccessful();

    $disk->assertMissing('payslips/tmp/1/old.pdf');
    $disk->assertExists('payslips/tmp/2/new.pdf');
    $disk->assertExists('logos/keep.png');
    expect($disk->directories('payslips/tmp'))->toBe(['payslips/tmp/2']);
});

it('schedules the cleanup daily', function (): void {
    $events = collect(app(Schedule::class)->events())->map(fn ($event): string => (string) $event->command);

    expect($events->contains(fn (string $command): bool => str_contains($command, 'eslip:cleanup-temp')))->toBeTrue()
        ->and($events->contains(fn (string $command): bool => str_contains($command, 'queue:prune-batches')))->toBeTrue();
});
