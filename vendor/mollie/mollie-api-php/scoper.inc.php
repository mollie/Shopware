<?php 

// removed strict types declaration, strict types are not supported in shopware plugins

// scoper.inc.php

use Isolated\Symfony\Component\Finder\Finder;

return [
    'finders' => [],                        // Finder[]
    'patchers' => [],                       // callable[]
    'whitelist' => [
        'Mollie\\Api\\*',
    ],
];