@extends('platform.layout')

@section('title', $user->exists ? 'Edit user' : 'New user')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 card-title mb-3">{{ $user->exists ? 'Edit user' : 'New user' }}</h1>

                    <form method="POST"
                          @if ($user->exists) action="{{ route('platform.users.update', $user) }}" @else action="{{ route('platform.users.store') }}" @endif>
                        @csrf
                        @if ($user->exists)
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ $user->exists ? 'New password (leave blank to keep)' : 'Password' }}</label>
                            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                                   autocomplete="new-password" {{ $user->exists ? '' : 'required' }}>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="platform_role_id" class="form-label">Role</label>
                            <select id="platform_role_id" name="platform_role_id" class="form-select @error('platform_role_id') is-invalid @enderror">
                                <option value="">— no role —</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected(old('platform_role_id', $user->platform_role_id) == $role->id)>
                                        {{ $role->name }}{{ $role->is_super_admin ? ' (super admin)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('platform_role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('platform.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
