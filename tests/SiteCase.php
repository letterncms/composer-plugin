<?php

declare(strict_types=1);

namespace Cms\ComposerPlugin\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\Installer\InstallationManager;
use Composer\IO\NullIO;
use Composer\Util\Filesystem;
use Composer\Util\HttpDownloader;
use Composer\Util\Loop;
use PHPUnit\Framework\TestCase;

/** A throw-away site folder, and a Composer that thinks it is working in it. */
abstract class SiteCase extends TestCase
{
    protected string $site;

    protected function setUp(): void
    {
        $this->site = sys_get_temp_dir().'/cms-composer-plugin-'.bin2hex(random_bytes(6));
        mkdir($this->site.'/vendor', 0o777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->removeDirectory($this->site);
    }

    protected function composer(): Composer
    {
        $config = new Config(false, $this->site);
        $config->merge(['config' => ['vendor-dir' => $this->site.'/vendor']]);
        $io = new NullIO;

        $composer = new Composer;
        $composer->setConfig($config);
        $composer->setDownloadManager(new DownloadManager($io));
        $composer->setInstallationManager(new InstallationManager(new Loop(new HttpDownloader($io, $config)), $io));

        return $composer;
    }

    /** What the CMS ships for a site's root, as it sits in vendor/. */
    protected function stub(string $file, string $contents): void
    {
        $this->write('vendor/letterncms/cms/stubs/site/'.$file, $contents);
    }

    protected function write(string $file, string $contents): void
    {
        is_dir(dirname($this->site.'/'.$file)) || mkdir(dirname($this->site.'/'.$file), 0o777, true);
        file_put_contents($this->site.'/'.$file, $contents);
    }

    protected function read(string $file): ?string
    {
        return is_file($this->site.'/'.$file) ? (string) file_get_contents($this->site.'/'.$file) : null;
    }
}
