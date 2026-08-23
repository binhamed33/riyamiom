<?php

namespace App\Observers;

use App\Models\CaseActivity;
use App\Models\Document;
use App\Support\CaseTimeline;

/**
 * المستند يُسجَّل في المسار الزمني — إن كان مسموحاً للموكّل.
 *
 * المستند الداخلي لا يُسجَّل إطلاقاً: سطر «أُرفق مستند» في مسار الموكّل
 * يخبره بوجود ما لم يُسمح له بمعرفته. والحجب هنا لا يُعوَّض بترشيح
 * لاحق — ما لا يُكتب لا يُسرَّب.
 */
class DocumentObserver
{
    public function created(Document $document): void
    {
        if (!$this->visibleToClient($document)) {
            return;
        }

        CaseTimeline::log(
            $document->case_id,
            CaseActivity::TYPE_DOCUMENT,
            'أُرفق مستند',
            $this->name($document)
        );
    }

    public function updated(Document $document): void
    {
        if (!$document->wasChanged(['client_visible', 'access_level'])) {
            return;
        }

        if (!$this->visibleToClient($document)) {
            return;
        }

        // كان مرئياً قبل التعديل؟ إذن لا جديد يُخبَر به
        $wasVisible = (bool) $document->getOriginal('client_visible')
            && $document->getOriginal('access_level') !== Document::ACCESS_PRIVATE;

        if ($wasVisible) {
            return;
        }

        CaseTimeline::log(
            $document->case_id,
            CaseActivity::TYPE_DOCUMENT,
            'أُتيح لك مستند',
            $this->name($document)
        );
    }

    private function visibleToClient(Document $document): bool
    {
        return (bool) $document->client_visible
            && $document->access_level !== Document::ACCESS_PRIVATE;
    }

    private function name(Document $document): string
    {
        return (string) ($document->title ?? $document->original_name ?? 'مستند');
    }
}
