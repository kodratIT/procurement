<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application health</title>
</head>
<body>
    <main>
        <h1>Application {{ $status }}</h1>
        <ul>
            @foreach ($checks as $name => $check)
                <li>{{ $name }}: {{ $check }}</li>
            @endforeach
        </ul>
    </main>
</body>
</html>
