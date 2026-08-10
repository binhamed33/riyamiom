<?php

namespace Tests\Unit;

use App\Services\NaturalActionParser;
use PHPUnit\Framework\TestCase;

class NaturalActionParserTest extends TestCase
{
    private NaturalActionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new NaturalActionParser();
    }

    public function test_extracts_call_with_subject(): void
    {
        $actions = $this->parser->parse('اتصلت بأحمد اليوم وقال سيرسل صورة البطاقة غداً');

        $types = array_column($actions, 'type');
        $this->assertContains('call', $types);

        $call = collect($actions)->firstWhere('type', 'call');
        $this->assertNotNull($call);
        $this->assertStringContainsString('أحمد', $call['title']);
    }

    public function test_extracts_task_with_due_date(): void
    {
        $actions = $this->parser->parse('المحامي سيرسل مذكرة الدفاع غداً');

        $task = collect($actions)->firstWhere('type', 'task');
        $this->assertNotNull($task);
        $this->assertNotNull($task['due_date']);
        $this->assertEquals(now()->addDay()->toDateString(), $task['due_date']);
    }

    public function test_extracts_appointment_with_date(): void
    {
        $actions = $this->parser->parse('حددنا موعد جلسة تحضيرية 15/09/2026 في المحكمة');

        $appointment = collect($actions)->firstWhere('type', 'appointment');
        $this->assertNotNull($appointment);
        $this->assertEquals('2026-09-15', $appointment['due_date']);
    }

    public function test_plain_note_when_no_action_keywords(): void
    {
        $actions = $this->parser->parse('راجعنا ملف القضية كاملاً اليوم');

        $this->assertCount(1, $actions);
        $this->assertEquals('note', $actions[0]['type']);
    }

    public function test_empty_message_returns_no_actions(): void
    {
        $this->assertSame([], $this->parser->parse('   '));
    }

    public function test_cap_at_three_actions(): void
    {
        $actions = $this->parser->parse('اتصلت بالعميل وحددنا موعد غداً وسيرسل المستندات بعد يومين');

        $this->assertLessThanOrEqual(3, count($actions));
    }
}