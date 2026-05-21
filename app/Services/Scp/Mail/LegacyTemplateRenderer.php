<?php

declare(strict_types=1);

namespace App\Services\Scp\Mail;

use App\Exceptions\Scp\LegacyTemplateNotFoundException;
use App\Mail\RenderedMail;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

final class LegacyTemplateRenderer
{
    public function render(
        string $codeName,
        Ticket $ticket,
        ThreadEntry $entry,
        ?string $signatureText,
        ?string $bodyOverride = null,
    ): RenderedMail {
        $ticket->loadMissing(['cdata', 'department', 'user.defaultEmail']);
        $entry->loadMissing('staff');

        $deptTplId = (int) ($ticket->department?->tpl_id ?? 0);
        $template = $this->loadTemplate($codeName, $deptTplId);
        $table = $this->substitutionTable($ticket, $entry, $signatureText, $bodyOverride);

        $subject = $this->applySubstitutions((string) $template->subject, $table);
        $bodyHtml = $this->applySubstitutions((string) $template->body, $table);

        return new RenderedMail(
            subject: $subject,
            bodyHtml: $bodyHtml,
            bodyText: $this->htmlToText($bodyHtml),
        );
    }

    private function loadTemplate(string $codeName, int $deptTplId): object
    {
        if ($deptTplId > 0) {
            $template = DB::connection('legacy')->table('email_template')
                ->where('tpl_id', $deptTplId)
                ->where('code_name', $codeName)
                ->first();

            if ($template !== null) {
                return $template;
            }
        }

        $template = DB::connection('legacy')->table('email_template')
            ->where('tpl_id', 1)
            ->where('code_name', $codeName)
            ->first();

        if ($template === null) {
            throw new LegacyTemplateNotFoundException($codeName, $deptTplId);
        }

        return $template;
    }

    /**
     * @return array<string, string>
     */
    private function substitutionTable(
        Ticket $ticket,
        ThreadEntry $entry,
        ?string $signatureText,
        ?string $bodyOverride,
    ): array {
        $body = $bodyOverride ?? (string) $entry->body;

        return [
            '%{ticket.number}' => (string) $ticket->number,
            '%{ticket.subject}' => (string) ($ticket->subject ?? $ticket->cdata?->subject ?? ''),
            '%{ticket.name}' => (string) ($ticket->user?->name ?? ''),
            '%{ticket.email}' => (string) ($ticket->user?->defaultEmail?->address ?? ''),
            '%{ticket.dept.name}' => (string) ($ticket->department?->name ?? ''),
            '%{ticket.staff.name}' => (string) ($entry->staff?->displayName() ?? ''),
            '%{response}' => $body,
            '%{comments}' => $body,
            '%{signature}' => $signatureText ?? '',
        ];
    }

    /**
     * @param  array<string, string>  $table
     */
    private function applySubstitutions(string $template, array $table): string
    {
        foreach ($table as $token => $value) {
            $replacement = $token === '%{signature}'
                ? $value
                : htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $template = str_replace($token, $replacement, $template);
        }

        return $template;
    }

    private function htmlToText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
