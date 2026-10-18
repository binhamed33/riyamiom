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

        // بمعنى الشارة لا بالعمود الخام (AuditLog::scopeActionIs): كان «إنشاء» يُرجع
        // صفوفاً شارتُها «تصدير» ويُسقط المواعيدَ المكتوبة «created»
        if ($request->filled('action')) {
            $query->actionIs((string) $request->action);
        }

        // ═══ «النموذج» قائمةٌ بأسمائه العربيّة لا خانةُ نصّ ═══
        //
        // العمودُ يعرض «قضية» و«مستند» و«موكّل»، والخانةُ كانت تبحث بـLIKE
        // في اسم الصنف: من كتب «مستند» لم يجد شيئاً، ووحدها «Document»
        // تعمل. فالقائمةُ تُبنى ممّا في السجلّ فعلاً، والقيمةُ اسمُ الصنف.
        $models = $this->modelOptions();

        if ($request->filled('model')) {
            $wanted = (string) $request->model;
            // اسمُ صنفٍ من القائمة، أو اسمُه العربيّ من رابطٍ قديمٍ أو مكتوبٍ باليد
            $classes = array_key_exists($wanted, $models)
                ? [$wanted]
                : array_keys(array_filter($models, fn ($label) => $label === $wanted));

            if ($classes) {
                $query->whereIn('model_type', $classes);
            } else {
                $model = str_replace(['%', '_'], ['\\%', '\\_'], $wanted);
                $query->where('model_type', 'like', '%' . $model . '%');
            }
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
        // أسماءُ موكّلي «فتح رابط البوابة» باستعلامٍ واحد — لا واحدٍ لكلّ صفّ
        AuditLog::primeActorNames($logs->getCollection());
        $users = User::orderBy('name')->get();

        return view('audit-log.index', compact('logs', 'users', 'models'));
    }

    /**
     * أصنافُ السجلّ الموجودة فعلاً ⇐ اسمُها العربيّ، مرتّبةً بالاسم.
     *
     * @return array<string,string>
     */
    private function modelOptions(): array
    {
        return AuditLog::query()
            ->whereNotNull('model_type')->where('model_type', '!=', '')
            ->distinct()->pluck('model_type')
            ->mapWithKeys(fn ($class) => [$class => (new AuditLog(['model_type' => $class]))->modelNoun()])
            ->sort()
            ->all();
    }
}
