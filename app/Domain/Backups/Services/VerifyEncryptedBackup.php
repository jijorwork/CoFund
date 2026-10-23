<?php

namespace App\Domain\Backups\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class VerifyEncryptedBackup
{
    /** @return array{format:string,created_at:string,database_driver:string,files:array<string,array{sha256:string,bytes:int}>} */
    public function handle(string $backupPath): array
    {
        if (! preg_match('/\Abackups\/[a-zA-Z0-9._-]+\.zip\z/', $backupPath)) {
            throw new RuntimeException('Invalid backup path.');
        }
        $password = (string) config('cofund.backups.password');
        if (strlen($password) < 32) {
            throw new RuntimeException('Configure a backup password of at least 32 characters.');
        }
        $input = Storage::disk((string) config('cofund.backups.disk'))->readStream($backupPath);
        if (! is_resource($input)) {
            throw new RuntimeException('Backup archive was not found.');
        }
        $temporaryPath = tempnam(sys_get_temp_dir(), 'cofund-verify-');
        if ($temporaryPath === false) {
            fclose($input);
            throw new RuntimeException('Unable to create a private verification workspace.');
        }

        try {
            $output = fopen($temporaryPath, 'wb');
            if ($output === false) {
                throw new RuntimeException('Unable to open the verification workspace.');
            }
            try {
                if (stream_copy_to_stream($input, $output) === false) {
                    throw new RuntimeException('Unable to copy the backup archive.');
                }
            } finally {
                fclose($input);
                fclose($output);
            }

            $archive = new ZipArchive;
            if ($archive->open($temporaryPath, ZipArchive::CHECKCONS) !== true) {
                throw new RuntimeException('Backup archive is corrupt.');
            }
            $archive->setPassword($password);
            try {
                $rawManifest = $archive->getFromName('manifest.json');
                if (($archive->statName('manifest.json')['encryption_method'] ?? null) !== ZipArchive::EM_AES_256) {
                    throw new RuntimeException('Backup manifest is not encrypted.');
                }
                if ($rawManifest === false) {
                    throw new RuntimeException('Backup manifest cannot be decrypted.');
                }
                $manifest = json_decode($rawManifest, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($manifest) || ($manifest['format'] ?? null) !== 'cofund-backup-v1'
                    || ! in_array($manifest['database_driver'] ?? null, ['sqlite', 'mysql'], true)
                    || ! is_string($manifest['created_at'] ?? null) || ! is_array($manifest['files'] ?? null)) {
                    throw new RuntimeException('Backup manifest is invalid.');
                }
                if ($archive->numFiles !== count($manifest['files']) + 1) {
                    throw new RuntimeException('Backup entry count does not match the manifest.');
                }
                $verifiedFiles = [];
                foreach ($manifest['files'] as $name => $expected) {
                    if (! is_string($name) || ! preg_match('/\A(?:database\.(?:sqlite|sql)|storage\/(?!backups\/)(?:[a-zA-Z0-9._-]+\/)*[a-zA-Z0-9._-]+)\z/', $name)
                        || ! is_array($expected) || ! is_string($expected['sha256'] ?? null) || ! is_int($expected['bytes'] ?? null)) {
                        throw new RuntimeException('Backup manifest contains an invalid entry.');
                    }
                    foreach (explode('/', $name) as $segment) {
                        if ($segment === '.' || $segment === '..') {
                            throw new RuntimeException('Backup manifest contains an unsafe path.');
                        }
                    }
                    if (($archive->statName($name)['encryption_method'] ?? null) !== ZipArchive::EM_AES_256) {
                        throw new RuntimeException('Backup entry is not encrypted.');
                    }
                    $stream = $archive->getStream($name);
                    if ($stream === false) {
                        throw new RuntimeException('Backup entry cannot be decrypted.');
                    }
                    $hash = hash_init('sha256');
                    $bytes = 0;
                    try {
                        while (! feof($stream)) {
                            $chunk = fread($stream, 1024 * 1024);
                            if ($chunk === false) {
                                throw new RuntimeException('Backup entry cannot be read.');
                            }
                            $bytes += strlen($chunk);
                            hash_update($hash, $chunk);
                        }
                    } finally {
                        fclose($stream);
                    }
                    if ($bytes !== $expected['bytes'] || ! hash_equals($expected['sha256'], hash_final($hash))) {
                        throw new RuntimeException('Backup entry checksum does not match the manifest.');
                    }
                    $verifiedFiles[$name] = ['sha256' => $expected['sha256'], 'bytes' => $expected['bytes']];
                }
                $databaseEntry = $manifest['database_driver'] === 'sqlite' ? 'database.sqlite' : 'database.sql';
                if (! array_key_exists($databaseEntry, $manifest['files'])
                    || array_key_exists($databaseEntry === 'database.sqlite' ? 'database.sql' : 'database.sqlite', $manifest['files'])) {
                    throw new RuntimeException('Backup database snapshot is missing.');
                }

                return [
                    'format' => 'cofund-backup-v1',
                    'created_at' => $manifest['created_at'],
                    'database_driver' => $manifest['database_driver'],
                    'files' => $verifiedFiles,
                ];
            } finally {
                $archive->close();
            }
        } finally {
            unlink($temporaryPath);
        }
    }
}
