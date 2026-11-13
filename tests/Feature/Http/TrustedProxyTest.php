<?php

declare(strict_types=1);

it('builds https urls when the reverse proxy terminates TLS', function (): void {
    $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'eslip.example.test',
        'X-Forwarded-Port' => '443',
    ])->get('/login')
        ->assertOk()
        ->assertSee('action="https://eslip.example.test/login"', escape: false)
        ->assertDontSee('http://eslip.example.test', escape: false);
});
