<?php

declare(strict_types=1);

arch('application code uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging statements')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();
