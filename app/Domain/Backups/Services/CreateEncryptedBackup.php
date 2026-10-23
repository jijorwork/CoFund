<?php

namespace App\Domain\Backups\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SQLite3;
use ZipArchive;

class CreateEncryptedBackup
{
    public function __construct(private readonly VerifyEncryptedBackup $verifier) {}

    public function handle(): string
    {
        $password = (string) config('cofund.backups.password');
        if (strlen($password) < 32) {
            throw new RuntimeException('Configure a backup password of at least 32 characters.');
        }
        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException('The configured database driver is not supported by this backup command.');
        }

        $databasePath = realpath((string) config('database.connections.sqlite.database'));
        if ($databasePath === false || ! is_file($databasePath)) {
            throw new RuntimeException('A file-backed SQLite database is required for backup.');
        }

        $temporaryDirectory = sys_get_temp_dir().'/cofund-backup-'.bin2hex(random_bytes(12));
        if (! mkdir($temporaryDirectory, 0700)) {
            throw new RuntimeException('Unable to create a private backup workspace.');
        }

        try {
            $snapshotPath = $temporaryDirectory.'/database.sqlite';
            $source = new SQLite3($databasePath, SQLITE3_OPEN_READONLY);
            $destination = new SQLite3($snapshotPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            try {
                if (! $source->backup($destination)) {
                    throw new RuntimeException('SQLite snapshot failed.');
                }
            } finally {
                $destination->close();
                $source->close();
            }

            $archivePath = $temporaryDirectory.'/backup.zip';
            $archive = new ZipArchive;
            if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the backup archive.');
            }
            $archive->setPassword($password);
            try {
                $files = ['database.sqlite' => $snapshotPath];
                $localDisk = Storage::disk('local');
                $root = realpath($localDisk->path(''));
                if ($root === false) {
                    throw new RuntimeException('Private storage is unavailable.');
                }
                foreach ($localDisk->allFiles() as $file) {
                    if (str_starts_with($file, 'backups/')) {
                        continue;
                    }
                    $path = realpath($localDisk->path($file));
                    if ($path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path) || is_link($localDisk->path($file))) {
                        throw new RuntimeException('Private storage contains an unsafe file path.');
                    }
                    $files['storage/'.$file] = $path;
                }

                $manifestFiles = [];
                foreach ($files as $name => $path) {
                    $manifestFiles[$name] = [
                        'sha256' => hash_file('sha256', $path),
                        'bytes' => filesize($path),
                    ];
                    if (! $archive->addFile($path, $name) || ! $archive->setEncryptionName($name, ZipArchive::EM_AES_256)) {
                        throw new RuntimeException('Unable to encrypt a backup entry.');
                    }
                }
                $manifest = json_encode([
                    'format' => 'cofund-backup-v1',
                    'created_at' => now()->utc()->toIso8601String(),
                    'database_driver' => 'sqlite',
                    'files' => $manifestFiles,
                ], JSON_THROW_ON_ERROR);
                if (! $archive->addFromString('manifest.json', $manifest) || ! $archive->setEncryptionName('manifest.json', ZipArchive::EM_AES_256)) {
                    throw new RuntimeException('Unable to encrypt the backup manifest.');
                }
            } finally {
                $archive->close();
            }

            $backupPath = 'backups/'.now()->utc()->format('Ymd-His').'-'.bin2hex(random_bytes(8)).'.zip';
            $stream = fopen($archivePath, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Unable to read the backup archive.');
            }
            try {
                if (! Storage::disk((string) config('cofund.backups.disk'))->put($backupPath, $stream)) {
                    throw new RuntimeException('Unable to store the encrypted backup.');
                }
            } finally {
                fclose($stream);
            }
            try {
                $this->verifier->handle($backupPath);
            } catch (\Throwable $exception) {
                Storage::disk((string) config('cofund.backups.disk'))->delete($backupPath);
                throw $exception;
            }

            return $backupPath;
        } finally {
            foreach (glob($temporaryDirectory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($temporaryDirectory);
        }
    }
}
