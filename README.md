# مُداوَلة — نظام إدارة مكاتب المحاماة

نظام متكامل لإدارة مكاتب المحاماة مبني على Laravel 12 مع واجهة عربية كاملة (RTL).

## المميزات

- **لوحة تحكم** مع إحصائيات ورسوم بيانية
- **إدارة القضايا** مع حالات وأولويات و courts عمان
- **جدولة الجلسات** مع تنبيهات
- **إدارة المهام** مع تعيين للموظفين
- **رفع المستندات** بشكل آمن (خارج المجلد العام)
- **إدارة الموكّلين** (أفراد وشركات)
- **إدارة المستخدمين** مع أدوار (مدير/محامي/إداري/موكّل)
- **دراسة الجدوى** مع تقييم كفاءة الموظفين
- **سجل المراقبة** لكل العمليات
- **نظام الإشعارات** الداخلي
- **إعدادات المكتب**
- **ترجمة كاملة** (عربي/إنجليزي)

## المتطلبات

- PHP 8.2+
- MySQL/MariaDB أو SQLite
- Composer
- Node.js (اختياري للتطوير)

## التثبيت والتشغيل

### 1. استنساخ المشروع
```bash
cd law-office
```

### 2. تثبيت الاعتماديات
```bash
composer install
```

### 3. إعداد ملف البيئة
```bash
copy .env.example .env
php artisan key:generate
```

### 4. إعداد قاعدة البيانات

**للتطوير (SQLite - افتراضي):**
```bash
# إنشاء ملف قاعدة البيانات
type nul > database\database.sqlite
php artisan migrate --seed
```

**للإنتاج (MySQL/MariaDB):**
عدّل ملف `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=law_office
DB_USERNAME=root
DB_PASSWORD=your_password
```
ثم:
```bash
php artisan migrate --seed
```

### 5. إنشاء الروابط الرمزية
```bash
php artisan storage:link
```

### 6. تشغيل التطبيق
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### 7. الوصول من أجهزة أخرى في الشبكة
من أي جهاز كمبيوتر متصل بنفس الشبكة:
```
http://[عنوان-IP-الجهاز]:8000
```

## حساب المدير الافتراضي

| الحقل | القيمة |
|--------|--------|
| البريد | admin@riyami.om |
| كلمة المرور | password |

**مهم:** غيّر كلمة المرور فوراً بعد أول دخول.

## الأدوار والصلاحيات

| الدور | الصلاحيات |
|--------|-----------|
| **مدير (admin)** | صلاحيات كاملة + إدارة المستخدمين + دراسة الجدوى + سجل المراجعة + الإعدادات |
| **محامي (lawyer)** | القضايا + الجلسات + المهام + المستندات + الموكّلين |
| **إداري (staff)** | القضايا + الجلسات + المهام + المستندات + الموكّلين |
| **موكّل (client)** | الاطلاع على القضايا المصرّح بها |

## هيكل المشروع

```
law-office/
├── app/
│   ├── Http/Controllers/    # الم控制器ات
│   │   ├── Auth/            # المصادقة
│   │   ├── CaseController   # القضايا
│   │   ├── CourtSessionController  # الجلسات
│   │   ├── TaskController   # المهام
│   │   ├── DocumentController  # المستندات
│   │   ├── ClientController # الموكّلين
│   │   ├── UserController   # المستخدمين
│   │   ├── FeasibilityController  # دراسة الجدوى
│   │   ├── NotificationController  # الإشعارات
│   │   ├── AuditLogController  # سجل المراقبة
│   │   └── SettingController  # الإعدادات
│   ├── Http/Middleware/      # الوسيطات
│   │   ├── RoleMiddleware   # التحقق من الدور
│   │   └── CheckActiveUser  # التحقق من نشاط المستخدم
│   ├── Models/              # النماذج
│   └── Providers/           # مزودي الخدمة
├── database/
│   ├── migrations/          # هيكل قاعدة البيانات
│   └── seeders/             # البيانات الأولية
├── lang/
│   ├── ar/app.php           # الترجمة العربية
│   └── en/app.php           # الترجمة الإنجليزية
├── resources/views/         # القوالب
│   ├── layouts/app.blade.php  # التخطيط الرئيسي
│   ├── auth/                # صفحات الدخول
│   ├── dashboard.blade.php  # لوحة التحكم
│   ├── cases/               # القضايا
│   ├── sessions/            # الجلسات
│   ├── tasks/               # المهام
│   ├── documents/           # المستندات
│   ├── clients/             # الموكّلين
│   ├── users/               # المستخدمين
│   ├── feasibility/         # دراسة الجدوى
│   ├── audit-log/           # سجل المراقبة
│   ├── settings/            # الإعدادات
│   ├── notifications/       # الإشعارات
│   └── profile/             # الملف الشخصي
├── routes/web.php           # المسارات
└── storage/                 # التخزين
```

## النشر على سيرفر الإنتاج

### باستخدام XAMPP/WAMP:
1. انسخ مجلد المشروع إلى `htdocs/` أو `www/`
2. أنشئ قاعدة بيانات MySQL فارغة اسمها `law_office`
3. عدّل `.env` ببيانات MySQL
4. شغّل `php artisan migrate --seed`
5. شغّل `php artisan storage:link`
6. شغّل `php artisan serve`

### باستخدام Docker:
```bash
docker run -d -p 8000:8000 -v "$(pwd):/app" -w /app composer:latest install
docker run -d -p 3306:3306 -e MYSQL_ROOT_PASSWORD=password -e MYSQL_DATABASE=law_office mysql:8
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

### للوصول من أكثر من جهاز:
1. تأكد أن جميع الأجهزة متصلة بنفس الشبكة (Wi-Fi أو LAN)
2. شغّل الأمر: `php artisan serve --host=0.0.0.0 --port=8000`
3. اكتشف عنوان IP لجهاز السيرفر: `ipconfig` (Windows) أو `ifconfig` (Mac/Linux)
4. من أي جهاز آخر في الشبكة، افتح المتصفح واذهب إلى: `http://[عنوان-IP]:8000`

## الأمان

- تشفير كلمات المرور بـ bcrypt
- حماية CSRF/XSS/SQL Injection
- تقييد معدل الطلبات
- قفل الحساب بعد 5 محاولات دخول فاشلة (15 دقيقة)
- جلسات مشفّرة في قاعدة البيانات
- رؤوس أمان
- رفع المستندات خارج المجلد العام
- التحقق من صلاحية الوصول للمستندات

## البيانات الأولية

- **مكتب حمد الريami للمحاماة** - هاتف: 99331700 - info@riyami.om
- ** المحاكم العمانية** - 13 محكمة استئناف + المحكمة العليا + المحاكم الابتدائية

## حقوق النشر

مُداوَلة © 2026 — https://mudawala.riyami.om/
