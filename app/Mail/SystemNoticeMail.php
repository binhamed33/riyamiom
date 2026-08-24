<?php

namespace App\Mail;

/**
 * رسالةُ نظامٍ رسمية — موقَّعة «مُداوَلة» لا باسم المكتب، لأنّ مصدرها
 * النظام نفسه لا المكتب.
 */
class SystemNoticeMail extends OfficeMail
{
    public function __construct(
        public readonly string $heading,
        public readonly string $bodyText,
        public readonly string $recipientName = '',
    ) {
        parent::__construct(MailKind::SystemNotice);
    }

    protected function subjectLine(): string
    {
        return $this->heading . ' - ' . \App\Support\MailIdentity::SYSTEM_NAME;
    }

    protected function data(): array
    {
        return [
            'body' => $this->bodyText,
            'heading' => $this->heading,
            'clientName' => $this->recipientName,
            'caseNumber' => null,
            'portalUrl' => null,
        ];
    }
}
