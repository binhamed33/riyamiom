@extends('pdf.layout')

@section('content')
    {{-- ========== HEADER ========== --}}
    <div class="pdf-header">
        <table class="pdf-header-table">
            <tr>
                <td style="text-align: right;">
                    @php $officeLogoData = \App\Support\OfficeBrand::logoDataUri(); @endphp
                    @if($officeLogoData)
                        <img src="{{ $officeLogoData }}" alt="" style="max-height:46px;max-width:150px;margin-bottom:4px;">
                    @endif
                    <div class="office-name">{{ \App\Models\Setting::get('office_name', 'مكتب حمد الريامي للمحاماة') }}</div>
                    <div class="office-sub">هاتف: {{ \App\Models\Setting::get('phone', '99331700') }} | بريد: {{ \App\Models\Setting::get('email', 'info@riyami.om') }}</div>
                </td>
                <td style="text-align: left;">
                    <div class="print-date">تاريخ الطباعة: {{ date('Y/m/d') }}</div>
                </td>
            </tr>
        </table>
        <div class="doc-title-row">
            <div class="doc-title">ملف القضية</div>
        </div>
    </div>

    {{-- ========== CASE INFO ========== --}}
    <div class="section">
        <div class="section-header">بيانات القضية</div>
        <div class="section-body">
            <table class="info-table">
                <tr>
                    <td class="info-label">رقم القضية</td>
                    <td class="info-value">{{ $case->case_number }}</td>
                    <td class="info-label">عنوان القضية</td>
                    <td class="info-value">{{ $case->title }}</td>
                </tr>
                <tr>
                    <td class="info-label">نوع القضية</td>
                    <td class="info-value">{{ $case->type ?? '—' }}</td>
                    <td class="info-label">المحكمة</td>
                    <td class="info-value">{{ $case->court ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="info-label">الحالة</td>
                    <td class="info-value">
                        @if($case->status === 'active') <span class="badge badge-active">نشطة</span>
                        @elseif($case->status === 'pending') <span class="badge badge-pending">قيد الانتظار</span>
                        @elseif($case->status === 'overdue') <span class="badge badge-overdue">متأخرة</span>
                        @elseif($case->status === 'closed') <span class="badge badge-closed">مغلقة</span>
                        @elseif($case->status === 'won') <span class="badge badge-won">مكتسبة</span>
                        @elseif($case->status === 'lost') <span class="badge badge-lost">خاسرة</span>
                        @elseif($case->status === 'adjudicated') <span class="badge badge-adjudicated">محكومة</span>
                        @elseif($case->status === 'fees_pending') <span class="badge badge-fees-pending">في انتظار دفع الأتعاب</span>
                        @else {{ $case->status }}
                        @endif
                    </td>
                    <td class="info-label">الأولوية</td>
                    <td class="info-value">
                        @if($case->priority === 'low') <span class="badge badge-low">منخفضة</span>
                        @elseif($case->priority === 'medium') <span class="badge badge-medium">متوسطة</span>
                        @elseif($case->priority === 'high') <span class="badge badge-high">عالية</span>
                        @elseif($case->priority === 'urgent') <span class="badge badge-urgent">عاجلة</span>
                        @else {{ $case->priority ?? '—' }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="info-label">الطرف المقابل</td>
                    <td class="info-value">{{ $case->opponent ?? '—' }}</td>
                    <td class="info-label">تاريخ الفتح</td>
                    <td class="info-value">{{ $case->opened_at?->format('Y/m/d') ?? $case->created_at?->format('Y/m/d') ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="info-label">التاريخ التالي</td>
                    <td class="info-value">{{ $case->next_date?->format('Y/m/d') ?? '—' }}</td>
                    <td class="info-label">تاريخ الإنشاء</td>
                    <td class="info-value">{{ $case->created_at?->format('Y/m/d H:i') ?? '—' }}</td>
                </tr>
            </table>
            @if($case->description)
                <div class="note-text">
                    <strong style="color: #121826;">وصف القضية:</strong><br>
                    {{ $case->description }}
                </div>
            @endif
        </div>
    </div>

    {{-- ========== CLIENT INFO ========== --}}
    <div class="section">
        <div class="section-header">بيانات العميل</div>
        <div class="section-body">
            <table class="info-table">
                <tr>
                    <td class="info-label">اسم العميل</td>
                    <td class="info-value">{{ $case->client->name ?? '—' }}</td>
                    <td class="info-label">رقم الهاتف</td>
                    <td class="info-value">{{ $case->client->phone ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="info-label">البريد الإلكتروني</td>
                    <td class="info-value">{{ $case->client->email ?? '—' }}</td>
                    <td class="info-label">رقم الهوية الوطنية</td>
                    <td class="info-value">{{ $case->client->national_id ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ========== LAWYER INFO ========== --}}
    <div class="section">
        <div class="section-header">بيانات المحامي المسؤول</div>
        <div class="section-body">
            <table class="info-table">
                <tr>
                    <td class="info-label">اسم المحامي</td>
                    <td class="info-value">{{ $case->lawyer->name ?? '—' }}</td>
                    <td class="info-label">البريد الإلكتروني</td>
                    <td class="info-value">{{ $case->lawyer->email ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ========== COURT SESSIONS ========== --}}
    <div class="section">
        <div class="section-header">الجلسات القضائية</div>
        <div class="section-body">
            @if($case->sessions && $case->sessions->count() > 0)
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 20%;">التاريخ والوقت</th>
                            <th style="width: 25%;">المكان</th>
                            <th style="width: 15%;">الحالة</th>
                            <th style="width: 35%;">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($case->sessions as $i => $session)
                            <tr>
                                <td style="text-align: center;">{{ $i + 1 }}</td>
                                <td>{{ $session->date?->format('Y/m/d H:i') ?? $session->scheduled_at?->format('Y/m/d H:i') ?? '—' }}</td>
                                <td>{{ $session->location ?? $session->courtroom ?? '—' }}</td>
                                <td>
                                    @if(($session->status ?? '') === 'completed') <span class="badge badge-completed">مكتملة</span>
                                    @elseif(($session->status ?? '') === 'cancelled') <span class="badge badge-cancelled">ملغاة</span>
                                    @elseif(($session->status ?? '') === 'postponed') <span class="badge badge-postponed">مؤجلة</span>
                                    @else <span class="badge badge-upcoming">مجدولة</span>
                                    @endif
                                </td>
                                <td>{{ $session->notes ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-text">لا توجد جلسات مسجلة لهذه القضية</div>
            @endif
        </div>
    </div>

    {{-- ========== TASKS ========== --}}
    <div class="section">
        <div class="section-header">المهام والتكليفات</div>
        <div class="section-body">
            @if($case->tasks && $case->tasks->count() > 0)
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 22%;">العنوان</th>
                            <th style="width: 18%;">المسؤول</th>
                            <th style="width: 13%;">الحالة</th>
                            <th style="width: 13%;">الأولوية</th>
                            <th style="width: 14%;">الموعد النهائي</th>
                            <th style="width: 15%;">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($case->tasks as $i => $task)
                            <tr>
                                <td style="text-align: center;">{{ $i + 1 }}</td>
                                <td>{{ $task->title }}</td>
                                <td>{{ $task->assignee->name ?? '—' }}</td>
                                <td>
                                    @if($task->status === 'completed' || ($task->is_completed ?? false)) <span class="badge badge-completed">مكتملة</span>
                                    @elseif($task->status === 'in_progress') <span class="badge badge-upcoming">قيد التنفيذ</span>
                                    @else <span class="badge badge-pending">قيد الانتظار</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($task->priority ?? '') === 'urgent') <span class="badge badge-urgent">عاجلة</span>
                                    @elseif(($task->priority ?? '') === 'high') <span class="badge badge-high">عالية</span>
                                    @elseif(($task->priority ?? '') === 'medium') <span class="badge badge-medium">متوسطة</span>
                                    @else <span class="badge badge-low">منخفضة</span>
                                    @endif
                                </td>
                                <td>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('Y/m/d') : '—' }}</td>
                                <td>{{ $task->notes ?? $task->description ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-text">لا توجد مهام مسجلة لهذه القضية</div>
            @endif
        </div>
    </div>

    {{-- ========== DOCUMENTS ========== --}}
    <div class="section">
        <div class="section-header">المستندات المرفقة</div>
        <div class="section-body">
            @if($case->documents && $case->documents->count() > 0)
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 25%;">العنوان</th>
                            <th style="width: 15%;">النوع</th>
                            <th style="width: 15%;">الحجم</th>
                            <th style="width: 18%;">أضافه</th>
                            <th style="width: 12%;">الوصول</th>
                            <th style="width: 10%;">التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($case->documents as $i => $document)
                            <tr>
                                <td style="text-align: center;">{{ $i + 1 }}</td>
                                <td>{{ $document->title ?? $document->name ?? 'مستند' }}</td>
                                <td style="text-transform: uppercase; font-weight: bold;">{{ strtoupper($document->file_type ?? $document->mime_type ?? '—') }}</td>
                                <td>{{ $document->file_size ? round($document->file_size / 1024, 1) . ' KB' : '—' }}</td>
                                <td>{{ $document->uploader->name ?? '—' }}</td>
                                <td>
                                    @if(($document->access_level ?? '') === 'public' || ($document->access_level ?? '') === 'all') <span class="badge badge-access">عام</span>
                                    @elseif(($document->access_level ?? '') === 'private') <span class="badge badge-private">خاص</span>
                                    @elseif(($document->access_level ?? '') === 'team') <span class="badge badge-team">الفريق</span>
                                    @else {{ $document->access_level ?? '—' }}
                                    @endif
                                </td>
                                <td>{{ $document->created_at?->format('Y/m/d') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-text">لا توجد مستندات مرفقة لهذه القضية</div>
            @endif
        </div>
    </div>

    {{-- ========== FOOTER ========== --}}
    <div class="pdf-footer">
        <table class="pdf-footer-table">
            <tr>
                <td style="text-align: right; width: 33%;">تاريخ الإنشاء: {{ $case->created_at?->format('Y/m/d H:i') ?? '—' }}</td>
                <td style="text-align: center; width: 34%; color: #D4AF37; font-weight: bold;">{{ \App\Models\Setting::get('office_name', 'مكتب حمد الريامي للمحاماة') }}</td>
                <td style="text-align: left; width: 33%;">آخر تعديل: {{ $case->updated_at?->format('Y/m/d H:i') ?? '—' }}</td>
            </tr>
        </table>
    </div>
@endsection
