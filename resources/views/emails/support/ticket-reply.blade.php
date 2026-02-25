<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket Reply</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 8px;">Support Team Reply</h2>
    <p style="margin-top: 0;">Ticket <strong>{{ $ticket->ticket_number }}</strong></p>

    <p><strong>Subject:</strong> {{ $ticket->subject }}</p>
    <p><strong>Status:</strong> {{ $ticket->status }}</p>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 16px 0;">

    <p style="margin-bottom: 6px;"><strong>Admin Reply:</strong></p>
    <p style="white-space: pre-wrap;">{{ $replyMessage }}</p>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 16px 0;">

    <p style="font-size: 13px; color: #6b7280;">
        This is an automated support update from Tandil.
    </p>
</body>
</html>

