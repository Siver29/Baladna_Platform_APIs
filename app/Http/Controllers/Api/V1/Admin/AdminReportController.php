<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignEmployeeRequest;
use App\Http\Resources\ReportResource;
use App\Http\Responses\ApiResponse;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    use ApiResponse;

    /**
     * Assign an employee from the report's agency to the report.
     */
    public function assign(AssignEmployeeRequest $request, Report $report): JsonResponse
    {
        $employee = User::findOrFail($request->employee_id);

        if ($employee->agency_id !== $report->agency_id) {
            return $this->error(
                'The selected employee does not belong to this report\'s agency.',
                422
            );
        }

        $report = DB::transaction(function () use ($report, $employee) {
            $report->update(['assigned_employee_id' => $employee->id]);

            return $report;
        });

        $report->load(['category', 'area', 'agency', 'user', 'images', 'assignedEmployee']);

        return $this->success(new ReportResource($report), 'Employee assigned successfully.');
    }
}
