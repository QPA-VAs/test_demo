<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';
    protected $description = 'Backup the database on Sundays';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Run the backup using Spatie's package
        $this->info("Starting the backup...");
        BackupJobFactory::createFromArray(config('backup'))->run();
        $this->info("Backup completed!");
    }
}

