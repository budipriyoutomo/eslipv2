<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Enums\PayslipTemplateType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\DeliveryLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * A throw-away SQLite copy of the legacy CodeIgniter tables, with the columns the legacy code reads.
 */
beforeEach(function (): void {
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');

    $schema = Schema::connection('legacy');
    $schema->create('user', function (Blueprint $t): void {
        $t->increments('id');
        $t->string('username', 30)->nullable();
        $t->string('password', 50)->nullable();
        $t->string('role')->nullable();
        $t->string('nama', 100)->nullable();
        $t->string('pp', 20)->nullable();
    });
    $schema->create('tmstbrand', function (Blueprint $t): void {
        $t->increments('id');
        $t->string('namaPT')->nullable();
        $t->string('alamat')->nullable();
        $t->string('kota')->nullable();
        $t->string('telp')->nullable();
        $t->string('logo')->nullable();
        $t->integer('tipe')->nullable();
    });
    $schema->create('tstatus', function (Blueprint $t): void {
        $t->increments('id');
        $t->string('nik')->nullable();
        $t->string('nama')->nullable();
        $t->string('jabatan')->nullable();
        $t->string('periode')->nullable();
        $t->string('penerbit')->nullable();
        $t->string('namapt')->nullable();
        $t->string('email')->nullable();
        $t->string('status')->nullable();
    });

    $legacy = DB::connection('legacy');
    $legacy->table('user')->insert([
        ['username' => 'boss', 'password' => sha1('rahasia'), 'role' => 'SuperAdmin', 'nama' => 'Pak Boss', 'pp' => null],
        ['username' => 'admin', 'password' => strtoupper(sha1('admin123')), 'role' => 'Admin', 'nama' => 'Admin HR', 'pp' => null],
        ['username' => 'polos', 'password' => 'plain123', 'role' => 'User', 'nama' => '', 'pp' => null],
        ['username' => 'kosong', 'password' => '', 'role' => null, 'nama' => 'Kosong', 'pp' => null],
        ['username' => '', 'password' => sha1('x'), 'role' => 'User', 'nama' => 'Tanpa Username', 'pp' => null],
    ]);
    $legacy->table('tmstbrand')->insert([
        ['namaPT' => 'PT Sumber Tani', 'alamat' => 'Jl. A 1', 'kota' => 'Bandung', 'telp' => '022-1', 'logo' => null, 'tipe' => 1],
        ['namaPT' => 'PT Xtra Food', 'alamat' => 'Jl. X 4', 'kota' => 'Cimahi', 'telp' => '022-4', 'logo' => null, 'tipe' => 2],
    ]);
    // Make the second brand id 4, like the legacy XF brand.
    $legacy->table('tmstbrand')->where('id', 2)->update(['id' => 4]);
    $legacy->table('tstatus')->insert([
        ['nik' => '1001', 'nama' => 'Andi', 'jabatan' => 'Cook', 'periode' => '2019-01-01', 'penerbit' => '2019-01-31', 'namapt' => '1', 'email' => 'andi@example.com', 'status' => '1'],
        ['nik' => '1002', 'nama' => 'Budi', 'jabatan' => 'Waiter', 'periode' => '2019-01-01', 'penerbit' => '0000-00-00', 'namapt' => '4', 'email' => 'budi@example.com', 'status' => '0'],
    ]);
});

it('imports users, keeping sha1 hashes and hashing plaintext passwords', function (): void {
    $this->artisan('eslip:import-legacy', ['--only' => ['users']])->assertSuccessful();

    $boss = User::query()->where('username', 'boss')->sole();
    expect($boss->role)->toBe(UserRole::SuperAdmin)
        ->and($boss->name)->toBe('Pak Boss')
        ->and($boss->legacy_sha1)->toBeTrue()
        ->and($boss->password)->toBe(sha1('rahasia'));

    $admin = User::query()->where('username', 'admin')->sole();
    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->password)->toBe(sha1('admin123'));

    $plain = User::query()->where('username', 'polos')->sole();
    expect($plain->role)->toBe(UserRole::User)
        ->and($plain->name)->toBe('polos')
        ->and($plain->legacy_sha1)->toBeFalse()
        ->and(Hash::check('plain123', $plain->password))->toBeTrue();

    expect(User::query()->where('username', 'kosong')->sole()->legacy_sha1)->toBeFalse()
        ->and(User::query()->count())->toBe(4);
});

it('lets imported sha1 users log in and upgrades their hash', function (): void {
    $this->artisan('eslip:import-legacy', ['--only' => ['users']])->assertSuccessful();

    $this->post('/login', ['username' => 'boss', 'password' => 'rahasia'])->assertRedirect('/dashboard');

    expect(User::query()->where('username', 'boss')->sole()->legacy_sha1)->toBeFalse();
});

