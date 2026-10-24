<?php

namespace App\Domain\Backups\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ExerciseMysqlRestore
{
    public function __construct(
        private readonly VerifyEncryptedBackup $verifier,
        private readonly StageEncryptedBackup $stager,
        private readonly Filesystem $files,
    ) {}

    /** @return array{tables:int,migration_rows:int,database_driver:string} */
    public function handle(string $backupPath): array
    {
        $manifest = $this->verifier->handle($backupPath);
        if ($manifest['database_driver'] !== 'mysql') {
            throw new RuntimeException('A MySQL backup is required for this restore exercise.');
        }

        $settings = config('cofund.backups.restore_mysql');
        if (! is_array($settings) || ! ($settings['enabled'] ?? false)) {
            throw new RuntimeException('A dedicated MySQL restore connection is required.');
        }
        $host = (string) ($settings['host'] ?? '');
        $socket = (string) ($settings['unix_socket'] ?? '');
        $port = (string) ($settings['port'] ?? '3306');
        $username = (string) ($settings['username'] ?? '');
        $password = (string) ($settings['password'] ?? '');
        foreach ([$host, $socket, $port, $username, $password] as $value) {
            if (str_contains($value, "\n") || str_contains($value, "\r")) {
                throw new RuntimeException('MySQL restore connection settings are invalid.');
            }
        }
        if (($host === '' && $socket === '') || $username === '' || ! ctype_digit($port)) {
            throw new RuntimeException('MySQL restore connection settings are incomplete.');
        }

        $directory = sys_get_temp_dir().'/cofund-restore-'.bin2hex(random_bytes(12));
        $scratchDatabase = 'cofund_restore_'.bin2hex(random_bytes(10));
        $binary = (string) ($settings['binary'] ?? 'mysql');
        $created = false;

        try {
            $this->stager->handle($backupPath, $directory);
            $credentialsPath = $directory.'/mysql-client.cnf';
            $quote = static fn (string $value): string => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
            $credentials = "[client]\nuser=".$quote($username)."\npassword=".$quote($password)."\n";
            $credentials .= $socket !== '' ? 'socket='.$quote($socket)."\n" : 'host='.$quote($host)."\nport=".$port."\n";
            if (file_put_contents($credentialsPath, $credentials) === false || ! chmod($credentialsPath, 0600)) {
                throw new RuntimeException('Unable to prepare private MySQL restore credentials.');
            }

            $command = [$binary, '--defaults-extra-file='.$credentialsPath, '--batch', '--skip-column-names'];
            $create = Process::timeout(60)->run([
                ...$command,
                '--execute=CREATE DATABASE `'.$scratchDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            ]);
            if (! $create->successful()) {
                throw new RuntimeException('Unable to create a disposable MySQL restore database.');
            }
            $created = true;

            $input = fopen($directory.'/database.sql', 'rb');
            if ($input === false) {
                throw new RuntimeException('Unable to read the staged MySQL dump.');
            }
            try {
                $import = Process::timeout(1800)->input($input)->run([
                    ...$command,
                    '--database='.$scratchDatabase,
                ]);
            } finally {
                fclose($input);
            }
            if (! $import->successful()) {
                throw new RuntimeException('The disposable MySQL restore failed.');
            }

            $count = Process::timeout(60)->run([
                ...$command,
                '--database='.$scratchDatabase,
                "--execute=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'",
            ]);
            $required = Process::timeout(60)->run([
                ...$command,
                '--database='.$scratchDatabase,
                "--execute=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('migrations', 'users', 'organisations')",
            ]);
            $migrationRows = Process::timeout(60)->run([
                ...$command,
                '--database='.$scratchDatabase,
                '--execute=SELECT COUNT(*) FROM migrations',
            ]);
            if (! $count->successful() || ! $required->successful()
                || ! $migrationRows->successful()
                || ! ctype_digit(trim($count->output())) || (int) trim($count->output()) < 3
                || trim($required->output()) !== '3'
                || ! ctype_digit(trim($migrationRows->output())) || (int) trim($migrationRows->output()) < 1) {
                throw new RuntimeException('The disposable MySQL restore is missing required CoFund tables.');
            }

            return [
                'tables' => (int) trim($count->output()),
                'migration_rows' => (int) trim($migrationRows->output()),
                'database_driver' => 'mysql',
            ];
        } finally {
            try {
                if ($created) {
                    $drop = Process::timeout(60)->run([
                        $binary,
                        '--defaults-extra-file='.$directory.'/mysql-client.cnf',
                        '--execute=DROP DATABASE `'.$scratchDatabase.'`',
                    ]);
                    if (! $drop->successful()) {
                        throw new RuntimeException('The disposable MySQL restore database could not be removed.');
                    }
                }
            } finally {
                if (is_dir($directory)) {
                    $this->files->deleteDirectory($directory);
                }
            }
        }
    }
}
