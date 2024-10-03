<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('auth:clear-resets')->everyFifteenMinutes();
       $schedule->command('generate:task-pdf')->everyMinute();
        // $schedule->command('generate:backup-pdf')->everyMinute();
        $schedule->command('queue:work  --stop-when-empty')->everyMinute();
         // Schedule the task clearing command for every Monday at 8:30 AM
//    $schedule->command('task:clear')->weeklyOn(1, '08:30');

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
