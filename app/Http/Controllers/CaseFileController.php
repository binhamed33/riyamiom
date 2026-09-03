<?php

namespace App\Http\Controllers;

use App\Models\LegalCase;
use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

class CaseFileController extends Controller
{
    public function download(LegalCase $case): Response
    {
        $user = auth()->user();

        // وملفُّ القضية PDF كذلك: كان يطبع عناوينَ المستندات الخاصّة
        // ويكتب بجانبها «خاص» — لمن لا يملكها
        $case->load([
            'client', 'lawyer', 'sessions', 'tasks.assignee',
            'documents' => fn ($q) => $q->visibleTo(auth()->user()),
            'documents.uploader',
        ]);

        try {
            $fontDir = resource_path('fonts');

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font' => 'cairo',
                'fontDir' => [$fontDir],
                'fontdata' => [
                    'cairo' => [
                        'R' => 'Cairo-Regular.ttf',
                        'B' => 'Cairo-Bold.ttf',
                        'useOTL' => 0xFF,
                        'useKashida' => 75,
                    ],
                ],
                'biDirectional' => true,
                'margin_header' => 10,
                'margin_footer' => 10,
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 15,
                'margin_bottom' => 15,
            ]);

            $mpdf->autoArabic = true;

            $mpdf->SetDirectionality('rtl');
            $mpdf->useSubstitutions = true;

            $html = view('pdf.case-file', ['case' => $case, 'title' => 'ملف القضية - ' . $case->case_number])->render();
            $html = preg_replace('/^\xEF\xBB\xBF|\xEF\xBB\xBF$/', '', $html);
            $mpdf->WriteHTML($html);

            // رقمُ القضية يكتبه الموظّف حرّاً بلا حدٍّ ولا محارف مسموحة،
            // وكان يُلصق في الترويسة بين علامتَي اقتباس: رقمٌ فيه " يختار
            // لزملائه اسمَ الملفّ الذي يُحفظ به. يُنظَّف ثمّ يُترك البناءُ
            // لـstreamDownload — وهي تقتبس وتتحقّق كما فعلت downloadName.
            $safeNumber = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', (string) $case->case_number) ?: 'case';
            $fileName = 'case-file-' . trim($safeNumber, '-') . '-' . date('Y-m-d') . '.pdf';

            $pdfContent = $mpdf->Output($fileName, 'S');

            return response()->streamDownload(
                static fn () => print($pdfContent),
                $fileName,
                ['Content-Type' => 'application/pdf'],
            );
        } catch (MpdfException $e) {
            abort(500, 'فشل إنشاء ملف PDF: ' . $e->getMessage());
        } catch (\Exception $e) {
            abort(500, 'خطأ غير متوقع أثناء إنشاء ملف PDF');
        }
    }
}
