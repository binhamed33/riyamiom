{{--
    ألوان الرسوم البيانية.

    العطل الذي يعالجه هذا الملف: كانت الصفحات تمرّر 'var(--accent)' إلى
    Chart.js. والـ<canvas> ليس فيه تتالي CSS — لا يفهم متغيّرات CSS
    إطلاقاً. المتصفّح يرفض القيمة غير الصالحة ويرجع إلى افتراضي سياق
    الرسم: الأسود. فكل عمود وكل خطّ في النظام كان يُرسم أسود، في
    الوضعين الفاتح والداكن معاً.

    الحل: نقرأ قيمة الرمز الحقيقية وقت التشغيل عبر getComputedStyle،
    فتصير لوناً صالحاً للـcanvas — ويتبع سمة المستخدم أياً كانت من
    السمات الخمس.

    ولوحة الفئات ليست لون السمة: مستخدم اختار «رمادي» لا يجوز أن تصير
    كل سلاسله رمادية لا تُميَّز. فهي لوحة ثابتة مفحوصة لعمى الألوان
    (بروتان/ديوتان/تريتان) وللتباين مع الخلفية، بدرجات خاصة لكل وضع.
--}}
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    'use strict';

    // لوحتان مفحوصتان: ΔE بين كل زوج متجاور يتجاوز الحد في أنماط
    // عمى الألوان الثلاثة، والإضاءة داخل نطاق الوضع، والتباين ≥ 3:1
    var CATEGORICAL = {
        light: ['#2563EB', '#D97706', '#7C3AED'],
        dark:  ['#3B82F6', '#BE8A12', '#8B5CF6'],
    };

    // ألوان الحالة محجوزة: لا تُستعمل كسلسلة رابعة، وتُرافَق دائماً
    // باسم الحالة مكتوباً لا باللون وحده
    var STATUS = {
        light: { good: '#059669', warn: '#B45309', bad: '#DC2626', idle: '#64748B' },
        dark:  { good: '#10B981', warn: '#D97706', bad: '#EF4444', idle: '#94A3B8' },
    };

    function isDark() {
        return document.documentElement.getAttribute('data-theme') === 'dark';
    }

    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

        return v || fallback;
    }

    /** ثلاثية «212 175 55» → لون صالح للـcanvas مع شفافية اختيارية. */
    function accent(alpha) {
        var rgb = cssVar('--accent-rgb', '212 175 55').replace(/\s+/g, ' ').trim();

        return alpha === undefined || alpha === 1
            ? 'rgb(' + rgb.split(' ').join(', ') + ')'
            : 'rgba(' + rgb.split(' ').join(', ') + ', ' + alpha + ')';
    }

    function withAlpha(hex, alpha) {
        var h = hex.replace('#', '');
        var r = parseInt(h.substring(0, 2), 16);
        var g = parseInt(h.substring(2, 4), 16);
        var b = parseInt(h.substring(4, 6), 16);

        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    var MdChart = {
        isDark: isDark,
        accent: accent,
        withAlpha: withAlpha,

        /** لون الفئة رقم i — بترتيب ثابت لا يدور: الرابعة تندمج في «أخرى». */
        series: function (i) {
            var set = CATEGORICAL[isDark() ? 'dark' : 'light'];

            return set[i % set.length];
        },

        all: function () {
            return CATEGORICAL[isDark() ? 'dark' : 'light'].slice();
        },

        status: function (key) {
            return STATUS[isDark() ? 'dark' : 'light'][key] || this.series(0);
        },

        /** ألوان النصّ والشبكة والسطح — من الرموز نفسها لا مكتوبة يدوياً. */
        ink: function () { return isDark() ? '#CBD5E1' : '#475569'; },
        inkMuted: function () { return isDark() ? '#94A3B8' : '#64748B'; },
        grid: function () { return isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(15,23,42,0.07)'; },
        surface: function () { return isDark() ? '#121826' : '#FFFFFF'; },

        /** إعدادات مشتركة: شبكة خافتة، ونصّ بلون النصّ لا بلون السلسلة. */
        tooltip: function () {
            return {
                backgroundColor: this.surface(),
                titleColor: this.ink(),
                bodyColor: this.ink(),
                borderColor: this.grid(),
                borderWidth: 1,
                padding: 10,
                rtl: document.documentElement.getAttribute('dir') === 'rtl',
                displayColors: true,
                boxPadding: 4,
            };
        },

        scale: function (extra) {
            var base = {
                grid: { color: this.grid(), drawBorder: false },
                ticks: { color: this.inkMuted(), font: { size: 11 } },
            };

            return Object.assign(base, extra || {});
        },

        legend: function (show) {
            return {
                display: show !== false,
                position: 'bottom',
                labels: {
                    color: this.ink(),
                    boxWidth: 10,
                    boxHeight: 10,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 14,
                    font: { size: 11 },
                },
            };
        },

        /** إعادة الرسم عند تبديل السمة — الألوان مقروءة وقت البناء. */
        onThemeChange: function (redraw) {
            new MutationObserver(function (records) {
                for (var i = 0; i < records.length; i++) {
                    if (records[i].attributeName === 'data-theme'
                        || records[i].attributeName === 'data-palette') {
                        redraw();

                        return;
                    }
                }
            }).observe(document.documentElement, { attributes: true });
        },
    };

    window.MdChart = MdChart;
})();
</script>
