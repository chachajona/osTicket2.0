<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Mail;

use App\Exceptions\Scp\LegacyTemplateNotFoundException;
use App\Services\Scp\Mail\LegacyTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LegacyMailFixtures;
use Tests\TestCase;

final class LegacyTemplateRendererTest extends TestCase
{
    use LegacyMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureLegacyMailTables();
        $this->seedMailTemplates();
    }

    public function test_renders_reply_with_default_template_group(): void
    {
        $fixture = $this->seedMailTicket(tplId: 1);

        $rendered = app(LegacyTemplateRenderer::class)
            ->render('ticket.reply', $fixture['ticket'], $fixture['entry'], '<p>--<br>Bob</p>');

        $this->assertSame('Re: Test subject [#'.$fixture['ticket']->number.']', $rendered->subject);
        $this->assertStringContainsString('<p>Hi Alice,</p>', $rendered->bodyHtml);
        $this->assertStringContainsString('<p>The response body</p>', $rendered->bodyHtml);
        $this->assertStringContainsString('<p>--<br>Bob</p>', $rendered->bodyHtml);
        $this->assertStringContainsString('Hi Alice,', $rendered->bodyText);
        $this->assertStringNotContainsString('<p>', $rendered->bodyText);
    }

    public function test_uses_department_template_group(): void
    {
        $fixture = $this->seedMailTicket(tplId: 2);

        $rendered = app(LegacyTemplateRenderer::class)
            ->render('ticket.reply', $fixture['ticket'], $fixture['entry'], null);

        $this->assertSame('[Eng] Test subject', $rendered->subject);
    }

    public function test_escapes_html_substitutions(): void
    {
        $fixture = $this->seedMailTicket(subject: '<script>alert(1)</script>');

        $rendered = app(LegacyTemplateRenderer::class)
            ->render('ticket.reply', $fixture['ticket'], $fixture['entry'], null);

        $this->assertStringNotContainsString('<script>', $rendered->subject);
        $this->assertStringContainsString('&lt;script&gt;', $rendered->subject);
    }

    public function test_renders_note_alert_with_comments_override(): void
    {
        $fixture = $this->seedMailTicket();

        $rendered = app(LegacyTemplateRenderer::class)
            ->render('note.alert', $fixture['ticket'], $fixture['entry'], null, bodyOverride: 'Closing comment.');

        $this->assertStringContainsString('<p>Closing comment.</p>', $rendered->bodyHtml);
    }

    public function test_throws_when_template_missing(): void
    {
        $fixture = $this->seedMailTicket();

        $this->expectException(LegacyTemplateNotFoundException::class);

        app(LegacyTemplateRenderer::class)
            ->render('missing.template', $fixture['ticket'], $fixture['entry'], null);
    }
}
