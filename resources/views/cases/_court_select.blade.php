@php $selected = $selected ?? old('court'); @endphp
<select name="court" id="court" required
    class="w-full rounded-lg bg-[#0D1321] border border-white/20 px-4 py-2.5 text-white text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A] @error('court') border-red-500/50 @enderror">
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
</select>
