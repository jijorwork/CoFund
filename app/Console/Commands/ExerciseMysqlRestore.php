<?php

namespace App\Console\Commands;

use App\Domain\Backups\Services\ExerciseMysqlRestore as RestoreExercise;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cofund:backup:exercise-mysql-restore {path}')]
#[Description('Verify a backup by restoring it into a disposable MySQL database')]
class ExerciseMysqlRestore extends Command
{
    public function handle(RestoreExercise $exercise): int
    {
        $result = $exercise->handle((string) $this->argument('path'));
        $this->info(sprintf('MySQL restore exercise passed: %d tables and %d migration rows.', $result['tables'], $result['migration_rows']));

        return self::SUCCESS;
    }
}
