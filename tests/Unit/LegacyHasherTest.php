<?php

use App\Services\LegacyHasher;

test('verifies bcrypt passwords', function () {
    $hasher = new LegacyHasher();
    $hash = $hasher->make('testpassword');

    expect($hasher->check('testpassword', $hash))->toBeTrue();
    expect($hasher->check('wrongpassword', $hash))->toBeFalse();
});

test('verifies MD5 passwords', function () {
    $hasher = new LegacyHasher();
    $md5Hash = md5('password'); // 5f4dcc3b5aa765d61d8327deb882cf99

    expect($hasher->check('password', $md5Hash))->toBeTrue();
    expect($hasher->check('wrong', $md5Hash))->toBeFalse();
});

test('returns false for empty hashed value', function () {
    $hasher = new LegacyHasher();

    expect($hasher->check('password', ''))->toBeFalse();
    expect($hasher->check('password', null))->toBeFalse();
});

test('MD5 hash needs rehash', function () {
    $hasher = new LegacyHasher();

    expect($hasher->needsRehash(md5('anything')))->toBeTrue();
});

test('bcrypt hash does not need rehash', function () {
    $hasher = new LegacyHasher();
    $hash = $hasher->make('testpassword');

    expect($hasher->needsRehash($hash))->toBeFalse();
});

test('make always produces bcrypt hash', function () {
    $hasher = new LegacyHasher();
    $hash = $hasher->make('testpassword');

    expect($hash)->toStartWith('$2y$');
});

test('info returns correct algorithm for bcrypt', function () {
    $hasher = new LegacyHasher();
    $hash = $hasher->make('testpassword');

    expect($hasher->info($hash)['algoName'])->toBe('bcrypt');
});

test('info returns md5 for non-bcrypt hash', function () {
    $hasher = new LegacyHasher();

    expect($hasher->info(md5('test'))['algoName'])->toBe('md5');
});

test('verifies $2a$ bcrypt hashes from legacy osTicket', function () {
    $hasher = new LegacyHasher();
    // $2a$ is the prefix used by osTicket's Passwd::hash()
    $hash = password_hash('testpassword', PASSWORD_BCRYPT);

    expect($hasher->check('testpassword', $hash))->toBeTrue();
    expect($hasher->check('wrongpassword', $hash))->toBeFalse();
});
