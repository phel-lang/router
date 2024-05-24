<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return (new PhelConfig())
    ->setIgnoreWhenBuilding([
        'performance.phel',
        'local.phel'
    ])
;
