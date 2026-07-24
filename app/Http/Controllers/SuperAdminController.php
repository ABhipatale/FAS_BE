<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\FaceDescriptor;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cross-company reporting for the superadmin system dashboard.
 *
 * Every other controller scopes its queries to the caller's company_id; this
 * one deliberately spans all of them, so each endpoint re-checks the role.
 */
class SuperAdminController extends Controller
{
    /** Roles that are staff accounts rather than attendance-tracked employees */
    private const STAFF_ROLES = ['admin', 'superadmin'];

    public function overview(Request $request)
    {
        $authUser = $request->user();

        if (!$authUser || $authUser->role !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - superadmin only',
            ], 403);
        }

        try {
            $days = (int) $request->query('days', 7);
            $days = max(1, min($days, 90)); // guard against a silly ?days=100000

            $today = Carbon::today();
            $hasAttendance = \Schema::hasTable('attendances');

            $companies = Company::withCount('users')->orderBy('name')->get();

            // --- One grouped query per metric, keyed by company_id, so the
            // per-company table below is built without an N+1 loop.
            $employeesByCompany = User::whereNotIn('role', self::STAFF_ROLES)
                ->select('company_id', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('company_id')
                ->pluck('aggregate', 'company_id');

            $facesByCompany = FaceDescriptor::select('company_id', DB::raw('COUNT(DISTINCT user_id) as aggregate'))
                ->groupBy('company_id')
                ->pluck('aggregate', 'company_id');

            $shiftsByCompany = Shift::select('company_id', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('company_id')
                ->pluck('aggregate', 'company_id');

            $presentTodayByCompany = collect();
            $lateTodayByCompany = collect();
            $recordsTodayByCompany = collect();
            $lastPunchByCompany = collect();

            if ($hasAttendance) {
                $presentTodayByCompany = Attendance::whereDate('date', $today)
                    ->whereIn('status', ['present', 'late'])
                    ->select('company_id', DB::raw('COUNT(*) as aggregate'))
                    ->groupBy('company_id')
                    ->pluck('aggregate', 'company_id');

                $lateTodayByCompany = Attendance::whereDate('date', $today)
                    ->where(function ($q) {
                        $q->where('status', 'late')->orWhere('late_mark', true);
                    })
                    ->select('company_id', DB::raw('COUNT(*) as aggregate'))
                    ->groupBy('company_id')
                    ->pluck('aggregate', 'company_id');

                $recordsTodayByCompany = Attendance::whereDate('date', $today)
                    ->select('company_id', DB::raw('COUNT(*) as aggregate'))
                    ->groupBy('company_id')
                    ->pluck('aggregate', 'company_id');

                $lastPunchByCompany = Attendance::select('company_id', DB::raw('MAX(punch_in_time) as aggregate'))
                    ->groupBy('company_id')
                    ->pluck('aggregate', 'company_id');
            }

            $rows = $companies->map(function ($company) use (
                $employeesByCompany, $facesByCompany, $shiftsByCompany,
                $presentTodayByCompany, $lateTodayByCompany, $recordsTodayByCompany,
                $lastPunchByCompany
            ) {
                $employees = (int) ($employeesByCompany[$company->id] ?? 0);
                $present = (int) ($presentTodayByCompany[$company->id] ?? 0);
                $faces = (int) ($facesByCompany[$company->id] ?? 0);

                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'email' => $company->email,
                    'phone' => $company->phone,
                    'logo' => $company->logo,
                    'status' => $company->status,
                    'created_at' => $company->created_at,
                    'users' => (int) $company->users_count,
                    'employees' => $employees,
                    'shifts' => (int) ($shiftsByCompany[$company->id] ?? 0),
                    'faces_registered' => $faces,
                    'face_coverage' => $employees > 0 ? round($faces / $employees * 100, 1) : 0.0,
                    'present_today' => $present,
                    'late_today' => (int) ($lateTodayByCompany[$company->id] ?? 0),
                    'records_today' => (int) ($recordsTodayByCompany[$company->id] ?? 0),
                    'attendance_rate' => $employees > 0 ? round($present / $employees * 100, 1) : 0.0,
                    'last_punch_at' => $lastPunchByCompany[$company->id] ?? null,
                ];
            })->values();

            // --- Daily trend across every company
            $trend = [];
            if ($hasAttendance) {
                $from = $today->copy()->subDays($days - 1);

                $daily = Attendance::whereBetween('date', [$from->toDateString(), $today->toDateString()])
                    ->select(
                        'date',
                        DB::raw('COUNT(*) as records'),
                        DB::raw("SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) as present"),
                        DB::raw("SUM(CASE WHEN status = 'late' OR late_mark = 1 THEN 1 ELSE 0 END) as late")
                    )
                    ->groupBy('date')
                    ->get()
                    ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

                for ($cursor = $from->copy(); $cursor <= $today; $cursor->addDay()) {
                    $key = $cursor->toDateString();
                    $row = $daily[$key] ?? null;

                    $trend[] = [
                        'date' => $key,
                        'label' => $cursor->format('d M'),
                        'day' => $cursor->format('D'),
                        'present' => (int) ($row->present ?? 0),
                        'late' => (int) ($row->late ?? 0),
                        'records' => (int) ($row->records ?? 0),
                    ];
                }
            }

            $totalEmployees = (int) $employeesByCompany->sum();
            $presentToday = (int) $presentTodayByCompany->sum();

            return response()->json([
                'success' => true,
                'data' => [
                    'generated_at' => now()->toIso8601String(),
                    'attendance_table_missing' => !$hasAttendance,
                    'totals' => [
                        'companies' => $companies->count(),
                        'companies_active' => $companies->where('status', 'active')->count(),
                        'companies_inactive' => $companies->where('status', '!=', 'active')->count(),
                        'users' => (int) User::count(),
                        'admins' => (int) User::whereIn('role', self::STAFF_ROLES)->count(),
                        'employees' => $totalEmployees,
                        'shifts' => (int) Shift::count(),
                        'faces_registered' => (int) FaceDescriptor::distinct('user_id')->count('user_id'),
                        'attendance_records' => $hasAttendance ? (int) Attendance::count() : 0,
                        'present_today' => $presentToday,
                        'late_today' => (int) $lateTodayByCompany->sum(),
                        'absent_today' => max(0, $totalEmployees - $presentToday),
                        'attendance_rate' => $totalEmployees > 0
                            ? round($presentToday / $totalEmployees * 100, 1)
                            : 0.0,
                        'companies_reporting' => $recordsTodayByCompany->filter(fn ($n) => $n > 0)->count(),
                        'new_companies_30d' => $companies
                            ->where('created_at', '>=', $today->copy()->subDays(30))
                            ->count(),
                    ],
                    'trend' => $trend,
                    'companies' => $rows,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to build system overview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
