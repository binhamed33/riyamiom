<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('model')) {
            $model = str_replace(['%', '_'], ['\\%', '\\_'], $request->model);
            $query->where('model_type', 'like', '%' . $model . '%');
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        // §4: ترتيب — الأحدث/الأقدم والإجراء والمستخدم
        $sortMap = ['created' => 'created_at', 'action' => 'action', 'user' => 'user_id'];
        $sort = (string) $request->get('sort', 'created');
        $sort = array_key_exists($sort, $sortMap) ? $sort : 'created';
        $dir = strtolower($request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $logs = $query->orderBy($sortMap[$sort], $dir)->orderBy('id', 'desc')->paginate(25)->withQueryString();
        $users = User::orderBy('name')->get();

        return view('audit-log.index', compact('logs', 'users'));
    }
}
