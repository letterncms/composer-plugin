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

    public function test_it_takes_plugins_and_sites_and_nothing_else(): void
    {
        $installer = $this->installer();

        $this->assertTrue($installer->supports('cms-plugin'));
        $this->assertTrue($installer->supports('cms-site'));
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

    public function test_a_site_goes_in_sites_named_the_same_way(): void
    {
        $this->assertSame($this->site.'/sites/studio', $this->installer()->getInstallPath($this->package('letterncms/studio', 'cms-site')));
        $this->assertSame($this->site.'/sites/acme-studio', $this->installer()->getInstallPath($this->package('acme/studio', 'cms-site')));
    }

    public function test_a_site_whose_folder_would_not_be_a_handle_is_refused(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('sites/acme-Studio.v2');
        $this->installer()->getInstallPath($this->package('acme/Studio.v2', 'cms-site'));
    }

    public function test_a_site_folder_composer_did_not_put_there_is_never_emptied(): void
    {
        $this->write('sites/studio/site.json', '{"handle": "studio"}');

        try {
            $this->installer()->install(new \Composer\Repository\InstalledArrayRepository, $this->package('letterncms/studio', 'cms-site'));
            $this->fail('a site someone else put there was replaced');
        } catch (\UnexpectedValueException $e) {
            $this->assertStringContainsString("sites/studio is already there, and Composer didn't put it there", $e->getMessage());
        }

        $this->assertSame('{"handle": "studio"}', $this->read('sites/studio/site.json'));
    }

    public function test_a_site_composer_links_from_a_path_repository_gets_no_mark_in_its_source(): void
    {
        $this->write('source/studio/site.json', '{"handle": "studio"}');
        $this->write('sites/copied/site.json', '{"handle": "copied"}');
        symlink($this->site.'/source/studio', $this->site.'/sites/studio');
        $marked = new \ReflectionMethod(Installer::class, 'marked');

        $marked->invoke($this->installer(), null, $this->site.'/sites/studio');
        $marked->invoke($this->installer(), null, $this->site.'/sites/copied');

        $this->assertFileDoesNotExist($this->site.'/source/studio/'.Installer::MARKER);
        $this->assertFileExists($this->site.'/sites/copied/'.Installer::MARKER);
    }

    public function test_a_site_and_a_plugin_of_one_name_do_not_share_a_folder(): void
    {
        $installer = $this->installer();

        $this->assertNotSame($installer->getInstallPath($this->package('letterncms/forms', 'cms-site')), $installer->getInstallPath($this->package('letterncms/forms')));
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
