@extends('platform.layout')

@section('title', $role->exists ? 'Edit role' : 'New role')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-3">{{ $role->exists ? 'Edit role' : 'New role' }}</h1>

                    <form method="POST"
                          @if ($role->exists) action="{{ route('platform.roles.update', $role) }}" @else action="{{ route('platform.roles.store') }}" @endif>
                        @csrf
                        @if ($role->exists)
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $role->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-check mb-4">
                            <input type="hidden" name="is_super_admin" value="0">
                            <input type="checkbox" id="is_super_admin" name="is_super_admin" value="1"
                                   class="form-check-input" @checked(old('is_super_admin', $role->is_super_admin))>
                            <label class="form-check-label" for="is_super_admin">
                                Super admin — bypasses all permission checks
                            </label>
                        </div>

                        <label class="form-label">Permissions</label>
                        @error('permissions')<div class="text-danger small">{{ $message }}</div>@enderror
                        @foreach ($groupedPermissions as $appId => $permissions)
                            <div class="border rounded p-3 mb-3">
                                <div class="fw-semibold text-uppercase small text-muted mb-2">{{ $appId }}</div>
                                <div class="row">
                                    @foreach ($permissions as $permission)
                                        <div class="col-md-6 col-lg-4">
                                            <div class="form-check">
                                                <input type="checkbox" name="permissions[]"
                                                       id="perm-{{ $permission->id }}"
                                                       value="{{ $permission->id }}"
                                                       class="form-check-input"
                                                       @checked(in_array($permission->id, $selectedIds))>
                                                <label class="form-check-label" for="perm-{{ $permission->id }}">
                                                    <code>{{ $permission->name }}</code>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('platform.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
