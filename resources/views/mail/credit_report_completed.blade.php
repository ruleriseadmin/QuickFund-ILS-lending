<!DOCTYPE html>
<html>
<head>
    <title>Credit Report Batch Completed</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .summary { margin-top: 20px; border-collapse: collapse; width: 100%; }
        .summary th, .summary td { border: 1px solid #ddd; padding: 8px; }
        .summary th { background-color: #f2f2f2; text-align: left; width: 200px; }
    </style>
</head>
<body>
    <h2>Credit Bureau Report Completed Successfully</h2>

    <p>Hello,</p>
    <p>The credit report batch has finished processing successfully. Here are the details:</p>

    <table class="summary">
        <tr>
            <th>Bureau</th>
            <td>{{ $summary['bureau'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Batch ID</th>
            <td>{{ $summary['batch_id'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Total Customers</th>
            <td>{{ $summary['total_customers'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Total Jobs</th>
            <td>{{ $summary['total_jobs'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Processed Jobs</th>
            <td>{{ $summary['processed_jobs'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Pending Jobs</th>
            <td>{{ $summary['pending_jobs'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Failed Jobs</th>
            <td>{{ $summary['failed_jobs'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Completed At</th>
            <td>{{ $summary['created_at'] ?? now()->toDateTimeString() }}</td>
        </tr>
    </table>

    <p>Everything ran successfully and the credit reports are up to date.</p>

    <p>Regards,<br>Your System</p>
</body>
</html>