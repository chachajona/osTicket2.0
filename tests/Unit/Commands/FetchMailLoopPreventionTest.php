<?php

use App\Console\Commands\FetchMailCommand;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $legacy = Schema::connection('legacy');

    if (! $legacy->hasTable('email')) {
        $legacy->create('email', function (Blueprint $table) {
            $table->unsignedInteger('email_id')->primary();
            $table->unsignedInteger('noautoresp')->default(0);
            $table->unsignedInteger('priority_id')->default(0);
            $table->unsignedInteger('dept_id')->default(0);
            $table->unsignedInteger('topic_id')->default(0);
            $table->string('email', 255);
            $table->string('name', 255)->default('');
            $table->text('notes')->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('updated')->nullable();
        });
    }

    DB::connection('legacy')->table('email')->delete();
});

function commandRecognizesSystemAddress(string $email): bool
{
    $command = app(FetchMailCommand::class);
    $method = new ReflectionMethod($command, 'isSystemAddress');
    $method->setAccessible(true);

    return $method->invoke($command, $email);
}

test('configured system email is matched case-insensitively', function () {
    DB::connection('legacy')->table('email')->insert([
        'email_id' => 1,
        'noautoresp' => 0,
        'priority_id' => 1,
        'dept_id' => 1,
        'topic_id' => 0,
        'email' => 'Support@Example.com',
        'name' => 'Support',
        'notes' => '',
        'created' => now()->format('Y-m-d H:i:s'),
        'updated' => now()->format('Y-m-d H:i:s'),
    ]);

    expect(commandRecognizesSystemAddress('support@example.COM'))->toBeTrue();
});

test('external sender is not treated as a system address', function () {
    DB::connection('legacy')->table('email')->insert([
        'email_id' => 1,
        'noautoresp' => 0,
        'priority_id' => 1,
        'dept_id' => 1,
        'topic_id' => 0,
        'email' => 'support@example.com',
        'name' => 'Support',
        'notes' => '',
        'created' => now()->format('Y-m-d H:i:s'),
        'updated' => now()->format('Y-m-d H:i:s'),
    ]);

    expect(commandRecognizesSystemAddress('customer@elsewhere.org'))->toBeFalse();
});
