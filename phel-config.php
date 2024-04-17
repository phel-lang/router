<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return (new PhelConfig())
    ->setSrcDirs(['src'])
    ->setTestDirs(['tests'])
    ->setIgnoreWhenBuilding([
        'performance.phel',
        'local.phel'
    ]);
