@extends('layouts.admin')

@section('title', 'Departments — ATTENDSYNC')

@section('content')
<div class="admin-page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h1 class="page-title">Departments</h1>
        <p class="page-subtitle">Organize faculty and staff into school grade levels and administrative offices.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger" style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
        {{ session('error') }}
    </div>
@endif

<div style="display:grid; grid-template-columns: 1fr 340px; gap:20px;">
    <div class="card" style="background:#fff; border-radius:10px; border:1px solid #e5e7eb; overflow:hidden;">
        <table class="table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb; color:#6b7280;">
                    <th style="padding:12px 16px;">Code</th>
                    <th style="padding:12px 16px;">Department Name</th>
                    <th style="padding:12px 16px;">Assigned Personnel</th>
                    <th style="padding:12px 16px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departments as $dept)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:12px 16px; font-weight:600; color:#111827;">{{ $dept->code }}</td>
                    <td style="padding:12px 16px;">
                        <strong>{{ $dept->name }}</strong>
                        @if($dept->description)
                            <div style="color:#6b7280; font-size:12px;">{{ $dept->description }}</div>
                        @endif
                    </td>
                    <td style="padding:12px 16px;">
                        <span style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:600;">
                            {{ $dept->employees_count }} employees
                        </span>
                    </td>
                    <td style="padding:12px 16px; text-align:right;">
                        <form method="POST" action="{{ route('admin.departments.destroy', $dept) }}" style="display:inline;" onsubmit="return confirm('Delete department {{ $dept->name }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#991b1b; border:none; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:12px;">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="padding:32px 16px; text-align:center; color:#9ca3af;">
                        No departments registered yet. Use the form to add school departments (e.g. Grade 1, Junior High, Admin).
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div style="padding:12px 16px;">
            {{ $departments->links() }}
        </div>
    </div>

    <div class="card" style="background:#fff; border-radius:10px; border:1px solid #e5e7eb; padding:20px; height:fit-content;">
        <h3 style="font-size:16px; font-weight:700; margin:0 0 16px; color:#111827;">Add New Department</h3>
        <form method="POST" action="{{ route('admin.departments.store') }}">
            @csrf
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#374151;">Department Code *</label>
                <input type="text" name="code" value="{{ old('code') }}" required placeholder="e.g. G6, JHS-ENG, ADMIN" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                @error('code') <span style="color:#dc2626; font-size:11px;">{{ $message }}</span> @enderror
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#374151;">Department Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Grade 6 Faculty" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                @error('name') <span style="color:#dc2626; font-size:11px;">{{ $message }}</span> @enderror
            </div>

            <div style="margin-bottom:18px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#374151;">Description</label>
                <textarea name="description" rows="3" placeholder="Optional notes or scope" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">{{ old('description') }}</textarea>
            </div>

            <button type="submit" style="width:100%; background:#2563eb; color:#fff; border:none; padding:10px 16px; border-radius:6px; font-weight:600; cursor:pointer; font-size:13px;">Create Department</button>
        </form>
    </div>
</div>
@endsection
