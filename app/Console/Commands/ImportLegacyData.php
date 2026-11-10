<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DeliveryStatus;
use App\Enums\PayslipTemplateType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\DeliveryLog;
use App\Models\User;
use App\Support\LegacyPasswordHasher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * Copy users, companies (tmstbrand) and delivery history (tstatus) from the legacy CodeIgniter database.
 *
 * Idempotent: existing users (by username) and companies (by code) are never overwritten, because admins may
 * have edited them in the new app; tstatus rows are matched by their legacy id. See DECISIONS D-041.
 */
#[Signature('eslip:import-legacy
    {--connection=legacy : Database connection of the legacy CodeIgniter app}
    {--only=* : Limit to users, companies and/or history}
    {--mailer=smtp_bogajabar : Mailer for newly created companies}
    {--from-address= : Sender address for new companies (default: MAIL_FROM_ADDRESS)}
    {--template=* : Template per legacy brand id, e.g. --template=4:xft (default: standard)}
    {--logo-dir= : Legacy "image" folder; logo-{CODE}.png files are copied for new companies}
    {--dry-run : Report what would happen without writing anything}')]
#[Description('Import users, companies and delivery history from the legacy e-slip database')]
final class ImportLegacyData extends Command
{
    /**
     * Legacy genpdf() hard-coded the logo per brand id (DECISIONS D-009); reused as company code.
     */
    private const array LEGACY_BRAND_CODES = [1 => 'ST', 2 => 'PL', 3 => 'BJ', 4 => 'XF'];

    /** @var array<string, array{created: int, skipped: int, notes: list<string>}> */
    private array $report = [];

    public function handle(LegacyPasswordHasher $legacyHasher): int
    {
        $legacy = DB::connection((string) $this->option('connection'));
        $only = (array) $this->option('only');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('Dry run: nothing will be written.');
        }

        $steps = [
            'users' => fn () => $this->importUsers($legacy, $dryRun),
            'companies' => fn () => $this->importCompanies($legacy, $dryRun),
            'history' => fn () => $this->importHistory($legacy, $dryRun),
        ];

