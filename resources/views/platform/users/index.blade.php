@extends('platform.layout')

@section('title', 'Users')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Users</h1>
        <a href="{{ route('platform.users.create') }}" class="btn btn-primary">+ New user</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role?->name ?? '-' }}</td>
                        <td>
                            @if ($user->is_active)
                                <span class="badge text-bg-success">active</span>
                            @else
                                <span class="badge text-bg-secondary">inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('platform.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @if ($user->id !== auth()->id())
                                @if ($user->is_active)
                                    <form method="POST" action="{{ route('platform.users.deactivate', $user) }}" class="d-inline">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Deactivate</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('platform.users.activate', $user) }}" class="d-inline">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $users->links() }}</div>
@endsection
