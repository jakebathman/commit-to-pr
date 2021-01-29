<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SetupCommand extends Command
{
    protected $signature = 'setup {prBranch?}';

    protected $description = 'Add branches and remotes to prepare the branch.';

    public function handle()
    {
        if (! $prBranch = $this->argument('prBranch')) {
            $prBranch = $this->ask('Enter the PR branch from GitHub (e.g. username:branch)');
        }
        if (! preg_match('/^(.+?)\:(.+)$/i', $prBranch, $parts)) {
            $this->error("Error! This doesn't look right. Should be username:branch format");
            return 1;
        }

        $remote = $parts[1];
        $branch = $parts[2];
        $repo = $this->getRepo();
        $url = "git@github.com:{$remote}/{$repo}.git";

        try {
            $this->info($this->callCommand("git remote add {$remote} {$url}"));
        } catch (\Throwable $e) {
            $this->error('FAILED adding remote');
            $this->newLine();
            $this->warn($e->getMessage());
            return 1;
        }

        $this->line('Fetching ...');
        $this->info($this->callCommand("git fetch {$remote}"));

        $this->line('Checking out branch ...');
        $this->info($this->callCommand("git checkout -b {$remote}-{$branch} {$remote}/{$branch}"));

        $this->newLine(2);
        $this->question('Success!');
        $this->info("After committing, push to {$remote} HEAD:{$branch}:");
        $this->newLine();
        $this->info("    git push {$remote} HEAD:{$branch}");
    }

    public function getRepo()
    {
        return $this->callCommand('basename -s .git `git config --get remote.origin.url`');
    }

    public function callCommand($command)
    {
        $process = Process::fromShellCommandline($command);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return trim($process->getOutput());
    }
}