        foreach ($steps as $name => $step) {
            if ($only !== [] && ! in_array($name, $only, true)) {
                continue;
            }

            $this->report[$name] = ['created' => 0, 'skipped' => 0, 'notes' => []];

            try {
                $dryRun ? $step() : DB::transaction($step);
            } catch (Throwable $e) {
                $this->components->error("{$name}: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->printReport();

        return self::SUCCESS;
    }

    private function importUsers(ConnectionInterface $legacy, bool $dryRun): void
    {
        $existing = User::query()->pluck('username')->map(fn (string $u): string => Str::lower($u))->flip();

        foreach ($legacy->table('user')->orderBy('id')->get() as $row) {
            $username = trim((string) ($row->username ?? ''));

            if ($username === '') {
                $this->note('users', "user id {$row->id}: username kosong, dilewati.");
                $this->report['users']['skipped']++;

                continue;
            }

            if ($existing->has(Str::lower($username))) {
                $this->report['users']['skipped']++;

                continue;
            }

            $stored = trim((string) ($row->password ?? ''));
            $isSha1 = LegacyPasswordHasher::looksLikeSha1($stored);

            if (! $isSha1) {
                // The legacy user form stored raw passwords (DECISIONS D-021).
                $this->note('users', $stored === ''
                    ? "{$username}: password kosong, diberi password acak (reset lewat menu User)."
                    : "{$username}: password lama tersimpan plaintext, langsung di-hash bcrypt.");
            }

            if (! $dryRun) {
                $user = new User([
                    'username' => $username,
                    'name' => trim((string) ($row->nama ?? '')) ?: $username,
                    'email' => null,
                    'role' => UserRole::fromLegacy((string) ($row->role ?? '')),
                ]);
                $user->password = $isSha1 ? strtolower($stored) : Hash::make($stored === '' ? Str::random(40) : $stored);
                $user->legacy_sha1 = $isSha1;
                $user->save();
            }

            $existing->put(Str::lower($username), true);
            $this->report['users']['created']++;
        }
    }

    private function importCompanies(ConnectionInterface $legacy, bool $dryRun): void
    {
        $templates = $this->templateMap();
        $fromAddress = (string) ($this->option('from-address') ?: config('mail.from.address'));

        foreach ($legacy->table('tmstbrand')->orderBy('id')->get() as $row) {
            $id = (int) $row->id;
            $code = self::LEGACY_BRAND_CODES[$id] ?? "BRAND-{$id}";

            if (Company::query()->where('code', $code)->exists()) {
                $this->report['companies']['skipped']++;

                continue;
            }

            $name = trim((string) ($row->namaPT ?? '')) ?: $code;

            if (! $dryRun) {
                Company::query()->create([
                    'code' => $code,
                    'name' => $name,
                    'address' => $this->nullableString($row, 'alamat'),
                    'city' => $this->nullableString($row, 'kota'),
                    'phone' => $this->nullableString($row, 'telp'),
                    'logo_path' => $this->copyLogo($code),
                    'template_type' => $templates[$id] ?? PayslipTemplateType::Standard,
                    'mailer' => (string) $this->option('mailer'),
                    'from_address' => $fromAddress,
                    'from_name' => 'HRD '.$name,
                    'pdf_password_field' => 'nik',
                ]);
            }

            $this->note('companies', sprintf('%s → %s, template %s.', $code, $name, ($templates[$id] ?? PayslipTemplateType::Standard)->label()));
            $this->report['companies']['created']++;
        }

        if ($this->report['companies']['created'] > 0) {
            $this->note('companies', 'Periksa mailer, email pengirim, kota dan logo di menu PT sebelum mengirim.');
        }
    }

    private function importHistory(ConnectionInterface $legacy, bool $dryRun): void
    {
        $brands = $legacy->table('tmstbrand')->pluck('namaPT', 'id');

        $imported = DeliveryLog::query()
            ->whereNull('payslip_id')
            ->get(['context'])
            ->map(fn (DeliveryLog $log): mixed => $log->context['legacy_id'] ?? null)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->flip();

        $legacy->table('tstatus')->orderBy('id')->chunk(500, function ($rows) use ($brands, $imported, $dryRun): void {
            $inserts = [];
            $now = now();

            /** @var stdClass $row */
            foreach ($rows as $row) {
                if ($imported->has((int) $row->id)) {
                    $this->report['history']['skipped']++;

                    continue;
                }

                $period = $this->legacyDate($row->periode ?? null);
                $issued = $this->legacyDate($row->penerbit ?? null);

                $inserts[] = [
                    'payslip_id' => null,
                    'status' => $this->legacyStatus($row->status ?? null)->value,
                    'recipient' => $this->nullableString($row, 'email'),
                    'error_message' => null,
                    'context' => json_encode([
                        'source' => 'tstatus',
                        'legacy_id' => (int) $row->id,
                        'nik' => $this->nullableString($row, 'nik'),
                        'name' => $this->nullableString($row, 'nama'),
                        'position' => $this->nullableString($row, 'jabatan'),
                        'period' => $period?->toDateString(),
                        'issued_at' => $issued?->toDateString(),
                        'company' => $brands[$row->namapt ?? null] ?? (string) ($row->namapt ?? ''),
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    // tstatus has no timestamp; the issue date is the closest thing to "sent at".
                    'attempted_at' => ($issued ?? $period ?? CarbonImmutable::createFromTimestamp(0))->toDateTimeString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! $dryRun && $inserts !== []) {
                DeliveryLog::query()->insert($inserts);
            }

            $this->report['history']['created'] += count($inserts);
        });
    }

    /**
     * @return array<int, PayslipTemplateType>
     */
    private function templateMap(): array
    {
        $map = [];

        foreach ((array) $this->option('template') as $pair) {
            [$id, $type] = array_pad(explode(':', (string) $pair, 2), 2, '');
            $template = PayslipTemplateType::tryFrom(strtolower(trim($type)));

            if (! ctype_digit(trim($id)) || $template === null) {
                throw new \InvalidArgumentException("Format --template salah: [{$pair}]. Contoh: --template=4:xft");
            }

            $map[(int) $id] = $template;
        }

        return $map;
    }

    private function copyLogo(string $code): ?string
    {
        $dir = $this->option('logo-dir');
        $source = is_string($dir) && $dir !== '' ? rtrim($dir, '/\\').DIRECTORY_SEPARATOR."logo-{$code}.png" : null;

        if ($source === null || ! is_file($source)) {
            return null;
        }

        $target = 'logos/legacy-'.strtolower($code).'.png';
        Storage::disk('local')->put($target, (string) file_get_contents($source));

        return $target;
    }

    private function legacyStatus(mixed $value): DeliveryStatus
    {
        // Legacy stored the boolean result of $this->email->send(): 1 / "1" = sent, anything else = failed.
        return in_array($value, [1, '1', true, 'true'], true) ? DeliveryStatus::Sent : DeliveryStatus::Failed;
    }

    private function legacyDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        return rescue(fn (): CarbonImmutable => CarbonImmutable::parse(substr($value, 0, 10)), null, report: false);
    }

    private function nullableString(stdClass $row, string $column): ?string
    {
        $value = $row->{$column} ?? null;

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private function note(string $step, string $message): void
    {
        $this->report[$step]['notes'][] = $message;
    }

    private function printReport(): void
    {
        $this->table(
            ['Data', 'Dibuat', 'Dilewati (sudah ada)'],
            array_map(
                fn (string $step, array $r): array => [$step, $r['created'], $r['skipped']],
                array_keys($this->report),
                $this->report,
            ),
        );

        foreach ($this->report as $step => $r) {
            foreach ($r['notes'] as $note) {
                $this->line("  <comment>[{$step}]</comment> {$note}");
            }
        }
    }
}
