<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Tasks Summary</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .signature {
            font-weight: bold;
            font-size: larger;
        }
    </style>
</head>
<body>
<p>Dear {{ $client->first_name }},</p>
<p>We hope this email finds you well.</p>
<p>Please find attached your end of week summary for last week.</p>
<p>Hours used so far / Hours booked: {{ $formattedTime }}</p>
<p>Thank you for your continued partnership. Should you have any questions or concerns, please don't hesitate to contact us.</p>
<p>Best regards,</p>
<p><span class="signature">Bernadette Bawuah</span><br>
    <span class="signature" style="font-size: larger;">Business Administrative Manager</span><br>
    Tel: 0333 345 5486<br>
    Mob: 07780 874704</p>
</body>
</html>
