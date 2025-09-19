<!DOCTYPE html>
<html>
<head>
    <title>Credit Report Batch Completed</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .summary { margin-top: 20px; border-collapse: collapse; width: 100%; }
        .summary th, .summary td { border: 1px solid #ddd; padding: 8px; }
        .summary th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Credit Bureau Report Completed Successfully</h2>

    <p>Hello,</p>
    <p>The credit report batch has finished processing successfully. Here are the details:</p>

    <table class="summary">
        <tr>
            <th>Batch ID</th>
            <td>{{ $summary['batch_id'] }}</td>
        </tr>
        {{-- <tr>
            <th>Name</th>
            <td>{{ $summary['name'] }}</td>
        </tr> --}}
        <tr>
            <th>Total Jobs</th>
            <td>{{ $summary['total_jobs'] }}</td>
        </tr>
        <tr>
            <th>Processed Jobs</th>
            <td>{{ $summary['processed_jobs'] }}</td>
        </tr>
        <tr>
            <th>Pending Jobs</th>
            <td>{{ $summary['pending_jobs'] }}</td>
        </tr>
        <tr>
            <th>Failed Jobs</th>
            <td>{{ $summary['failed_jobs'] }}</td>
        </tr>
        <tr>
            <th>Completed At</th>
            <td>{{ $summary['created_at'] }}</td>
        </tr>
    </table>

    <p>Everything ran successfully and the credit reports are up to date.</p>

    <p>Regards,<br>Your System</p>
</body>
</html>
