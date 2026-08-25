#!/bin/bash
#
# ضبط بريد مُداوَلة المركزي في .env — بلا أن تمرّ كلمة المرور بمكانٍ يُقرأ.
#
# ═══ لماذا هذه الأداة ═══
#
# كلمةُ مرور التطبيق سرٌّ لا يُسترجع: من رآها مرّةً ملكها. وتحريرُ .env
# يدوياً يجعلها تمرّ بالحافظة وبالطرفية وربما بمحادثة. وكتابتُها في أمرٍ
# مباشر تُخلّدها في ~/.bash_history.
#
# فهنا تُقرأ بـ read -rs: لا تظهر على الشاشة، ولا تدخل تاريخ الأوامر،
# ولا تُطبع في أي مخرَج. وتُكتب في .env وحده ثم تُنسى من الذاكرة.
#
# ولا تُحذف بياناتٌ ولا تُلمس قاعدة: هذا ملفُ إعدادات لا أكثر، ونسخةٌ
# منه تُحفظ قبل كل تعديل.

set -u

MAIL_USER="${MAIL_USER:-mudawalah@gmail.com}"
PHP="${PHP:-php8.4}"

say()  { echo; echo "──────────────────────────────────────────────"; echo "  $*"; echo "──────────────────────────────────────────────"; }
ok()   { echo "  ✓ $*"; }
bad()  { echo "  ✗ $*"; }
note() { echo "    $*"; }

