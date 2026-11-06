<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_la_raiz_redirige_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_el_login_se_muestra(): void
    {
        $this->get('/login')->assertOk()->assertSee('Ingresar');
    }
}
