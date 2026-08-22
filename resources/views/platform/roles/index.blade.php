@extends('platform.layout')

@section('title', 'Roles')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Roles</h1>
        <a href="{{ route('platform.roles.create') }}" class="btn btn-primary">+ New role</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Super admin</th>
                        <th>Permissions</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td>{{ $role->name }}</td>
                        <td><code>{{ $role->slug }}</code></td>
                        <td>
                            @if ($role->is_super_admin)
                                <span class="badge text-bg-warning">super admin</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $role->is_super_admin ? 'all' : $role->permissions_count }}</td>
                        <td class="text-end">
                            <a href="{{ route('platform.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @if ($role->slug !== 'admin')
                                <form method="POST" action="{{ route('platform.roles.destroy', $role) }}" class="d-inline"
                                      onsubmit="return confirm('Delete role {{ $role->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
