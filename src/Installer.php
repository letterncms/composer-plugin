<?php

declare(strict_types=1);

namespace Cms\ComposerPlugin;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use React\Promise\PromiseInterface;
use UnexpectedValueException;

/**
 * A plugin goes in plugins/<name>, a third party's in plugins/<vendor>-<name>. A site kit, a complete
 * site to start from, goes in kits/ the same way: the CMS copies from there when the kit is applied.
 */
final class Installer extends LibraryInstaller
{
    private const string VENDOR = 'letterncms';

    /** Package type => the folder beside vendor/ it lands in. */
    private const array FOLDERS = ['cms-plugin' => 'plugins', 'cms-kit' => 'kits'];

    /** What a kit's folder holds when Composer put it there: the only kits/ folder Composer may empty. */
    public const string MARKER = '.composer-kit';

    /** A kit's folder name is its handle in the CMS: lowercase letters, digits, - and _. */
    private const string HANDLE = '/^[a-z0-9][a-z0-9_-]{0,63}$/D';

    public function supports(string $packageType): bool
    {
        return isset(self::FOLDERS[$packageType]);
    }

    public function getInstallPath(PackageInterface $package): string
    {
        [$vendor, $name] = explode('/', $package->getPrettyName(), 2);
        $folder = $vendor === self::VENDOR ? $name : "{$vendor}-{$name}";

        if ($package->getType() === 'cms-kit' && preg_match(self::HANDLE, $folder) !== 1) {
            throw new UnexpectedValueException("{$package->getPrettyName()} can't be a site kit: its folder would be kits/{$folder}, and a kit's handle is lowercase letters, digits, - and _.");
        }

        return dirname($this->vendorDir ?? (string) $this->composer->getConfig()->get('vendor-dir')).'/'.self::FOLDERS[$package->getType()].'/'.$folder;
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): ?PromiseInterface
    {
        if ($package->getType() !== 'cms-kit') {
            return parent::install($repo, $package);
        }

        // Installing empties the folder first: a kit someone put there by hand, or the store did, stays theirs.
        $path = $this->getInstallPath($package);

        if (is_dir($path) && ! is_file($path.'/'.self::MARKER) && (scandir($path) ?: []) !== ['.', '..']) {
            throw new UnexpectedValueException("kits/".basename($path)." is already there, and Composer didn't put it there. Move it away, then require {$package->getPrettyName()} again.");
        }

        return $this->marked(parent::install($repo, $package), $path);
    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target): ?PromiseInterface
    {
        $promise = parent::update($repo, $initial, $target);

        return $target->getType() === 'cms-kit' ? $this->marked($promise, $this->getInstallPath($target)) : $promise;
    }

    /** The marker goes in once the files are there. */
    private function marked(?PromiseInterface $promise, string $path): ?PromiseInterface
    {
        $mark = static function () use ($path): void {
            is_dir($path) && file_put_contents($path.'/'.self::MARKER, "Composer put this kit here. composer remove takes it away.\n");
        };

        if ($promise === null) {
            $mark();

            return null;
        }

        return $promise->then($mark);
    }
}
