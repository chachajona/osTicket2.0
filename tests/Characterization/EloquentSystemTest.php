<?php

use App\Models\ApiKey;
use App\Models\Draft;
use App\Models\Event;
use App\Models\Lock;
use App\Models\Note;
use App\Models\Plugin;
use App\Models\PluginInstance;
use App\Models\Search;
use App\Models\Sequence;
use App\Models\Session;
use App\Models\Syslog;
use App\Models\Translation;

test('Lock model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['lock']);

    $lock = Lock::first();

    if ($lock === null) {
        $this->markTestSkipped('No locks found in legacy database.');
    }

    expect($lock)->toBeInstanceOf(Lock::class);
    expect($lock->lock_id)->toBeInt();
});

test('ApiKey model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['api_key']);

    $key = ApiKey::first();

    if ($key === null) {
        $this->markTestSkipped('No API keys found in legacy database.');
    }

    expect($key)->toBeInstanceOf(ApiKey::class);
    expect($key->id)->toBeInt();
});

test('Session model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['session']);

    $session = Session::first();

    if ($session === null) {
        $this->markTestSkipped('No sessions found in legacy database.');
    }

    expect($session)->toBeInstanceOf(Session::class);
    expect($session->session_id)->toBeString();
});

test('Plugin model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['plugin']);

    $plugin = Plugin::first();

    if ($plugin === null) {
        $this->markTestSkipped('No plugins found in legacy database.');
    }

    expect($plugin)->toBeInstanceOf(Plugin::class);
    expect($plugin->id)->toBeInt();
});

test('Plugin loads instances relation', function () {
    skipIfLegacyTablesMissing(['plugin', 'plugin_instance']);

    $plugin = Plugin::whereHas('instances')->with('instances')->first();

    if ($plugin === null) {
        $this->markTestSkipped('No plugins with instances found.');
    }

    expect($plugin->instances)->not->toBeEmpty();
    expect($plugin->instances->first()->plugin_id)->toBe($plugin->id);
});

test('PluginInstance model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['plugin_instance']);

    $instance = PluginInstance::first();

    if ($instance === null) {
        $this->markTestSkipped('No plugin instances found in legacy database.');
    }

    expect($instance)->toBeInstanceOf(PluginInstance::class);
    expect($instance->id)->toBeInt();
});

test('PluginInstance loads plugin relation', function () {
    skipIfLegacyTablesMissing(['plugin_instance', 'plugin']);

    $instance = PluginInstance::with('plugin')->first();

    if ($instance === null) {
        $this->markTestSkipped('No plugin instances found.');
    }

    expect($instance->plugin)->not->toBeNull();
    expect($instance->plugin->id)->toBe($instance->plugin_id);
});

test('Sequence model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['sequence']);

    $sequence = Sequence::first();

    if ($sequence === null) {
        $this->markTestSkipped('No sequences found in legacy database.');
    }

    expect($sequence)->toBeInstanceOf(Sequence::class);
    expect($sequence->id)->toBeInt();
});

test('Translation model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['translation']);

    $translation = Translation::first();

    if ($translation === null) {
        $this->markTestSkipped('No translations found in legacy database.');
    }

    expect($translation)->toBeInstanceOf(Translation::class);
    expect($translation->id)->toBeInt();
});

test('Draft model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['draft']);

    $draft = Draft::first();

    if ($draft === null) {
        $this->markTestSkipped('No drafts found in legacy database.');
    }

    expect($draft)->toBeInstanceOf(Draft::class);
    expect($draft->id)->toBeInt();
});

test('Note model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['note']);

    $note = Note::first();

    if ($note === null) {
        $this->markTestSkipped('No notes found in legacy database.');
    }

    expect($note)->toBeInstanceOf(Note::class);
    expect($note->id)->toBeInt();
});

test('Syslog model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['syslog']);

    $entry = Syslog::first();

    if ($entry === null) {
        $this->markTestSkipped('No syslog entries found in legacy database.');
    }

    expect($entry)->toBeInstanceOf(Syslog::class);
    expect($entry->log_id)->toBeInt();
});

test('Event model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['event']);

    $event = Event::first();

    if ($event === null) {
        $this->markTestSkipped('No events found in legacy database.');
    }

    expect($event)->toBeInstanceOf(Event::class);
    expect($event->id)->toBeInt();
});

test('Search model reads from legacy database', function () {
    skipIfLegacyTablesMissing(['_search']);

    $result = Search::first();

    if ($result === null) {
        $this->markTestSkipped('No search entries found in legacy database.');
    }

    expect($result)->toBeInstanceOf(Search::class);
});
