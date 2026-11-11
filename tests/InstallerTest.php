<?php

declare(strict_types=1);

namespace Cms\ComposerPlugin\Tests;

use Cms\ComposerPlugin\Installer;
use Composer\IO\NullIO;
use Composer\Package\Package;

final class InstallerTest extends SiteCase
{
    private function installer(): Installer
    {
        return new Installer(new NullIO, $this->composer());
    }

    private function package(string $name, string $type = 'cms-plugin'): Package
    {
        $package = new Package($name, '1.0.0.0', '1.0.0');
        $package->setType($type);

        return $package;
    }

    public function test_it_takes_plugins_and_nothing_else(): void
    {
        $installer = $this->installer();

        $this->assertTrue($installer->supports('cms-plugin'));
        $this->assertFalse($installer->supports('library'));
        $this->assertFalse($installer->supports('composer-plugin'));
        $this->assertFalse($installer->supports('cms-theme'));
    }

    public function test_a_plugin_of_ours_goes_in_plugins_under_its_name(): void
    {
        $this->assertSame($this->site.'/plugins/forms', $this->installer()->getInstallPath($this->package('letterncms/forms')));
    }

    public function test_a_third_partys_plugin_keeps_its_vendor_in_the_folder_name(): void
    {
        $this->assertSame($this->site.'/plugins/acme-reading-time', $this->installer()->getInstallPath($this->package('acme/reading-time')));
    }

    public function test_two_vendors_with_the_same_plugin_name_do_not_share_a_folder(): void
    {
        $installer = $this->installer();

        $this->assertNotSame($installer->getInstallPath($this->package('acme/forms')), $installer->getInstallPath($this->package('letterncms/forms')));
        $this->assertNotSame($installer->getInstallPath($this->package('acme/forms')), $installer->getInstallPath($this->package('other/forms')));
    }

    public function test_plugins_sit_beside_vendor_wherever_vendor_is(): void
    {
        $composer = $this->composer();
        $composer->getConfig()->merge(['config' => ['vendor-dir' => $this->site.'/runtime/1.2.3/vendor']]);

        $this->assertSame($this->site.'/runtime/1.2.3/plugins/seo', (new Installer(new NullIO, $composer))->getInstallPath($this->package('letterncms/seo')));
    }
}
