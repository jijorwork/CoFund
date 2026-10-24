<?php

use App\Domain\Backups\Services\CreateEncryptedBackup;
use App\Domain\Backups\Services\ExerciseMysqlRestore;
use Illuminate\Support\Facades\Storage;

test('a real MySQL dump restores into an isolated database after its source is removed', function () {
    $connection = config('database.connections.mysql');
    $host = (string) ($connection['host'] ?? '127.0.0.1');
    $port = (string) ($connection['port'] ?? '3306');
    $socket = (string) ($connection['unix_socket'] ?? '');
    $username = (string) ($connection['username'] ?? '');
    $password = (string) ($connection['password'] ?? '');
    $originalDatabase = (string) ($connection['database'] ?? '');
    $dsn = $socket === ''
        ? "mysql:host={$host};port={$port};charset=utf8mb4"
        : "mysql:unix_socket={$socket};charset=utf8mb4";
    $server = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $sourceDatabase = 'cofund_source_'.bin2hex(random_bytes(10));
    $beforeQuery = $server->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'cofund_restore_%'");
    if (! $beforeQuery instanceof PDOStatement) {
        throw new RuntimeException('Unable to inspect MySQL restore databases.');
    }
    $scratchBefore = $beforeQuery->fetchAll(PDO::FETCH_COLUMN);
    $sourceExists = false;

    try {
        $server->exec('CREATE DATABASE `'.$sourceDatabase.'` CHARACTER SET utf8mb4');
        $sourceExists = true;
        $server->exec('CREATE TABLE `'.$sourceDatabase.'`.`migrations` (`id` INT NOT NULL)');
        $server->exec('CREATE TABLE `'.$sourceDatabase.'`.`users` (`id` INT NOT NULL)');
        $server->exec('CREATE TABLE `'.$sourceDatabase.'`.`organisations` (`id` INT NOT NULL)');
        $server->exec('INSERT INTO `'.$sourceDatabase.'`.`migrations` (`id`) VALUES (7)');

        Storage::fake('local');
        config()->set('cofund.backups.disk', 'local');
        config()->set('cofund.backups.password', str_repeat('integration-secret-', 3));
        config()->set('cofund.backups.mysqldump_binary', (string) getenv('COFUND_MYSQLDUMP_BINARY'));
        config()->set('database.connections.mysql.database', $sourceDatabase);

        $archivePath = app(CreateEncryptedBackup::class)->handle();
        config()->set('database.connections.mysql.database', $originalDatabase);
        $server->exec('DROP DATABASE `'.$sourceDatabase.'`');
        $sourceExists = false;

        config()->set('cofund.backups.restore_mysql', [
            'enabled' => true,
            'binary' => (string) getenv('COFUND_RESTORE_MYSQL_BINARY'),
            'host' => $host,
            'port' => $port,
            'unix_socket' => $socket,
            'username' => $username,
            'password' => $password,
        ]);
        $result = app(ExerciseMysqlRestore::class)->handle($archivePath);

        expect($result)->toBe([
            'tables' => 3,
            'migration_rows' => 1,
            'database_driver' => 'mysql',
        ]);
        $afterQuery = $server->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'cofund_restore_%'");
        if (! $afterQuery instanceof PDOStatement) {
            throw new RuntimeException('Unable to inspect MySQL restore databases.');
        }
        $scratchAfter = $afterQuery->fetchAll(PDO::FETCH_COLUMN);
        expect($scratchAfter)->toBe($scratchBefore);
    } finally {
        config()->set('database.connections.mysql.database', $originalDatabase);
        if ($sourceExists) {
            $server->exec('DROP DATABASE `'.$sourceDatabase.'`');
        }
    }
})->skip(
    fn (): bool => config('database.default') !== 'mysql'
        || ! str_starts_with((string) config('database.connections.mysql.database'), 'cofund_restore_suite_')
        || getenv('COFUND_RUN_MYSQL_RESTORE_TEST') !== '1',
    'Enable the isolated MySQL restore integration environment with a cofund_restore_suite_ database.',
);
