<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(Request $request): View
    {
        $selectedDepartment = $request->query('department_id');

        $query = Position::with('department')
            ->withCount('employees')
            ->orderBy('name');

        if ($selectedDepartment) {
            $query->where('department_id', $selectedDepartment);
        }

        $positions = $query->paginate(15)->withQueryString();
        $departments = Department::orderBy('name')->get();

        return view('admin.positions.index', compact('positions', 'departments', 'selectedDepartment'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'name')->where(fn ($q) => $q->where('department_id', $request->department_id)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Position::create($validated);

        return redirect()->route('admin.positions.index')
            ->with('success', "Position '{$validated['name']}' created successfully.");
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'name')
                    ->where(fn ($q) => $q->where('department_id', $request->department_id))
                    ->ignore($position->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $position->update($validated);

        return redirect()->route('admin.positions.index')
            ->with('success', "Position '{$position->name}' updated successfully.");
    }

    public function destroy(Position $position): RedirectResponse
    {
        if ($position->employees()->count() > 0) {
            return redirect()->route('admin.positions.index')
                ->with('error', "Cannot delete position '{$position->name}' because employees are currently assigned to it. Please reassign those employees first.");
        }

        $name = $position->name;
        $position->delete();

        return redirect()->route('admin.positions.index')
            ->with('success', "Position '{$name}' deleted successfully.");
    }

    public function byDepartment(int $departmentId): JsonResponse
    {
        $positions = Position::where('department_id', $departmentId)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json([
            'success' => true,
            'positions' => $positions,
        ]);
    }
}
