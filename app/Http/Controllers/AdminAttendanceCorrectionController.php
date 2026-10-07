<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Services\AttendanceCorrectionReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAttendanceCorrectionController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        $query = AttendanceCorrectionRequest::query()
            ->with(['employee.user', 'reviewer'])
            ->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return view('admin.attendance-correction-requests.index', [
            'requests' => $query->paginate(20)->withQueryString(),
            'status' => $validated['status'] ?? '',
            'pendingCount' => AttendanceCorrectionRequest::where('status', 'pending')->count(),
        ]);
    }

    public function show(int $correctionRequest): View
    {
        $attendanceRequest = AttendanceCorrectionRequest::query()
            ->with(['employee.user', 'reviewer'])
            ->findOrFail($correctionRequest);

        $attendance = Attendance::query()
            ->where('employee_id', $attendanceRequest->employee_id)
            ->whereDate('attendance_date', $attendanceRequest->attendance_date)
            ->first();

        return view('admin.attendance-correction-requests.show', compact(
            'attendanceRequest',
            'attendance'
        ));
    }

    public function approve(
        Request $request,
        int $correctionRequest,
        AttendanceCorrectionReviewService $reviewService
    ): RedirectResponse {
        return $this->review($request, $correctionRequest, true, $reviewService);
    }

    public function reject(
        Request $request,
        int $correctionRequest,
        AttendanceCorrectionReviewService $reviewService
    ): RedirectResponse {
        return $this->review($request, $correctionRequest, false, $reviewService);
    }

    private function review(
        Request $request,
        int $correctionRequest,
        bool $approve,
        AttendanceCorrectionReviewService $reviewService
    ): RedirectResponse {
        $validated = $request->validate([
            'admin_review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $reviewService->review(
            $correctionRequest,
            $request->user(),
            $approve,
            $validated['admin_review_notes'] ?? null
        );

        return redirect()
            ->route('admin.attendance-correction-requests.show', $correctionRequest)
            ->with('success', $approve
                ? 'Correction approved and attendance updated.'
                : 'Correction request rejected.');
    }
}
