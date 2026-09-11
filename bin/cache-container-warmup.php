<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

use App\Composition\ContainerFactory;

$rootDirectory = dirname(__DIR__);

require $rootDirectory . '/vendor/autoload.php';

new ContainerFactory($rootDirectory . '/var/cache/container', false)->warmUp();

fwrite(STDOUT, "Container cache warmed up.\n");
