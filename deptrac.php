<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $layer = static fn(string $name, string $namespace): Layer => Layer::withName($name)->collectors(
        ClassLikeConfig::create('^App.' . str_replace('\\', '.', $namespace) . '.'),
    );

    $identityApplication = $layer('Identity Application', 'Identity\\Application');
    $identityInfrastructure = $layer('Identity Infrastructure', 'Identity\\Infrastructure');
    $identityPresentation = $layer('Identity Presentation', 'Identity\\Presentation');
    $reportingDomain = $layer('Reporting Domain', 'Reporting\\Domain');
    $reportingApplication = $layer('Reporting Application', 'Reporting\\Application');
    $reportingInfrastructure = $layer('Reporting Infrastructure', 'Reporting\\Infrastructure');
    $reportingPresentation = $layer('Reporting Presentation', 'Reporting\\Presentation');
    $timeTrackingDomain = $layer('TimeTracking Domain', 'TimeTracking\\Domain');
    $timeTrackingApplication = $layer('TimeTracking Application', 'TimeTracking\\Application');
    $timeTrackingInfrastructure = $layer('TimeTracking Infrastructure', 'TimeTracking\\Infrastructure');
    $timeTrackingPresentation = $layer('TimeTracking Presentation', 'TimeTracking\\Presentation');
    $sharedDomain = $layer('Shared Domain', 'Shared\\Domain');
    $sharedInfrastructure = $layer('Shared Infrastructure', 'Shared\\Infrastructure');
    $kernelConfig = $layer('Kernel Config', 'Kernel\\Config');
    $kernelException = $layer('Kernel Exception', 'Kernel\\Exception');
    $kernelInfrastructure = $layer('Kernel Infrastructure', 'Kernel\\Infrastructure');
    $kernelPresentation = $layer('Kernel Presentation', 'Kernel\\Presentation');
    $kernelSupport = $layer('Kernel Support', 'Kernel\\Support');
    $bootstrap = Layer::withName('Composition Root')->collectors(ClassLikeConfig::create('^App.Bootstrap$'));

    $config
        ->paths('./src')
        ->cacheFile('/tmp/time-tracker-deptrac.cache')
        ->layers(
            $identityApplication,
            $identityInfrastructure,
            $identityPresentation,
            $reportingDomain,
            $reportingApplication,
            $reportingInfrastructure,
            $reportingPresentation,
            $timeTrackingDomain,
            $timeTrackingApplication,
            $timeTrackingInfrastructure,
            $timeTrackingPresentation,
            $sharedDomain,
            $sharedInfrastructure,
            $kernelConfig,
            $kernelException,
            $kernelInfrastructure,
            $kernelPresentation,
            $kernelSupport,
            $bootstrap,
        )
        ->rulesets(
            Ruleset::forLayer($identityApplication)->accesses(
                $kernelException,
            ),
            Ruleset::forLayer($identityInfrastructure)->accesses(
                $identityApplication,
                $identityPresentation,
                $kernelConfig,
                $kernelException,
                $kernelSupport,
                $sharedInfrastructure,
            ),
            Ruleset::forLayer($identityPresentation)->accesses(
                $identityApplication,
                $kernelException,
                $kernelPresentation,
                $kernelSupport,
            ),
            Ruleset::forLayer($reportingDomain)->accesses($sharedDomain),
            Ruleset::forLayer($reportingApplication)->accesses($reportingDomain),
            Ruleset::forLayer($reportingInfrastructure)->accesses(
                $reportingDomain,
                $reportingApplication,
                $kernelConfig,
                $kernelException,
                $kernelSupport,
                $sharedInfrastructure,
            ),
            Ruleset::forLayer($reportingPresentation)->accesses(
                $reportingDomain,
                $reportingApplication,
                $kernelException,
                $kernelPresentation,
                $kernelSupport,
            ),
            Ruleset::forLayer($timeTrackingDomain),
            Ruleset::forLayer($timeTrackingApplication)->accesses(
                $timeTrackingDomain,
                $kernelException,
            ),
            Ruleset::forLayer($timeTrackingInfrastructure)->accesses(
                $timeTrackingDomain,
                $timeTrackingApplication,
                $kernelException,
                $kernelSupport,
                $sharedInfrastructure,
            ),
            Ruleset::forLayer($timeTrackingPresentation)->accesses(
                $timeTrackingDomain,
                $timeTrackingApplication,
                $kernelException,
                $kernelPresentation,
                $kernelSupport,
            ),
            Ruleset::forLayer($sharedDomain),
            Ruleset::forLayer($sharedInfrastructure)->accesses($sharedDomain),
            Ruleset::forLayer($kernelConfig)->accesses($kernelException),
            Ruleset::forLayer($kernelException),
            Ruleset::forLayer($kernelInfrastructure),
            Ruleset::forLayer($kernelPresentation)->accesses(
                $kernelConfig,
                $kernelException,
                $kernelSupport,
            ),
            Ruleset::forLayer($kernelSupport),
            Ruleset::forLayer($bootstrap)->accesses(
                $identityApplication,
                $identityInfrastructure,
                $identityPresentation,
                $reportingApplication,
                $reportingDomain,
                $reportingInfrastructure,
                $reportingPresentation,
                $timeTrackingApplication,
                $timeTrackingDomain,
                $timeTrackingInfrastructure,
                $timeTrackingPresentation,
                $sharedInfrastructure,
                $kernelConfig,
                $kernelInfrastructure,
                $kernelPresentation,
            ),
        )
    ;
};
