<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Ultramod Platform')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('platform.index') }}">Ultramod</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#platformNav"
                aria-controls="platformNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="platformNav">
            <div class="navbar-nav">
                @foreach ($platformMenu as $item)
                    @if (\Illuminate\Support\Facades\Route::has($item['route']))
                        <a class="nav-link" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                    @endif
                @endforeach
            </div>

            <div class="navbar-nav ms-auto align-items-lg-center">
                @auth
                    @can('platform.manage')
                        <a class="nav-link" href="{{ route('platform.users.index') }}">Users</a>
                        <a class="nav-link" href="{{ route('platform.roles.index') }}">Roles</a>
                    @endcan
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Sign out</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <a class="nav-link" href="{{ route('login') }}">Sign in</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

<main class="container">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
