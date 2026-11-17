<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parses_german_and_plain_formats(): void
    {
        $this->assertSame(129950, Money::toCents('1.299,50'));
        $this->assertSame(129950, Money::toCents('1299.50'));
        $this->assertSame(1200, Money::toCents('12'));
        $this->assertNull(Money::toCents(''));
        $this->assertNull(Money::toCents(null));
    }

    public function test_formats_for_display_and_input(): void
    {
        $this->assertSame('1.299,50 €', Money::format(129950));
        $this->assertSame('—', Money::format(null));
        $this->assertSame('1299,50', Money::input(129950));
    }
}
