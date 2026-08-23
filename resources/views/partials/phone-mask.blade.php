{{-- قناع حقول الهاتف: أرقام ومفتاح دولة اختياري لا غير.

     كان الحقل يقبل الحروف فيُحفظ رقمٌ لا يُتّصل به. والتحقّق مفروض في
     الخادم أيضاً (App\Support\GulfPhone) — هذا يمنع الخطأ وقت كتابته
     لا بدلاً عن الخادم.

     يُستدعى من كل تخطيط فيه حقل هاتف، لا من واحد: أول مرة كُتب في
     layouts.app وحده، فبقيت صفحة التسجيل التسويقية بلا قناع. --}}
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    function clean(v) {
        var plus = v.trim().charAt(0) === '+';
        var d = v.replace(/[^\d]/g, '');
        return (plus ? '+' : '') + d;
    }

    document.querySelectorAll('input[data-phone]').forEach(function (el) {
        el.addEventListener('input', function () {
            var before = el.value, after = clean(before);
            if (before !== after) {
                // المؤشّر يبقى مكانه، وإلّا قفز إلى آخر السطر كلّما حُذف رمز
                var pos = el.selectionStart - (before.length - after.length);
                el.value = after;
                try { el.setSelectionRange(pos, pos); } catch (e) {}
            }
        });

        el.addEventListener('blur', function () {
            if (!el.value.replace(/[^\d]/g, '')) el.value = '';
        });
    });
})();
</script>
