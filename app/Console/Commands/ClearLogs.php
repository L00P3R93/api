<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use SplFileInfo;

// Usage:
//- php artisan logs:clear --all — clears every *.log file in storage/logs
//- php artisan logs:clear laravel mpesa — clears only logs matching those names (matches laravel.log and rotated files like laravel-2024-01-01.log too, since the daily channel shares the same base path)
//- --dry-run — lists matched files and sizes, makes no changes
//- --force — skips the confirmation prompt (prompt only appears when APP_ENV=production, matching Laravel's own migrate:fresh convention via ConfirmableTrait)
//- Running with no arguments and no --all fails fast with an error rather than silently doing nothing or wiping everything
class ClearLogs extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:clear
        {logs?* : Names of specific logs to clear, e.g. "laravel" or "mpesa". Omit and pass --all to clear every log file}
        {--all : Clear every log file found in storage/logs}
        {--dry-run : List the log files that would be cleared without making any changes}
        {--force : Force the operation to run without a confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Empty application log files in storage/logs, either all of them or specific ones by name';

    public function handle(): int
    {
        $names = collect($this->argument('logs'));

        if (! $this->option('all') && $names->isEmpty()) {
            $this->components->error('Specify one or more log names, or pass --all to clear every log file.');

            return self::FAILURE;
        }

        $files = $this->resolveTargetFiles($names);

        if ($files->isEmpty()) {
            $this->components->warn('No matching log files were found.');

            return self::FAILURE;
        }

        $this->table(
            ['Log file', 'Size'],
            $files->map(fn (SplFileInfo $file) => [
                $this->relativePath($file),
                Number::fileSize($file->getSize()),
            ])
        );

        if ($this->option('dry-run')) {
            $this->components->info("Dry run: {$files->count()} log file(s) would be cleared. No changes made.");

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed("This will permanently erase the contents of {$files->count()} log file(s).")) {
            return self::FAILURE;
        }

        foreach ($files as $file) {
            File::put($file->getPathname(), '');
        }

        $this->components->info("Cleared {$files->count()} log file(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, string>  $names
     * @return Collection<int, SplFileInfo>
     */
    protected function resolveTargetFiles(Collection $names): Collection
    {
        $logFiles = collect(File::allFiles(storage_path('logs')))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'log');

        if ($this->option('all')) {
            return $logFiles->values();
        }

        $wanted = $names->map(fn (string $name) => Str::of($name)->before('.log')->lower()->value());

        return $logFiles
            ->filter(function (SplFileInfo $file) use ($wanted) {
                $basename = Str::lower(Str::before($file->getFilename(), '.log'));

                return $wanted->contains(fn (string $name) => $basename === $name || Str::startsWith($basename, "{$name}-"));
            })
            ->values();
    }

    protected function relativePath(SplFileInfo $file): string
    {
        return Str::after($file->getPathname(), storage_path('logs').DIRECTORY_SEPARATOR);
    }
}
