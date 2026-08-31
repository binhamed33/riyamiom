<?php

return [
    'groups' => [
        'cases' => [
            'label' => 'app.cases',
            'permissions' => [
                'cases.view' => 'عرض القضايا',
                'cases.create' => 'إضافة قضية',
                'cases.edit' => 'تعديل القضية',
                'cases.delete' => 'حذف القضية',
            ],
        ],
        'clients' => [
            'label' => 'app.clients',
            'permissions' => [
                'clients.view' => 'عرض العملاء',
                'clients.create' => 'إضافة عميل',
                'clients.edit' => 'تعديل العميل',
                'clients.delete' => 'حذف العميل',
            ],
        ],
        'sessions' => [
            'label' => 'app.sessions',
            'permissions' => [
                'sessions.view' => 'عرض الجلسات',
                'sessions.create' => 'إضافة جلسة',
                'sessions.edit' => 'تعديل الجلسة',
                'sessions.delete' => 'حذف الجلسة',
            ],
        ],
        'tasks' => [
            'label' => 'app.tasks',
            'permissions' => [
                'tasks.view' => 'عرض المهام',
                'tasks.create' => 'إضافة مهمة',
                'tasks.edit' => 'تعديل المهمة',
                'tasks.delete' => 'حذف المهمة',
            ],
        ],
        'documents' => [
            'label' => 'app.documents',
            'permissions' => [
                'documents.view' => 'عرض المستندات',
                'documents.create' => 'رفع مستند',
                'documents.delete' => 'حذف المستند',
            ],
        ],
        'users' => [
            'label' => 'app.users',
            'permissions' => [
                'users.view' => 'عرض المستخدمين',
                'users.create' => 'إضافة مستخدم',
                'users.edit' => 'تعديل المستخدم',
                'users.delete' => 'حذف المستخدم',
            ],
        ],
        'reports' => [
            'label' => 'التقارير',
            'permissions' => [
                'reports.view' => 'عرض التقارير',
            ],
        ],
        'feasibility' => [
            'label' => 'دراسة الجدوى',
            'permissions' => [
                'feasibility.view' => 'عرض دراسة الجدوى',
            ],
        ],
        'settings' => [
            'label' => 'الإعدادات',
            'permissions' => [
                'settings.manage' => 'إدارة الإعدادات',
            ],
        ],
        'backup' => [
            'label' => 'النسخ الاحتياطي',
            'permissions' => [
                'backup.manage' => 'إدارة النسخ الاحتياطي',
            ],
        ],
        'salaries' => [
            'label' => 'الرواتب',
            'permissions' => [
                // صلاحية واحدة لا اثنتان: من يرى الرواتب يرى أرقاماً
                // تكفي لاستنتاج الباقي، فالفصل بين «عرض» و«تعديل»
                // هنا وهمٌ يطمئن ولا يحمي.
                'salaries.manage' => 'إدارة رواتب الموظفين',
            ],
        ],
        'attendance' => [
            'label' => 'الحضور والانصراف',
            'permissions' => [
                'attendance.manage' => 'إدارة حضور الفريق',
            ],
        ],
        'audit_log' => [
            'label' => 'سجل الحركات',
            'permissions' => [
                'audit_log.view' => 'عرض سجل الحركات',
            ],
        ],
        'automations' => [
            'label' => 'مركز الأتمتة',
            'permissions' => [
                'automations.manage' => 'إدارة قواعد الأتمتة',
            ],
        ],
        'templates' => [
            'label' => 'القوالب الذكية',
            'permissions' => [
                'templates.manage' => 'إدارة قوالب القضايا',
            ],
        ],
        /*
        | واتساب — ثلاثُ صلاحيات لا واحدة.
        |
        | القراءةُ غير الردّ: موظّفٌ يتابع الوارد قد لا يُؤتمن على أن
        | يخاطب موكّلاً باسم المكتب. والربطُ غيرُهما: ربطُ محادثةٍ
        | بقضيّةٍ وحفظُ مستندٍ في ملفّها تصرّفٌ في سجلّ القضية نفسه.
        */
        'whatsapp' => [
            'label' => 'واتساب',
            'permissions' => [
                'whatsapp.view' => 'عرض محادثات واتساب',
                'whatsapp.send' => 'الرد على محادثات واتساب',
                'whatsapp.manage' => 'ربط المحادثات بالموكّلين والقضايا',
            ],
        ],
    ],

    'role_defaults' => [
        'developer' => [],
        'admin' => [],
        'lawyer' => [],
        'staff' => [],
        'client' => [],
    ],
];
