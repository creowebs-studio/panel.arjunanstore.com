<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Menggantikan contoh bawaan skeleton. Rute kini '/' → redirect ke '/orders'
 * (bukan 200), jadi asersi default `assertStatus(200)` tidak lagi berlaku.
 */
class ExampleTest extends TestCase
{
    public function test_root_redirects_to_orders(): void
    {
        $this->get('/')->assertRedirect('/orders');
    }
}
