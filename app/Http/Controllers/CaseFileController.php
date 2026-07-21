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
        if ($user->isLawyer() && $case->lawyer_id !== $user->id) {
            abort(403);
        }

        $case->load(['client', 'lawyer', 'sessions', 'tasks.assignee', 'documents.uploader']);

        try {
            $fontDir = resource_path('fonts');

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'direction' => 'rtl',
                'default_font' => 'Amiri',
                'fontDir' => [$fontDir],
                'fontdata' => [
                    'amiri' => [
                        'R' => 'Amiri-Regular.ttf',
                        'B' => 'Amiri-Bold.ttf',
                    ],
                    'cairo' => [
                        'R' => 'Cairo-Regular.ttf',
                        'B' => 'Cairo-Bold.ttf',
                    ],
                ],
                'config_font_variables' => [
                    'amiri' => [
                        'R' => 'Amiri-Regular.ttf',
                        'B' => 'Amiri-Bold.ttf',
                    ],
                    'cairo' => [
                        'R' => 'Cairo-Regular.ttf',
                        'B' => 'Cairo-Bold.ttf',
                    ],
                ],
                'abicorrect' => 1,
                'autoArabic' => true,
                'margin_header' => 10,
                'margin_footer' => 10,
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 15,
                'margin_bottom' => 15,
            ]);

            $mpdf->SetFont('amiri');

            $html = view('pdf.case-file', compact('case'))->render();
            $mpdf->WriteHTML($html);

            $fileName = 'case-file-' . str_replace('/', '-', $case->case_number) . '-' . date('Y-m-d') . '.pdf';

            return response($mpdf->Output($fileName, 'I'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        } catch (MpdfException $e) {
            abort(500, 'فشل إنشاء ملف PDF: ' . $e->getMessage());
        } catch (\Exception $e) {
            abort(500, 'خطأ غير متوقع أثناء إنشاء ملف PDF');
        }
    }
}
