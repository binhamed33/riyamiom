<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\HrLeaveType;
use Illuminate\Http\Request;

/**
 * أنواع الإجازات — يضبطها المدير.
 *
 * لا حذف هنا: نوعٌ يُحذف تصير إجازاتُه بلا حكمٍ في الراتب، وكشوفُ
 * الشهور الماضية تتغيّر بأثر رجعي. التعطيل يخرجه من قائمة الاختيار
 * ويُبقي ما بُني عليه سليماً.
 */
class LeaveTypeController extends Controller
{
    private function authorizeManager(): void
    {
        $u = auth()->user();

        abort_unless(
            $u && ($u->isDeveloper() || $u->role === 'admin' || $u->hasPermission('salaries.manage')),
            403
        );
    }

    public function store(Request $request)
    {
        $this->authorizeManager();

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:40|alpha_dash|unique:hr_leave_types,code',
            'affects_salary' => 'nullable|boolean',
        ]);

        $type = HrLeaveType::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'affects_salary' => $request->boolean('affects_salary'),
            'is_active' => true,
            'sort' => (int) HrLeaveType::max('sort') + 1,
        ]);

        $this->audit(AuditLog::ACTION_CREATE, $type, null, [
            'name' => $type->name, 'affects_salary' => $type->affects_salary,
        ]);

        return back()->with('success', 'أُضيف نوع الإجازة.');
    }

    public function update(Request $request, HrLeaveType $leaveType)
    {
        $this->authorizeManager();

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'affects_salary' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $before = [
            'name' => $leaveType->name,
            'affects_salary' => $leaveType->affects_salary,
            'is_active' => $leaveType->is_active,
        ];

        $leaveType->update([
            'name' => $data['name'],
            'affects_salary' => $request->boolean('affects_salary'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit(AuditLog::ACTION_UPDATE, $leaveType, $before, [
            'name' => $leaveType->name,
            'affects_salary' => $leaveType->affects_salary,
            'is_active' => $leaveType->is_active,
        ]);

        return back()->with('success', 'حُدّث نوع الإجازة.');
    }

    private function audit(string $action, $model, ?array $old, ?array $new): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable) {
        }
    }
}
