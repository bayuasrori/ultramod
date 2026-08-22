<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CookBook App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('platform.index') }}">Ultramod</a>
        <div class="navbar-nav">
            <a class="nav-link active" href="{{ route('cook-book.index') }}">CookBook</a>
        </div>
    </div>
</nav>
<main class="container">
    <div class="card shadow-sm" style="max-width: 32rem;">
        <div class="card-body">
            <h1 class="h3 card-title">Hello from CookBook App!</h1>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>App</span><code>{{ $app->app_id }}</code></li>
                <li class="list-group-item d-flex justify-content-between"><span>Version</span><code>{{ $app->version }}</code></li>
                <li class="list-group-item d-flex justify-content-between"><span>Status</span><span class="badge text-bg-success">{{ $app->status }}</span></li>
            </ul>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
