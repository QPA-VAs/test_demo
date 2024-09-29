<?php

namespace App\Console\Commands;

use Dompdf\Dompdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Attribute\AsCommand;
use Carbon\Carbon;
use App\Mail\ClientTasksReport;
use App\Mail\WeeklyTaskReport;
use Illuminate\Support\Facades\Queue;
use Laravel\SerializableClosure\SerializableClosure;

#[AsCommand(name: 'generate:task-pdf')]
class GenerateTaskPDF extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:task-pdf';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate PDF containing all tasks';

    /**
     * Execute the console command.
     * @throws \Throwable
     */
    public function handle()
    {
        // Define the date range for last week
        $startDate = Carbon::now()->startOfWeek()->subWeek();
        $endDate = Carbon::now()->startOfWeek();
        $clients = \App\Models\Client::all();

        $adminEmail = \App\Models\User::where('last_name', 'Neal')->value('email');

        $pdfAttachments = [];

        foreach ($clients as $client) {
            $project = (object)[];

            $tasks = \App\Models\Task::whereHas('project.clients', function ($query) use ($client) {
                $query->where('clients.id', $client->id);
            })
                ->whereBetween('created_at', [$startDate, $endDate]) // Filter tasks by creation date
                ->with('creator') // Load the creator relationship
                ->get();

            if ($tasks->isEmpty()) {
                continue; // Skip clients with no tasks created last week
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
                $hours = floor($totalMinutes / 60);
                $minutes = $totalMinutes % 60;
                $formattedTime = '';
                if ($hours > 0) {
                    $formattedTime .= $hours . ' hrs';
                }
                if ($minutes > 0) {
                    $formattedTime .= ' ' . $minutes . ' mins';
                }

                // Add formatted time to task
                $task->formattedTimeSpent = $formattedTime;

                return $task;
            });

            // Convert total minutes back to hours and minutes format
            $totalHours = floor($totalTimeSpent / 60);
            $totalMinutes = $totalTimeSpent % 60;
            $formattedTotalTime = '';
            if ($totalHours > 0) {
                $formattedTotalTime .= $totalHours . ' hrs';
            }
            if ($totalMinutes > 0) {
                $formattedTotalTime .= ' ' . $totalMinutes . ' mins';
            }

            // Extract the names of the creators
            $creators = $tasks->map(function ($task) {
                return $task->creator->first_name . ' ' . $task->creator->last_name;
            })->unique()->toArray();

            // Pass data to the view
            $html = view('tasks.pdf', ['tasks' => $taskData, 'client' => $client, 'project' => $project, 'formattedTotalTime' => $formattedTotalTime, 'creators' => $creators])->render();

            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();

            $pdfContent = $dompdf->output();

            // Generate PDF file name
            $fileName = 'tasks_' . $client->first_name . '_' . now()->format('Y-m-d_H-i-s') . '.pdf';

            // Attach PDF content to array
            $pdfAttachments[] = [
                'name' => $fileName,
                'data' => base64_encode($pdfContent),
            ];
            $pdfContent = base64_encode($pdfContent);

            Queue::push(function ($job) use ($client, $pdfContent, $fileName, $formattedTotalTime) {
                $maxRetries = 3;
                try {
                    // Attempt to send the email
                    Mail::to($client->email)->send(new ClientTasksReport($client, base64_decode($pdfContent), $fileName, $formattedTotalTime));
                    // Mark the job as processed
                    $job->delete();
                } catch (\Exception $e) {
                    // Handle the exception
                    if ($job->attempts() >= $maxRetries) {
                        // Job has reached maximum retries, mark it as failed
                        $job->fail($e); // Mark the job as failed with the exception
                    } else {
                        // Requeue the job for retry with a delay of 30 seconds
                        $job->release(30);
                    }
                }
            });

            $this->info('Email queued for client ' . $client->name);
        }

        // Send all PDFs as one email to the admin
        foreach ($pdfAttachments as &$attachment) {
            if (is_callable($attachment['data'])) {
                $attachment['data'] = new SerializableClosure($attachment['data']);
            }
        }
        Queue::push(function () use ($adminEmail, $pdfAttachments) {
            foreach ($pdfAttachments as &$attachment) {
                $attachment['data'] = base64_decode($attachment['data']);
            }
            Mail::to($adminEmail)->send(new WeeklyTaskReport($pdfAttachments));
        });
    }
}
