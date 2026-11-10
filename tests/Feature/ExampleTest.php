<?php

declare(strict_types=1);

it('boots the application', function (): void {
    $this->get('/up')->assertOk();
});
