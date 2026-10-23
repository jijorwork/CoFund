<?php

use App\Domain\Backups\Services\CreateEncryptedBackup;
use App\Domain\Backups\Services\StageEncryptedBackup;
use App\Domain\Backups\Services\VerifyEncryptedBackup;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->backupSource = tempnam(sys_get_temp_dir(), 'cofund-db-');
    $database = new SQLite3($this->backupSource);
    $database->exec('CREATE TABLE backup_probe (value TEXT NOT NULL)');
    $database->exec("INSERT INTO backup_probe (value) VALUES ('snapshot-data')");
    $database->close();
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $this->backupSource);
    config()->set('cofund.backups.disk', 'local');
    config()->set('cofund.backups.password', str_repeat('long-secret-', 4));
    $this->backupTarget = sys_get_temp_dir().'/cofund-recovery-'.bin2hex(random_bytes(8));
});

afterEach(function () {
    unlink($this->backupSource);
    if (is_dir($this->backupTarget)) {
        app(Filesystem::class)->deleteDirectory($this->backupTarget);
    }
});

test('backup creation encrypts database and private files then verifies the stored archive', function () {
    Storage::disk('local')->put('member-documents/proof.txt', 'private-document-data');
    $path = app(CreateEncryptedBackup::class)->handle();
    $manifest = app(VerifyEncryptedBackup::class)->handle($path);

    expect($manifest['format'])->toBe('cofund-backup-v1')
        ->and($manifest['files'])->toHaveKeys(['database.sqlite', 'storage/member-documents/proof.txt'])
        ->and(Storage::disk('local')->exists($path))->toBeTrue();

    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('local')->path($path)))->toBeTrue();
    expect($archive->statName('database.sqlite')['encryption_method'])->toBe(ZipArchive::EM_AES_256)
        ->and($archive->statName('storage/member-documents/proof.txt')['encryption_method'])->toBe(ZipArchive::EM_AES_256)
        ->and($archive->statName('manifest.json')['encryption_method'])->toBe(ZipArchive::EM_AES_256);
    $archive->close();

    config()->set('cofund.backups.password', str_repeat('wrong-secret-', 4));
    expect(fn () => app(VerifyEncryptedBackup::class)->handle($path))->toThrow(RuntimeException::class);
});

test('operator commands create and verify the backup without exposing its password', function () {
    expect(Artisan::call('cofund:backup:create'))->toBe(0);
    $path = Storage::disk('local')->files('backups')[0];
    expect(Artisan::call('cofund:backup:verify', ['path' => $path]))->toBe(0)
        ->and(Artisan::output())->toContain('Backup verified:')
        ->not->toContain((string) config('cofund.backups.password'));
    expect(Artisan::call('cofund:backup:stage', ['path' => $path, 'target' => $this->backupTarget]))->toBe(0)
        ->and(file_exists($this->backupTarget.'/database.sqlite'))->toBeTrue();
});

test('recovery stages a consistent snapshot and private files without overwriting live data', function () {
    Storage::disk('local')->put('member-documents/proof.txt', 'original-document');
    $path = app(CreateEncryptedBackup::class)->handle();
    $live = new SQLite3($this->backupSource);
    $live->exec("UPDATE backup_probe SET value = 'newer-data'");
    $live->close();
    Storage::disk('local')->put('member-documents/proof.txt', 'newer-document');

    app(StageEncryptedBackup::class)->handle($path, $this->backupTarget);
    $recovered = new SQLite3($this->backupTarget.'/database.sqlite', SQLITE3_OPEN_READONLY);
    expect($recovered->querySingle('SELECT value FROM backup_probe'))->toBe('snapshot-data')
        ->and(file_get_contents($this->backupTarget.'/storage/member-documents/proof.txt'))->toBe('original-document')
        ->and(Storage::disk('local')->get('member-documents/proof.txt'))->toBe('newer-document');
    $recovered->close();

    expect(fn () => app(StageEncryptedBackup::class)->handle($path, $this->backupTarget))->toThrow(RuntimeException::class);
    expect(file_get_contents($this->backupTarget.'/storage/member-documents/proof.txt'))->toBe('original-document');
});

test('backup creation refuses a missing password and unsupported database driver', function () {
    config()->set('cofund.backups.password', null);
    expect(fn () => app(CreateEncryptedBackup::class)->handle())->toThrow(RuntimeException::class);

    config()->set('cofund.backups.password', str_repeat('long-secret-', 4));
    config()->set('database.default', 'mysql');
    expect(fn () => app(CreateEncryptedBackup::class)->handle())->toThrow(RuntimeException::class);
    config()->set('database.default', 'sqlite');
});
