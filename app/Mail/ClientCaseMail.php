<?php

namespace App\Mail;

use App\Models\LegalCase;
use App\Support\ClientMessage;

/**
 * إشعارُ الموكّل بقضيته — قيداً أو تحديثاً.
 *
 * النصُّ والعنوان يُبنيان وقتَ الدفع لا وقتَ الإرسال: فلا يحمل الطابور
 * نموذجاً يُعاد جلبه فيرمي إن حُذف، وتصل الرسالةُ بحال القضية ساعةَ
 * وقع الحدث لا بحالها بعد دقيقة.
 */
class ClientCaseMail extends OfficeMail
{
    public readonly int $caseId;
    public readonly ?string $caseNumber;
    public readonly string $subjectText;
    public readonly string $bodyText;
    public readonly string $portalLink;

    public function __construct(MailKind $kind, LegalCase $case, public readonly string $clientName = '')
    {
        parent::__construct($kind);

        $this->caseId = (int) $case->id;
        $this->caseNumber = $case->case_number;

        $this->subjectText = $kind === MailKind::CaseCreated
            ? ClientMessage::inviteSubject($case)
            : ClientMessage::updateSubject($case);

        $this->bodyText = $kind === MailKind::CaseCreated
            ? ClientMessage::portalInvite($case)
            : ClientMessage::caseUpdate($case);

        $this->portalLink = ClientMessage::portalUrl();
    }

    /** قضيةٌ حُذفت في الدقيقة التي سبقت الإرسال: لا يُبشَّر بها أحد. */
    protected function stillRelevant(): bool
    {
        return LegalCase::whereKey($this->caseId)->exists();
    }

    protected function subjectLine(): string
    {
        return $this->subjectText;
    }

    protected function data(): array
    {
        return [
            'body' => $this->bodyText,
            'caseNumber' => $this->caseNumber,
            'clientName' => $this->clientName,
            'portalUrl' => $this->portalLink,
        ];
    }
}
