@extends('platform.layout')

@section('title', 'customers')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">customers</h1>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">+ New</a>
    </div>

    @if ($records->isEmpty())
        <div class="alert alert-light text-center">No records yet. <a href="{{ route('customers.create') }}">Create the first one</a>.</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Notes</th>
                        <th>Vip</th>
                        <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($records as $record)
                        <tr>
                        <td>{{ $record->full_name }}</td>
                        <td>{{ $record->email }}</td>
                        <td>{{ $record->notes }}</td>
                        <td>{{ $record->vip }}</td>
                        <td class="text-end">
                                <a href="{{ route('customers.edit', $record) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('customers.destroy', $record) }}" class="d-inline"
                                      onsubmit="return confirm('Delete this record?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $records->links() }}</div>
    @endif
@endsection
