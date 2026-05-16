<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Mail\Mailable;

class TestMailable extends Mailable
{
    public function build(): self
    {
        return $this->subject('test')->html('<p>hi</p>');
    }
}
