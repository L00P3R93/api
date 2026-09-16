<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->logsPath = storage_path('logs');

    File::ensureDirectoryExists($this->logsPath);

    File::put("{$this->logsPath}/test-clear-laravel.log", 'laravel log contents');
    File::put("{$this->logsPath}/test-clear-laravel-2024-01-01.log", 'rotated laravel log contents');
    File::put("{$this->logsPath}/test-clear-mpesa.log", 'mpesa log contents');
});

afterEach(function () {
    foreach ([
        'test-clear-laravel.log',
        'test-clear-laravel-2024-01-01.log',
        'test-clear-mpesa.log',
    ] as $file) {
        File::delete("{$this->logsPath}/{$file}");
    }
});

it('fails when neither a log name nor --all is provided', function () {
    $this->artisan('logs:clear')
        ->assertFailed();

    expect(File::get("{$this->logsPath}/test-clear-laravel.log"))->toBe('laravel log contents');
});

it('does not modify files on a dry run', function () {
    $this->artisan('logs:clear', ['--all' => true, '--dry-run' => true])
        ->assertSuccessful();

    expect(File::get("{$this->logsPath}/test-clear-laravel.log"))->toBe('laravel log contents');
    expect(File::get("{$this->logsPath}/test-clear-mpesa.log"))->toBe('mpesa log contents');
});

it('clears only the requested log and its rotated files', function () {
    $this->artisan('logs:clear', ['logs' => ['test-clear-laravel'], '--force' => true])
        ->assertSuccessful();

    expect(File::get("{$this->logsPath}/test-clear-laravel.log"))->toBe('');
    expect(File::get("{$this->logsPath}/test-clear-laravel-2024-01-01.log"))->toBe('');
    expect(File::get("{$this->logsPath}/test-clear-mpesa.log"))->toBe('mpesa log contents');
});

it('clears every log file with --all', function () {
    $this->artisan('logs:clear', ['--all' => true, '--force' => true])
        ->assertSuccessful();

    expect(File::get("{$this->logsPath}/test-clear-laravel.log"))->toBe('');
    expect(File::get("{$this->logsPath}/test-clear-laravel-2024-01-01.log"))->toBe('');
    expect(File::get("{$this->logsPath}/test-clear-mpesa.log"))->toBe('');
});

it('fails when no log files match the given name', function () {
    $this->artisan('logs:clear', ['logs' => ['does-not-exist']])
        ->assertFailed();

    expect(File::get("{$this->logsPath}/test-clear-laravel.log"))->toBe('laravel log contents');
});
