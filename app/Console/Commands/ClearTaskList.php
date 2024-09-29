<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task; // Make sure to import your Task model

class ClearTaskList extends Command
{
    // The name and signature of the console command.
    protected $signature = 'task:clear';

    // The console command description.
    protected $description = 'Clear all tasks from the task list';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Disable foreign key checks to allow truncation
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;'); 

        // Truncate the tasks table to delete all records
        Task::truncate(); 
        
        // Re-enable foreign key checks
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Provide feedback in the console
        $this->info('All tasks have been cleared.');
    }
}
