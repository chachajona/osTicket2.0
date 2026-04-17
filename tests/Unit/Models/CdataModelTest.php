<?php

use App\Models\OrganizationCdata;
use App\Models\TicketCdata;
use App\Models\UserCdata;

test('ticket organization and user cdata models use non-incrementing primary keys', function () {
    expect((new OrganizationCdata)->getIncrementing())->toBeFalse();
    expect((new UserCdata)->getIncrementing())->toBeFalse();
    expect((new TicketCdata)->getIncrementing())->toBeFalse();
});
