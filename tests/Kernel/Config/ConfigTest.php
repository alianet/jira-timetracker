<?php

declare(strict_types=1);

namespace Tests\Kernel\Config;

use App\Kernel\Config\Config;
use App\Kernel\Exception\ApplicationConfigException;
use Tests\TestCase;

final class ConfigTest extends TestCase
{
    public function testBooleanUsesFalseAsTheDefault(): void
    {
        $config = $this->createConfig([]);

        self::assertInstanceOf(Config::class, $config);
        self::assertFalse($config->boolean('WORKLOG_DESCRIPTION_REQUIRED'));
    }

    public function testBooleanReadsTrue(): void
    {
        self::assertTrue($this->createConfig([
            'WORKLOG_DESCRIPTION_REQUIRED' => 'true',
        ])->boolean('WORKLOG_DESCRIPTION_REQUIRED'));
    }

    public function testBooleanRejectsInvalidValue(): void
    {
        $this->expectException(ApplicationConfigException::class);
        $this->expectExceptionMessage('Nieprawidłowa wartość WORKLOG_DESCRIPTION_REQUIRED. Oczekiwano: true albo false.');

        $this->createConfig([
            'WORKLOG_DESCRIPTION_REQUIRED' => 'sometimes',
        ])->boolean('WORKLOG_DESCRIPTION_REQUIRED');
    }
}
