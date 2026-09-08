{{-- منتقي الدولة: بحثٌ بالكتابة، وطولٌ يتبع الدولة المختارة.

     ═══ لماذا بلا Alpine ═══

     ‏Alpine محمَّلٌ في تخطيط النظام وحدَه. وصفحةُ التسجيل التسويقيّة
     وبوّابةُ الموكّلين تخطيطان آخران بلا Alpine — وفيهما حقلا هاتف.
     فلو كُتب المنتقي بـx-data لعمل في مكانٍ وسقط صامتاً في مكانين،
     والسقوطُ الصامت لا يُشتكى منه فلا يُكتشف.

     ═══ ومن أين البيانات ═══

     من خيارات ‎<select>‎ نفسِها: كلُّ خيارٍ يحمل مفتاحَه وأطوالَه ومثالَه.
     فلا نسخةٌ ثانيةٌ من الجدول تفترق عن الأولى، وما تراه اللوحةُ هو
     نفسُه ما يُرسَل. وألوانُ اللوحة تأتي في data-row-class من المكوّن،
     فالسكربتُ واحدٌ للفاتح والداكن ولا يُنسخ مرّتين. --}}
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    'use strict';

    var AR = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    var isAr = (document.documentElement.getAttribute('lang') || '').indexOf('ar') === 0;

    function num(n) {
        n = String(n);
        return isAr ? n.replace(/\d/g, function (d) { return AR[+d]; }) : n;
    }

    /* «٨ أرقام» · «٩ أو ١٠ أرقام» · «من ٥ إلى ١٥ رقماً» */
    function lengthWord(lens) {
        if (!lens.length) return '';
        if (lens.length === 1) return isAr ? num(lens[0]) + ' أرقام' : lens[0] + ' digits';

        var contiguous = lens[lens.length - 1] - lens[0] === lens.length - 1;

        /* ألمانيا تقبل أحدَ عشرَ طولاً — سردُها كلِّها في سطر تلميحٍ
           لا يُقرأ، فتُقال مدىً */
        if (contiguous && lens.length > 2) {
            return isAr
                ? 'من ' + num(lens[0]) + ' إلى ' + num(lens[lens.length - 1]) + ' رقماً'
                : lens[0] + '–' + lens[lens.length - 1] + ' digits';
        }

        var head = lens.slice(0, -1).map(num).join(isAr ? '، ' : ', ');
        return isAr
            ? head + ' أو ' + num(lens[lens.length - 1]) + ' أرقام'
            : head + ' or ' + lens[lens.length - 1] + ' digits';
    }

    function digitsOnly(v) { return (v || '').replace(/\D+/g, ''); }

    function esc(s) {
        return String(s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    function build(field) {
        var select  = field.querySelector('[data-phone-country]');
        var input   = field.querySelector('[data-phone-national]');
        var trigger = field.querySelector('[data-phone-trigger]');
        var panel   = field.querySelector('[data-phone-panel]');
        var search  = field.querySelector('[data-phone-search]');
        var list    = field.querySelector('[data-phone-list]');
        var empty   = field.querySelector('[data-phone-empty]');
        var hint    = field.querySelector('[data-phone-hint]');
        var flagEl  = field.querySelector('[data-phone-flag]');
        var dialEl  = field.querySelector('[data-phone-dial]');

        if (!select || !input || !trigger || !panel || !list) return;

        var rowClass = panel.getAttribute('data-row-class') || '';
        var hintBase = hint ? hint.className : '';

        var rows = Array.prototype.map.call(select.options, function (o, i) {
            return {
                i: i,
                iso: o.value,
                name: o.getAttribute('data-name') || o.value,
                flag: o.getAttribute('data-flag') || '',
                dial: o.getAttribute('data-dial') || '',
                lens: (o.getAttribute('data-len') || '').split(',').filter(Boolean).map(Number),
                max: parseInt(o.getAttribute('data-max') || '15', 10),
                ex: o.getAttribute('data-ex') || '',
                main: o.getAttribute('data-main') === '1',
                q: (o.getAttribute('data-q') || '').toLowerCase()
            };
        });

        /* المفاتيح مرتَّبةً من الأطول: ‎+1‎ و‎+1‎ لعشرين دولة، و‎+96‎ لا
           وجود له لكنّ ‎+9‎ لو وُجد لابتلع ‎+968‎. فالأطولُ يُطابَق أوّلاً. */
        var byDial = rows.slice().sort(function (a, b) { return b.dial.length - a.dial.length; });

        function current() { return rows[select.selectedIndex] || rows[0]; }

        function paint() {
            var c = current();
            if (!c) return;

            if (flagEl) flagEl.textContent = c.flag;
            if (dialEl) dialEl.textContent = '+' + c.dial;
            input.setAttribute('maxlength', String(c.max));
            input.setAttribute('placeholder', c.ex);

            if (!hint) return;

            var typed = digitsOnly(input.value).length;
            var word = lengthWord(c.lens);

            if (!typed) {
                hint.className = hintBase;
                hint.textContent = word;
            } else if (c.lens.indexOf(typed) !== -1) {
                hint.className = hintBase + ' text-green-600';
                hint.textContent = '✓ ' + word;
            } else {
                hint.className = hintBase + ' text-red-600';
                hint.textContent = word + (isAr
                    ? ' — وهذا فيه ' + num(typed) + '.'
                    : ' — you typed ' + typed + '.');
            }
        }

        function choose(iso) {
            for (var i = 0; i < rows.length; i++) {
                if (rows[i].iso === iso) { select.selectedIndex = i; break; }
            }
            paint();
        }

        /* رقمٌ لُصق بمفتاحه: تُنقل الدولةُ ويُقصّ المفتاح — وهو أكثرُ ما
           يفعله الموظّف: ينسخ الرقم من واتساب كما هو. والصفرُ المحلّيُّ
           وحدَه (‎0552000531‎) ليس مفتاحاً فلا يُقصّ هنا؛ المكتبةُ في
           الخادم تعرف كيف تقشّره. */
        function absorbPrefix() {
            var raw = input.value.trim();
            if (raw.charAt(0) !== '+' && raw.slice(0, 2) !== '00') return false;

            var d = digitsOnly(raw).replace(/^00/, '');
            if (!d) return false;

            for (var i = 0; i < byDial.length; i++) {
                var r = byDial[i];
                if (!r.dial || d.indexOf(r.dial) !== 0 || d.length <= r.dial.length) continue;

                /* المفتاحُ المشترَك يذهب إلى صاحبه الأصل: ‎+1‎ لأمريكا لا
                   لأوّل جزيرةٍ في الترتيب الأبجديّ */
                var pick = r;
                if (!r.main) {
                    for (var j = 0; j < rows.length; j++) {
                        if (rows[j].dial === r.dial && rows[j].main) { pick = rows[j]; break; }
                    }
                }

                select.selectedIndex = pick.i;
                input.value = d.slice(r.dial.length);
                paint();
                return true;
            }

            return false;
        }

        function render(q) {
            q = (q || '').trim().toLowerCase();
            var html = '';
            var shown = 0;

            for (var i = 0; i < rows.length; i++) {
                var r = rows[i];
                if (q && r.q.indexOf(q) === -1 && ('+' + r.dial).indexOf(q) !== 0) continue;

                shown++;
                var on = i === select.selectedIndex;
                html += '<li role="option" data-iso="' + esc(r.iso) + '"'
                     + ' aria-selected="' + (on ? 'true' : 'false') + '"'
                     + ' class="flex items-center gap-2 px-3 py-2 cursor-pointer ' + rowClass
                     + (on ? ' font-semibold' : '') + '">'
                     + '<span class="text-base leading-none">' + r.flag + '</span>'
                     + '<span class="flex-1 truncate">' + esc(r.name) + '</span>'
                     + '<span class="font-mono text-xs opacity-60" dir="ltr">+' + esc(r.dial) + '</span>'
                     + '</li>';
            }

            list.innerHTML = html;
            if (empty) empty.hidden = shown > 0;
        }

        function highlight(li) {
            var all = list.querySelectorAll('[data-iso]');
            for (var i = 0; i < all.length; i++) all[i].classList.remove('ring-1', 'ring-inset');
            if (!li) return;
            li.classList.add('ring-1', 'ring-inset');
            li.scrollIntoView({ block: 'nearest' });
        }

        function open() {
            if (search) search.value = '';
            render('');
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');

            var active = list.querySelector('[aria-selected="true"]');
            if (active) active.scrollIntoView({ block: 'center' });
            if (search) search.focus();
        }

        function close() {
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }

        /* ═══ من هنا يبدأ التحسين ═══
           قبل هذا السطر الحقلُ عاملٌ بقائمةٍ أصليّةٍ بلا سكربت، وبعده أجمل. */
        select.classList.add('sr-only');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');
        trigger.style.display = '';

        trigger.addEventListener('click', function () {
            panel.hidden ? open() : close();
        });

        list.addEventListener('click', function (e) {
            var li = e.target.closest ? e.target.closest('[data-iso]') : null;
            if (!li) return;
            choose(li.getAttribute('data-iso'));
            close();
            input.focus();
        });

        if (search) {
            search.addEventListener('input', function () { render(search.value); });

            search.addEventListener('keydown', function (e) {
                var items = list.querySelectorAll('[data-iso]');
                var at = -1;
                for (var i = 0; i < items.length; i++) {
                    if (items[i].classList.contains('ring-1')) { at = i; break; }
                }

                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!items.length) return;
                    at = e.key === 'ArrowDown'
                        ? Math.min(items.length - 1, at + 1)
                        : Math.max(0, at <= 0 ? 0 : at - 1);
                    highlight(items[at]);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    var target = at >= 0 ? items[at] : items[0];
                    if (target) { choose(target.getAttribute('data-iso')); close(); input.focus(); }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    close();
                    trigger.focus();
                }
            });
        }

        document.addEventListener('click', function (e) {
            if (!panel.hidden && !field.contains(e.target)) close();
        });

        input.addEventListener('input', function () {
            /* «+» تبقى ما دامت أوّلَ الحقل: من دونها لا يستطيع أحدٌ أن
               يكتب مفتاحاً أصلاً — كان التنظيفُ يمحوها فور كتابتها */
            var before = input.value;
            var plus = before.charAt(0) === '+' ? '+' : '';
            var after = plus + digitsOnly(before);

            if (before !== after) {
                var pos = (input.selectionStart || 0) - (before.length - after.length);
                input.value = after;
                try { input.setSelectionRange(pos, pos); } catch (err) {}
            }

            if (absorbPrefix()) return;
            paint();
        });

        input.addEventListener('paste', function () { setTimeout(absorbPrefix, 0); });
        select.addEventListener('change', paint);

        paint();
    }

    function ready(field) {
        if (field.hasAttribute('data-phone-ready')) return;
        field.setAttribute('data-phone-ready', '1');
        build(field);
    }

    function boot(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var fields = scope.querySelectorAll('[data-phone-field]');

        for (var i = 0; i < fields.length; i++) ready(fields[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { boot(); });
    } else {
        boot();
    }

    /* حقلٌ يصل بعد أوّل رسمة (نافذةٌ تُفتح، صفٌّ يُضاف) يُهيَّأ كذلك */
    if (window.MutationObserver) {
        new MutationObserver(function (muts) {
            for (var i = 0; i < muts.length; i++) {
                for (var j = 0; j < muts[i].addedNodes.length; j++) {
                    var n = muts[i].addedNodes[j];
                    if (n.nodeType !== 1) continue;

                    /* لا يُمسح المستندُ كلُّه عند كلّ إضافة: الصفحةُ
                       تتغيّر مئاتِ المرّات، والمفحوصُ هو المضافُ وحدَه */
                    if (n.matches && n.matches('[data-phone-field]')) ready(n);
                    else if (n.querySelector && n.querySelector('[data-phone-field]')) boot(n);
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
</script>
