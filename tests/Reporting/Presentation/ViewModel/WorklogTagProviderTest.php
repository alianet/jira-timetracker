<?php

declare(strict_types=1);

namespace Tests\Reporting\Presentation\ViewModel;

use App\Composition\ContainerFactory;
use App\Reporting\Presentation\ViewModel\WorklogTagProvider;
use Tests\TestCase;

final class WorklogTagProviderTest extends TestCase
{
    public function testReadsConfiguredTagsAndRemovesWhitespaceDuplicatesAndEmptyEntries(): void
    {
        $config = $this->createConfig(['WORKLOG_TAGS' => '" Analysis, ,Code review,Analysis,0, "']);

        self::assertSame(['Analysis', 'Code review', '0'], ContainerFactory::worklogTagProvider($config)->all());
    }

    public function testMissingOrEmptyConfigurationProvidesNoTags(): void
    {
        self::assertSame([], ContainerFactory::worklogTagProvider($this->createConfig([]))->all());
        self::assertSame([], ContainerFactory::worklogTagProvider($this->createConfig(['WORKLOG_TAGS' => '']))->all());
        self::assertSame([], new WorklogTagProvider(' , , ')->all());
    }
}
