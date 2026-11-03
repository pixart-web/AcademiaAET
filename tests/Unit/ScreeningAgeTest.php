<?php

namespace Tests\Unit;

use App\Services\Screening\ScreeningAge;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScreeningAgeTest extends TestCase
{
    public function test_age_in_completed_months_and_label(): void
    {
        $on = Carbon::parse('2026-11-03');

        $this->assertSame(51, ScreeningAge::months(Carbon::parse('2022-07-20'), $on));
        $this->assertSame('4 anos e 3 meses', ScreeningAge::label(51));
        $this->assertSame('3 anos e 0 meses', ScreeningAge::label(36));
        $this->assertSame('1 ano e 1 mês', ScreeningAge::label(13));
    }

    public function test_a_month_is_only_complete_on_the_birth_day_of_month(): void
    {
        $this->assertSame(35, ScreeningAge::months(Carbon::parse('2023-11-04'), Carbon::parse('2026-11-03')));
        $this->assertSame(36, ScreeningAge::months(Carbon::parse('2023-11-03'), Carbon::parse('2026-11-03')));
    }

    public function test_block_boundaries(): void
    {
        $this->assertNull(ScreeningAge::block(35));
        $this->assertSame('3', ScreeningAge::block(36));
        $this->assertSame('3', ScreeningAge::block(47));
        $this->assertSame('4', ScreeningAge::block(48));
        $this->assertSame('4', ScreeningAge::block(59));
        $this->assertSame('5', ScreeningAge::block(60));
        $this->assertSame('5', ScreeningAge::block(71));
        $this->assertSame('6-7', ScreeningAge::block(72));
        $this->assertSame('6-7', ScreeningAge::block(95));
        $this->assertNull(ScreeningAge::block(96));
    }
}
