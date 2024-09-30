<!-- <!DOCTYPE html>
<html>
<head>
    <title>Tasks Backup Report</title>
    <style>
        body { font-family: Arial, sans-serif; }
        h1, h2 { color: #333; }
        ul { list-style-type: none; padding: 0; }
        li { margin: 5px 0; }
    </style>
</head>
<body>
    <h1>Tasks Backup Report</h1>
    <h2>All Tasks</h2>
    <ul>
        @foreach ($tasks as $task)
    <li>
        <strong>Name:</strong> {{ $task->name }} <br>
                <strong>Time Spent:</strong> {{ $task->time_spent }} <br>
                <strong>Created At:</strong> {{ $task->created_at }} <br>
                <strong>Updated At:</strong> {{ $task->updated_at }}
    </li>
@endforeach
</ul>
</body>
</html> -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks PDF</title>
    <style>
        .styled-table {
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 0.9em;
            font-family: sans-serif;
            min-width: 400px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
        }
        .styled-table thead tr {
            background-color: #009879;
            color: #ffffff;
            text-align: left;
        }
        .styled-table th,
        .styled-table td {
            padding: 12px 15px;
        }
        .styled-table tbody tr {
            border-bottom: 1px solid #dddddd;
        }
        .styled-table tbody tr:nth-of-type(even) {
            background-color: #f3f3f3;
        }
        .styled-table tbody tr:last-of-type {
            border-bottom: 2px solid #009879;
        }
        .client-name {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
            display: inline-block;
        }
    </style>
</head>
<body>
<h1>Tasks Report</h1>
<div class="client-section">
    <table class="styled-table">
        <thead>
        <tr>
            <th>Date</th>
            <th>Project</th>
            <th>Description</th>
            <th>Time Spent</th>
            <th>VA (Employee Initials)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($tasks as $task)
        <tr>
            <td>{{ $task->start_date }}</td>
            <td>{{ $task->project->title }}</td>
            <td>{{ $task->title }}</td>
            <td>{{ $task->formattedTimeSpent }}</td>
            <td>{{ strtoupper(substr($task->creator->first_name, 0, 1)) }}{{ strtoupper(substr($task->creator->last_name, 0, 1)) }}</td>
        </tr>
        @endforeach
        <tr>
            <td></td>
            <td></td>
            <td>Total Time Spent: {{ $formattedTotalTime }}</td>
            <td></td>
        </tr>
        </tbody>
    </table>
</div>
</body>
</html>

