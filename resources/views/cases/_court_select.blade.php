@php $selected = $selected ?? old('court'); @endphp
<select name="court" id="court" required
    class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40 @error('court') border-red-500/50 @enderror">
    <option value="">اختر المحكمة هنا</option>
    <optgroup label="المحكمة العليا">
        <option value="المحكمة العليا" {{ $selected == 'المحكمة العليا' ? 'selected' : '' }}>المحكمة العليا</option>
    </optgroup>
    <optgroup label="محاكم الاستئناف">
        @foreach(['مسقط','الداخلية','شمال الباطنة','جنوب الباطنة','ظفار','البريمي','مسندم','الوسطى','شمال الشرقية','جنوب الشرقية'] as $city)
            @php $name = "محكمة استئناف {$city}"; @endphp
            <option value="{{ $name }}" {{ $selected == $name ? 'selected' : '' }}>{{ $name }}</option>
        @endforeach
    </optgroup>
    @php
        $groups = [
            'مسقط' => ['بمسقط','بالوطية','بالسيب','بقريات'],
            'الداخلية' => ['بنزوى','ببهلا','بأدم','بأزكي','بسمائل','ببدبد'],
            'جنوب الباطنة' => ['ببركاء','بوادي المعاول','بنخل','بالرستاق','بالمصنعة'],
            'شمال الباطنة' => ['بالسويق','بالخابورة','بصحم','بصحار','بلوى','بشناص'],
            'الظاهرة' => ['بعبري','بينقل','بضنك'],
            'البريمي' => ['بالبريمي','بمحضة'],
            'مسندم' => ['بخصب','بدباء'],
            'ظفار' => ['بصلالة','بثمريت'],
            'شمال الشرقية' => ['بإبراء','بسمد الشأن','بالمضيبي','بدماء والطائين','بالقابل','بوادي بني خالد','ببدية'],
            'جنوب الشرقية' => ['بالكامل والوافي','بجعلان بني بو حسن','بجعلان بني بو علي','بصور','بمصيرة'],
            'الوسطى' => ['بالدقم','بهيماء','بمحوت'],
        ];
    @endphp
    @foreach($groups as $region => $courts)
        <optgroup label="المحاكم الابتدائية - {{ $region }}">
            @foreach($courts as $court)
                @php $name = "المحكمة الابتدائية {$court}"; @endphp
                <option value="{{ $name }}" {{ $selected == $name ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </optgroup>
    @endforeach
    <optgroup label="دوائر المحاكم الابتدائية">
        @php $daer = ['الدائرة المدنية','الدائرة التجارية','الدائرة العمالية','دائرة الأحوال الشخصية','الدائرة الجزائية (الجنائية)','دائرة التنفيذ المدني','دائرة التنفيذ الجزائي','دائرة الأوامر على العرائض والأوامر الوقتية','دائرة الإفلاس وإعادة الهيكلة','دائرة القضاء المستعجل','دائرة الإيجارات','دائرة المرور','دائرة الأحداث']; @endphp
        @foreach($daer as $d)
            <option value="{{ $d }}" {{ $selected == $d ? 'selected' : '' }}>{{ $d }}</option>
        @endforeach
    </optgroup>
</select>
