<?php

use App\Models\Attachment;
use App\Models\File;
use App\Models\FileChunk;

test('File model reads from legacy database', function () {
    $file = File::first();

    if ($file === null) {
        $this->markTestSkipped('No files found in legacy database.');
    }

    expect($file)->toBeInstanceOf(File::class);
    expect($file->id)->toBeInt();
});

test('File loads chunks relation', function () {
    $file = File::whereHas('chunks')->with('chunks')->first();

    if ($file === null) {
        $this->markTestSkipped('No files with chunks found.');
    }

    expect($file->chunks)->not->toBeEmpty();
    expect($file->chunks->first()->file_id)->toBe($file->id);
});

test('File loads attachments relation', function () {
    $file = File::whereHas('attachments')->with('attachments')->first();

    if ($file === null) {
        $this->markTestSkipped('No files with attachments found.');
    }

    expect($file->attachments)->not->toBeEmpty();
    expect($file->attachments->first()->file_id)->toBe($file->id);
});

test('FileChunk model reads from legacy database', function () {
    $chunk = FileChunk::first();

    if ($chunk === null) {
        $this->markTestSkipped('No file chunks found in legacy database.');
    }

    expect($chunk)->toBeInstanceOf(FileChunk::class);
});

test('FileChunk loads file relation', function () {
    $chunk = FileChunk::with('file')->first();

    if ($chunk === null) {
        $this->markTestSkipped('No file chunks found.');
    }

    expect($chunk->file)->not->toBeNull();
    expect($chunk->file->id)->toBe($chunk->file_id);
});

test('Attachment model reads from legacy database', function () {
    $attachment = Attachment::first();

    if ($attachment === null) {
        $this->markTestSkipped('No attachments found in legacy database.');
    }

    expect($attachment)->toBeInstanceOf(Attachment::class);
    expect($attachment->id)->toBeInt();
});

test('Attachment loads file relation', function () {
    $attachment = Attachment::with('file')->first();

    if ($attachment === null) {
        $this->markTestSkipped('No attachments found.');
    }

    expect($attachment->file)->not->toBeNull();
    expect($attachment->file->id)->toBe($attachment->file_id);
});