# ── المكاتب: نفس منطق update-all.sh، ومعها مكتب الوالد لأنّ هذا إعداد
#    لا بيانات. ويُطلب إقرارٌ صريح قبل لمسه.
find_offices() {
    local d
    for d in /home/office-*/htdocs/*.riyami.om /home/riyami-office/htdocs/office.riyami.om; do
        [ -f "$d/artisan" ] && [ -f "$d/.env" ] && echo "$d"
    done
}

# ── الصلاحية أولاً.
#
# مجلدات المكاتب مملوكة لمستخدميها، ومن يشغّل هذا كـ ubuntu لا يقرأ
# منها شيئاً — فيرى «Permission denied» متكرّرة ولا يعرف أنّ السبب
# هويّته لا الأداة. يُقال له هنا بوضوح.
if [ "$(id -u)" -ne 0 ]; then
    bad "هذه الأداة تحتاج صلاحية root."
    note "نفّذ أولاً:  sudo -i"
    note "ثم أعد تشغيل الأمر نفسه."
    exit 1
fi

TARGET="${1:-}"

if [ -n "$TARGET" ]; then
    [ -f "$TARGET/.env" ] || { bad "لا يوجد .env في $TARGET"; exit 1; }
    OFFICES="$TARGET"
else
    OFFICES="$(find_offices)"
fi

[ -n "$OFFICES" ] || { bad "لم يُعثر على أي مكتب."; exit 1; }

say "ضبط البريد المركزي — $MAIL_USER"
echo "  المكاتب التي ستُضبط:"
while read -r d; do note "· $d"; done <<< "$OFFICES"

echo
echo "  ملاحظة: لن تظهر كلمة المرور أثناء الكتابة — الصقها ثم اضغط Enter."
echo

# ── القراءة الصامتة. -r كي لا تُفسَّر الشرطات المائلة، -s كي لا تُطبع.
read -rsp "  الصق App Password (١٦ حرفاً): " APP_PW
echo

# مسافات Google العارضة تُنزع: يعرضها أربع مجموعات وهي متّصلة
APP_PW="${APP_PW// /}"

if [ ${#APP_PW} -ne 16 ]; then
    bad "الطول ${#APP_PW} — المتوقّع ١٦ حرفاً. أُلغي بلا تعديل."
    unset APP_PW
    exit 1
fi

if ! [[ "$APP_PW" =~ ^[a-z]+$ ]]; then
    bad "الصيغة غير متوقّعة — App Password من Google حروفٌ صغيرة فقط. أُلغي بلا تعديل."
    unset APP_PW
    exit 1
fi

ok "استُلمت (${#APP_PW} حرفاً) — لن تُطبع بعد الآن."

# ── تعديلٌ ذرّي لمفتاحٍ واحد: يُستبدل إن وُجد، ويُضاف إن غاب.
put_key() {
    local file="$1" key="$2" value="$3"

    if grep -q "^${key}=" "$file"; then
        # القيمة تُمرَّر عبر awk لا sed: لا تفسير لمحارف خاصة فيها
        awk -v k="$key" -v v="$value" '
            $0 ~ "^" k "=" { print k "=" v; next }
            { print }
        ' "$file" > "$file.tmp" && mv "$file.tmp" "$file"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

DONE=0
SKIPPED=0

while read -r dir; do
    [ -n "$dir" ] || continue

    domain="$(basename "$dir")"
    owner="$(stat -c '%U' "$dir/.env")"

    say "$domain"

    # مكتب الوالد إنتاجٌ حقيقي: يُطلب إقرارٌ صريح قبل لمس إعداداته
    if [ "$domain" = "office.riyami.om" ] && [ "${CONFIRM_PROTECTED:-0}" != "1" ]; then
        bad "نطاق محمي — لم يُلمس."
        note "لضبطه: CONFIRM_PROTECTED=1 bash $0 $dir"
        SKIPPED=$((SKIPPED + 1))
        continue
    fi

    backup="$dir/.env.bak-$(date +%Y%m%d-%H%M%S)"
    cp -p "$dir/.env" "$backup" || { bad "تعذّرت النسخة الاحتياطية — لم يُعدَّل شيء."; SKIPPED=$((SKIPPED+1)); continue; }
    ok "نسخة من .env: $(basename "$backup")"

    put_key "$dir/.env" MAIL_MAILER smtp
    put_key "$dir/.env" MAIL_HOST smtp.gmail.com
    put_key "$dir/.env" MAIL_PORT 587
    put_key "$dir/.env" MAIL_SCHEME null
    put_key "$dir/.env" MAIL_USERNAME "$MAIL_USER"
    put_key "$dir/.env" MAIL_PASSWORD "$APP_PW"
    put_key "$dir/.env" MAIL_FROM_ADDRESS "\"$MAIL_USER\""
    put_key "$dir/.env" MAIL_FROM_NAME '"مُداوَلة"'
    put_key "$dir/.env" QUEUE_CONNECTION database

    chown "$owner":"$owner" "$dir/.env" 2>/dev/null
    chmod 600 "$dir/.env" 2>/dev/null
    ok "‏.env مضبوط (الصلاحيات 600 — لا يقرؤه إلا صاحبه)"

    sudo -u "$owner" "$PHP" "$dir/artisan" config:clear --no-interaction >/dev/null 2>&1 \
        && ok "الكاش نُظّف" || bad "تعذّر تنظيف الكاش — نظّفه يدوياً"

    DONE=$((DONE + 1))
done <<< "$OFFICES"

# ── تُمحى من الذاكرة: العملية قد تبقى، والقيمة لا تبقى معها
unset APP_PW

say "الخلاصة"
ok "ضُبط: $DONE"
[ "$SKIPPED" -gt 0 ] && bad "تُخطّي: $SKIPPED"

echo
echo "  الخطوة التالية — جرّب على مكتبٍ واحد:"
echo "    cd <مجلد المكتب> && sudo -u <المالك> $PHP artisan mail:doctor --to=you@example.com --now"
echo
echo "  ولا تنسَ: تاريخُ الأوامر لا يحمل كلمة المرور، لكنّ النسخ الاحتياطية"
echo "  من .env تحملها. احذف القديمة منها متى اطمأننت:"
echo "    find /home/*/htdocs/*/.env.bak-* -mtime +7 -delete"
