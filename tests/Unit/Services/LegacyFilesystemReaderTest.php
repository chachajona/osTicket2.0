<?php

use App\Models\File;
use App\Services\FileStorage\LegacyFilesystemReader;

test('legacy filesystem reader only serves files inside configured root', function () {
    $root = storage_path('framework/testing/legacy-files');
    $outside = storage_path('framework/testing/outside-legacy.txt');

    if (! is_dir($root)) {
        mkdir($root, 0777, true);
    }

    file_put_contents($root.'/allowed.txt', 'allowed');
    file_put_contents($outside, 'outside');

    config(['services.osticket.filesystem_root' => $root]);

    $reader = app(LegacyFilesystemReader::class);

    expect($reader->exists(new File([
        'id' => 1,
        'bk' => '6',
        'name' => 'allowed.txt',
        'attrs' => json_encode(['path' => 'allowed.txt']),
    ])))->toBeTrue()
        ->and($reader->exists(new File([
            'id' => 2,
            'bk' => '6',
            'name' => 'outside-legacy.txt',
            'attrs' => $outside,
        ])))->toBeFalse();
});

test('filesystem plugin reader derives path from file key', function () {
    $root = storage_path('framework/testing/plugin-files');
    $key = 'A0Ql4rlKDF2IdFknMpjhZlJetG81f_SQ';

    if (! is_dir($root.'/A')) {
        mkdir($root.'/A', 0777, true);
    }

    file_put_contents($root.'/A/'.$key, 'plugin');

    config(['services.osticket.filesystem_root' => $root]);

    $reader = app(LegacyFilesystemReader::class);

    expect($reader->exists(new File([
        'id' => 4,
        'bk' => 'F',
        'name' => 'Release_Note.docx',
        'key' => $key,
        'attrs' => null,
    ])))->toBeTrue();
});

test('legacy filesystem reader fails closed without configured root', function () {
    config(['services.osticket.filesystem_root' => null]);

    $reader = app(LegacyFilesystemReader::class);

    expect($reader->exists(new File([
        'id' => 3,
        'bk' => '6',
        'attrs' => __FILE__,
    ])))->toBeFalse();
});
