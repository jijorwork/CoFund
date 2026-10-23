<?php

namespace App\Domain\Backups\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SQLite3;
use ZipArchive;

class StageEncryptedBackup
{
    public function __construct(
        private readonly VerifyEncryptedBackup $verifier,
        private readonly Filesystem $files,
    ) {}

    public function handle(string $backupPath, string $targetDirectory): string
    {
        if (! str_starts_with($targetDirectory, DIRECTORY_SEPARATOR)
            || $targetDirectory === DIRECTORY_SEPARATOR
            || file_exists($targetDirectory)
            || is_link($targetDirectory)
            || realpath(dirname($targetDirectory)) === false) {
            throw new RuntimeException('Recovery target must be a new absolute directory under an existing parent.');
        }
        $manifest = $this->verifier->handle($backupPath);
        $input = Storage::disk((string) config('cofund.backups.disk'))->readStream($backupPath);
        if (! is_resource($input)) {
            throw new RuntimeException('Backup archive was not found.');
        }
        $temporaryPath = tempnam(sys_get_temp_dir(), 'cofund-stage-');
        if ($temporaryPath === false) {
            fclose($input);
            throw new RuntimeException('Unable to create a private recovery workspace.');
        }

        try {
            $output = fopen($temporaryPath, 'wb');
            if ($output === false) {
                throw new RuntimeException('Unable to open the recovery workspace.');
            }
            try {
                if (stream_copy_to_stream($input, $output) === false) {
                    throw new RuntimeException('Unable to copy the backup archive.');
                }
            } finally {
                fclose($input);
                fclose($output);
            }

            if (! mkdir($targetDirectory, 0700)) {
                throw new RuntimeException('Unable to create the recovery target.');
            }
            try {
                $archive = new ZipArchive;
                if ($archive->open($temporaryPath, ZipArchive::CHECKCONS) !== true) {
                    throw new RuntimeException('Backup archive is corrupt.');
                }
                $archive->setPassword((string) config('cofund.backups.password'));
                try {
                    foreach (array_keys($manifest['files']) as $name) {
                        $destination = $targetDirectory.'/'.$name;
                        if (! $this->files->isDirectory(dirname($destination))
                            && ! $this->files->makeDirectory(dirname($destination), 0700, true)) {
                            throw new RuntimeException('Unable to create a recovery directory.');
                        }
                        $source = $archive->getStream($name);
                        $target = fopen($destination, 'xb');
                        if ($source === false || $target === false) {
                            if (is_resource($source)) {
                                fclose($source);
                            }
                            throw new RuntimeException('Unable to stage a backup entry.');
                        }
                        try {
                            if (stream_copy_to_stream($source, $target) === false) {
                                throw new RuntimeException('Unable to copy a backup entry.');
                            }
                        } finally {
                            fclose($source);
                            fclose($target);
                        }
                        if (filesize($destination) !== $manifest['files'][$name]['bytes']
                            || ! hash_equals($manifest['files'][$name]['sha256'], (string) hash_file('sha256', $destination))) {
                            throw new RuntimeException('Staged backup entry checksum does not match the manifest.');
                        }
                        chmod($destination, 0600);
                    }
                } finally {
                    $archive->close();
                }

                if ($manifest['database_driver'] === 'sqlite') {
                    $database = new SQLite3($targetDirectory.'/database.sqlite', SQLITE3_OPEN_READONLY);
                    try {
                        if ($database->querySingle('PRAGMA integrity_check') !== 'ok') {
                            throw new RuntimeException('Recovered SQLite snapshot failed integrity check.');
                        }
                    } finally {
                        $database->close();
                    }
                }
                $manifestPath = $targetDirectory.'/recovery-manifest.json';
                if (file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
                    throw new RuntimeException('Unable to write recovery metadata.');
                }
                chmod($manifestPath, 0600);

                return $targetDirectory;
            } catch (\Throwable $exception) {
                $this->files->deleteDirectory($targetDirectory);
                throw $exception;
            }
        } finally {
            unlink($temporaryPath);
        }
    }
}
