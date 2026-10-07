<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest as CorrectionRequest;
use App\Models\Employee;
use App\Services\AttendanceCorrectionReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionController extends Controller
{
    public function indexMine(): JsonResponse
    {
        $employee = auth('api')->user()->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'requests' => $employee->attendanceCorrectionRequests()
                ->latest()
                ->paginate(20),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user->employee;

        if ($user->role !== 'employee' || !$employee || $employee->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'An active employee profile is required.',
            ], 403);
        }

        $validated = $request->validate([
            'attendance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'request_type' => ['required', 'in:missing_time_in,missing_time_out,incorrect_time,incorrect_record'],
            'requested_time_in' => ['nullable', 'date_format:H:i'],
            'requested_time_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        if (
            ($validated['request_type'] === 'missing_time_in' && empty($validated['requested_time_in']))
            || ($validated['request_type'] === 'missing_time_out' && empty($validated['requested_time_out']))
            || (
                in_array($validated['request_type'], ['incorrect_time', 'incorrect_record'], true)
                && empty($validated['requested_time_in'])
                && empty($validated['requested_time_out'])
            )
        ) {
            throw ValidationException::withMessages([
                'requested_time_in' => 'Provide the requested time value(s) relevant to this correction.',
            ]);
        }

        $existing = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $validated['attendance_date'])
            ->first();

        $candidateIn = $validated['requested_time_in']
            ?? ($existing?->time_in?->format('H:i'));
        $candidateOut = $validated['requested_time_out']
            ?? ($existing?->time_out?->format('H:i'));

        if ($candidateIn && $candidateOut && $candidateOut <= $candidateIn) {
            throw ValidationException::withMessages([
                'requested_time_out' => 'Requested Time Out must be later than Time In.',
            ]);
        }

        $correction = DB::transaction(function () use ($employee, $validated): CorrectionRequest {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            $pendingExists = CorrectionRequest::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $validated['attendance_date'])
                ->where('status', 'pending')
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'attendance_date' => 'A pending correction request already exists for this date.',
                ]);
            }

            return CorrectionRequest::create([
                'employee_id' => $employee->id,
                'attendance_date' => $validated['attendance_date'],
                'request_type' => $validated['request_type'],
                'requested_time_in' => $validated['requested_time_in'] ?? null,
                'requested_time_out' => $validated['requested_time_out'] ?? null,
                'reason' => $validated['reason'],
                'status' => 'pending',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Attendance correction request submitted for review.',
            'request' => $correction,
        ], 201);
    }

    public function showMine(int $correctionRequest): JsonResponse
    {
        $employee = auth('api')->user()->employee;
        $request = CorrectionRequest::query()
            ->where('employee_id', $employee?->id)
            ->whereKey($correctionRequest)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'request' => $request,
        ]);
    }

    public function indexAdmin(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'requests' => CorrectionRequest::query()
                ->with(['employee.user', 'reviewer'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function showAdmin(int $correctionRequest): JsonResponse
    {
        $request = CorrectionRequest::query()
            ->with(['employee.user', 'reviewer'])
            ->findOrFail($correctionRequest);

        return response()->json([
            'success' => true,
            'request' => $request,
        ]);
    }

    public function approve(Request $request, int $correctionRequest): JsonResponse
    {
        return $this->review($request, $correctionRequest, true);
    }

    public function reject(Request $request, int $correctionRequest): JsonResponse
    {
        return $this->review($request, $correctionRequest, false);
    }

    private function review(Request $request, int $id, bool $approve): JsonResponse
    {
        $validated = $request->validate([
            'admin_review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $correction = app(AttendanceCorrectionReviewService::class)->review(
            $id,
            auth('api')->user(),
            $approve,
            $validated['admin_review_notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => $approve ? 'Correction approved and applied.' : 'Correction rejected.',
            'request' => $correction,
        ]);
    }
}