it('imports companies with legacy codes, template map and logos', function (): void {
    Storage::fake('local');
    $logoDir = sys_get_temp_dir().'/eslip-legacy-logos-'.uniqid();
    mkdir($logoDir);
    file_put_contents("{$logoDir}/logo-XF.png", 'png-bytes');

    $this->artisan('eslip:import-legacy', [
        '--only' => ['companies'],
        '--template' => ['4:xft'],
        '--mailer' => 'smtp_maharasa',
        '--from-address' => 'hrd@example.com',
        '--logo-dir' => $logoDir,
    ])->assertSuccessful();

    $st = Company::query()->where('code', 'ST')->sole();
    expect($st->name)->toBe('PT Sumber Tani')
        ->and($st->city)->toBe('Bandung')
        ->and($st->template_type)->toBe(PayslipTemplateType::Standard)
        ->and($st->mailer)->toBe('smtp_maharasa')
        ->and($st->from_address)->toBe('hrd@example.com')
        ->and($st->pdf_password_field)->toBe('nik')
        ->and($st->logo_path)->toBeNull();

    $xf = Company::query()->where('code', 'XF')->sole();
    expect($xf->template_type)->toBe(PayslipTemplateType::Xft)
        ->and($xf->logo_path)->toBe('logos/legacy-xf.png');
    Storage::disk('local')->assertExists('logos/legacy-xf.png');
});

it('imports tstatus rows as read-only delivery history', function (): void {
    $this->artisan('eslip:import-legacy', ['--only' => ['history']])->assertSuccessful();

    $andi = DeliveryLog::query()->where('recipient', 'andi@example.com')->sole();
    expect($andi->payslip_id)->toBeNull()
        ->and($andi->status)->toBe(DeliveryStatus::Sent)
        ->and($andi->attempted_at->toDateString())->toBe('2019-01-31')
        ->and($andi->context)->toMatchArray([
            'source' => 'tstatus', 'legacy_id' => 1, 'nik' => '1001', 'name' => 'Andi',
            'period' => '2019-01-01', 'company' => 'PT Sumber Tani',
        ])
        ->and($andi->periodLabel())->toBe('Januari 2019');

    $budi = DeliveryLog::query()->where('recipient', 'budi@example.com')->sole();
    expect($budi->status)->toBe(DeliveryStatus::Failed)
        ->and($budi->attempted_at->toDateString())->toBe('2019-01-01')
        ->and($budi->context['company'])->toBe('PT Xtra Food');
});

it('is idempotent and never overwrites edited data', function (): void {
    $this->artisan('eslip:import-legacy')->assertSuccessful();

    User::query()->where('username', 'boss')->update(['name' => 'Diubah Admin']);
    Company::query()->where('code', 'ST')->update(['mailer' => 'smtp_gmail']);

    $this->artisan('eslip:import-legacy')->assertSuccessful();

    expect(User::query()->count())->toBe(4)
        ->and(Company::query()->count())->toBe(2)
        ->and(DeliveryLog::query()->count())->toBe(2)
        ->and(User::query()->where('username', 'boss')->sole()->name)->toBe('Diubah Admin')
        ->and(Company::query()->where('code', 'ST')->sole()->mailer)->toBe('smtp_gmail');
});

it('imports only new tstatus rows on a later run', function (): void {
    $this->artisan('eslip:import-legacy', ['--only' => ['history']])->assertSuccessful();

    DB::connection('legacy')->table('tstatus')->insert([
        'nik' => '1003', 'nama' => 'Citra', 'periode' => '2019-02-01', 'penerbit' => '2019-02-28', 'namapt' => '1', 'email' => 'citra@example.com', 'status' => '1',
    ]);

    $this->artisan('eslip:import-legacy', ['--only' => ['history']])->assertSuccessful();

    expect(DeliveryLog::query()->count())->toBe(3);
});

it('writes nothing on a dry run', function (): void {
    $this->artisan('eslip:import-legacy', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0)
        ->and(Company::query()->count())->toBe(0)
        ->and(DeliveryLog::query()->count())->toBe(0);
});

it('reports plaintext passwords and companies to review', function (): void {
    $this->artisan('eslip:import-legacy')
        ->expectsOutputToContain('polos: password lama tersimpan plaintext')
        ->expectsOutputToContain('kosong: password kosong')
        ->expectsOutputToContain('Periksa mailer')
        ->assertSuccessful();
});

it('rejects a malformed template map', function (): void {
    $this->artisan('eslip:import-legacy', ['--only' => ['companies'], '--template' => ['xft']])
        ->expectsOutputToContain('Format --template salah')
        ->assertFailed();

    expect(Company::query()->count())->toBe(0);
});
