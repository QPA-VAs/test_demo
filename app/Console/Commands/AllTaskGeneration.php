<?php

namespace App\Console\Commands;

use Dompdf\Dompdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Attribute\AsCommand;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

#[AsCommand(name: 'generate:backup-pdf')]
class AllTaskGeneration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:backup-pdf';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate PDF containing all tasks';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Define the date range for last week
        $startDate = Carbon::now()->startOfWeek()->subWeek();
        $endDate = Carbon::now()->startOfWeek();

        // Fetch all tasks created last week
        $tasks = \App\Models\Task::whereBetween('created_at', ['2024-09-22', '2024-09-29'])
            ->with('creator','project') // Load the creator relationship
            ->get();

        Log::info($tasks);

        if ($tasks->isEmpty()) {
            Log::info('No tasks found for the specified date range.');
            return 0; // Indicate successful execution with no tasks
        }

        // Calculate total time spent and prepare task data
        $totalTimeSpent = 0;

        $taskData = $tasks->map(function ($task) use (&$totalTimeSpent) {
            // Split the time spent into hours and minutes
            $parts = explode(':', $task->time_spent);
            $hours = (int)$parts[0];
            $minutes = (int)$parts[1];

            // Convert hours to minutes and add them to the total minutes
            $totalMinutes = $hours * 60 + $minutes;
            $totalTimeSpent += $totalMinutes;

            // Convert total minutes back to hours and minutes format
            $formattedTime = floor($totalMinutes / 60) . ' hrs ' . ($totalMinutes % 60) . ' mins';
            $task->formattedTimeSpent = $formattedTime;

            return $task;
        });

        // Convert total minutes back to hours and minutes format
        $totalHours = floor($totalTimeSpent / 60);
        $totalMinutes = $totalTimeSpent % 60;
        $formattedTotalTime = "$totalHours hrs $totalMinutes mins";
        // Extract the names of the creators
        $projects = $tasks->map(function ($task) {
            return $task->project->title;
        })->unique()->toArray();
        // Pass data to the view
        $html = view('emails.tasks_backup', ['tasks' => $taskData,'project'=>$projects, 'formattedTotalTime' => $formattedTotalTime])->render();

        // Generate the PDF
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        // Output PDF content
        $pdfContent = $dompdf->output();

        // Generate PDF file name
        $fileName = 'weekly_tasks_' . now()->format('Y-m-d_H-i-s') . '.pdf';

        // Save PDF file to storage
        Storage::put('backups/' . $fileName, $pdfContent);

        // Send the PDF as an email to the admin
        $adminEmail = \App\Models\User::where('last_name', 'pepos')->value('email');

        Mail::send([], [], function ($message) use ($adminEmail, $fileName) {
            $message->to($adminEmail)
                ->subject('Weekly Task Report')
                ->attach(storage_path('app/backups/' . $fileName))
                ->html('Please find attached the backup weekly task report.'); // Set the body text as HTML
        });

    }

}
