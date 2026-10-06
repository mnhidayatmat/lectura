<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class MigrateUploadsToContabo extends Command
{
    protected $signature = 'uploads:migrate-to-contabo
        {--dry-run : List what would be copied without uploading}
        {--delete-local : Remove each private local file after it is verified on Contabo}';

    protected $description = 'Copy every file on the local private and public disks into the Contabo object storage bucket';

    public function handle(): int
    {
        $remote = Storage::disk('contabo');
        $dryRun = (bool) $this->option('dry-run');

        $failed = $this->copyDisk(Storage::disk('local'), $remote, 'private', $dryRun, (bool) $this->option('delete-local'));

        // Public files are never deleted locally: editor images are embedded
        // in saved content by their old /storage/... URL.
        $failed += $this->copyDisk(Storage::disk('public'), $remote, 'public', $dryRun, false);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function copyDisk(Filesystem $local, Filesystem $remote, string $visibility, bool $dryRun, bool $deleteLocal): int
    {
        $copied = $skipped = $failed = 0;

        foreach ($local->allFiles() as $path) {
            if (str_starts_with(basename($path), '.')) {
                continue;
            }

            if ($remote->exists($path) && $remote->size($path) === $local->size($path)) {
                $skipped++;
                if ($deleteLocal && ! $dryRun) {
                    $local->delete($path);
                }

                continue;
            }

            if ($dryRun) {
                $this->line("would copy {$path} ({$visibility})");
                $copied++;

                continue;
            }

            $stream = $local->readStream($path);
            $ok = $stream && $remote->writeStream($path, $stream, ['visibility' => $visibility]);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $ok || $remote->size($path) !== $local->size($path)) {
                $this->error("failed {$path}");
                $failed++;

                continue;
            }

            $copied++;
            if ($deleteLocal) {
                $local->delete($path);
            }
        }

        $this->info(ucfirst($visibility).': '.($dryRun ? 'would copy' : 'copied')." {$copied}, already there {$skipped}, failed {$failed}.");

        return $failed;
    }
}
