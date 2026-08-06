<?php

declare(strict_types=1);

/**
 * Defines environment-independent application configuration values.
 *
 * @return array{database_path: string}
 */
return [
    'database_path' => dirname(__DIR__) . '/storage/game-tracker.sqlite',
];
