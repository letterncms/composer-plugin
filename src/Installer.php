<?php

declare(strict_types=1);

namespace Cms\ComposerPlugin;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;

/** A plugin goes in plugins/<name>, a third party's in plugins/<vendor>-<name>. */
final class Installer extends LibraryInstaller
{
    private const string VENDOR = 'letterncms';

    public function supports(string $packageType): bool
    {
        return $packageType === 'cms-plugin';
    }

    public function getInstallPath(PackageInterface $package): string
    {
        [$vendor, $name] = explode('/', $package->getPrettyName(), 2);

        return dirname($this->vendorDir ?? (string) $this->composer->getConfig()->get('vendor-dir')).'/plugins/'.($vendor === self::VENDOR ? $name : "{$vendor}-{$name}");
    }
}
