<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests;

use App\Kernel\Config\Config;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param array<string, string> $values
     */
    protected function createConfig(array $values): Config
    {
        $directory = $this->createTempDirectory();
        $this->writeFile($directory, '.env', $this->envContents($values));

        return Config::fromDirectory($directory);
    }

    /**
     * @param array<string, string> $values
     */
    protected function createTempEnvFile(array $values): string
    {
        $directory = $this->createTempDirectory();
        $this->writeFile($directory, '.env', $this->envContents($values));

        return $directory;
    }

    protected function createTempDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/jira-timetracker-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o777, true);

        return $directory;
    }

    protected function writeFile(string $directory, string $relativePath, string $contents): void
    {
        $path = rtrim($directory, '/') . '/' . ltrim($relativePath, '/');
        $parentDirectory = dirname($path);

        if (!is_dir($parentDirectory)) {
            mkdir($parentDirectory, 0o777, true);
        }

        file_put_contents($path, $contents);
    }

    /**
     * @param array<string, string> $values
     */
    private function envContents(array $values): string
    {
        $lines = [];
        foreach ($values as $name => $value) {
            $lines[] = $name . '=' . $value;
        }

        return implode("\n", $lines) . "\n";
    }
}
