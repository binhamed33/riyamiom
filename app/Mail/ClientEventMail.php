<?php

namespace App\Mail;

use App\Support\ClientMessage;

/**
 * إشعارُ الموكّل بجلسةٍ أو مستند.
 *
 * النصُّ يُمرَّر جاهزاً من مُطلِق الإشعار: تفاصيلُ الجلسة تُبنى حيث
 * تُعرف، لا هنا.
 */
class ClientEventMail extends OfficeMail
{
    public function __construct(
        MailKind $kind,
        public readonly string $heading,
        public readonly string $bodyText,
        public readonly string $clientName = '',
        public readonly ?string $caseNumber = null,
    ) {
        parent::__construct($kind);
    }

    protected function subjectLine(): string
    {
        $subject = $this->heading . ' - ' . \App\Support\MailIdentity::fromName();

        return $this->caseNumber ? $subject . ' (قضية ' . $this->caseNumber . ')' : $subject;
    }

    protected function data(): array
    {
        return [
            'body' => $this->bodyText,
            'heading' => $this->heading,
            'clientName' => $this->clientName,
            'caseNumber' => $this->caseNumber,
            'portalUrl' => ClientMessage::portalUrl(),
        ];
    }
}
