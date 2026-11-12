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

    public function test_it_takes_plugins_and_kits_and_nothing_else(): void
    {
        $installer = $this->installer();

        $this->assertTrue($installer->supports('cms-plugin'));
        $this->assertTrue($installer->supports('cms-kit'));
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

    public function test_a_kit_goes_in_kits_named_the_same_way(): void
    {
        $this->assertSame($this->site.'/kits/fathomline', $this->installer()->getInstallPath($this->package('letterncms/fathomline', 'cms-kit')));
        $this->assertSame($this->site.'/kits/acme-studio', $this->installer()->getInstallPath($this->package('acme/studio', 'cms-kit')));
    }

    public function test_a_kit_whose_folder_would_not_be_a_handle_is_refused(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('kits/acme-Studio.v2');
        $this->installer()->getInstallPath($this->package('acme/Studio.v2', 'cms-kit'));
    }

    public function test_a_kit_folder_composer_did_not_put_there_is_never_emptied(): void
    {
        $this->write('kits/fathomline/kit.json', '{"handle": "fathomline"}');

        try {
            $this->installer()->install(new \Composer\Repository\InstalledArrayRepository, $this->package('letterncms/fathomline', 'cms-kit'));
            $this->fail('a kit someone else put there was replaced');
        } catch (\UnexpectedValueException $e) {
            $this->assertStringContainsString("kits/fathomline is already there, and Composer didn't put it there", $e->getMessage());
        }

        $this->assertSame('{"handle": "fathomline"}', $this->read('kits/fathomline/kit.json'));
    }

    public function test_a_kit_composer_links_from_a_path_repository_gets_no_mark_in_its_source(): void
    {
        $this->write('source/fathomline/kit.json', '{"handle": "fathomline"}');
        $this->write('kits/copied/kit.json', '{"handle": "copied"}');
        symlink($this->site.'/source/fathomline', $this->site.'/kits/fathomline');
        $marked = new \ReflectionMethod(Installer::class, 'marked');

        $marked->invoke($this->installer(), null, $this->site.'/kits/fathomline');
        $marked->invoke($this->installer(), null, $this->site.'/kits/copied');

        $this->assertFileDoesNotExist($this->site.'/source/fathomline/'.Installer::MARKER);
        $this->assertFileExists($this->site.'/kits/copied/'.Installer::MARKER);
    }

    public function test_a_kit_and_a_plugin_of_one_name_do_not_share_a_folder(): void
    {
        $installer = $this->installer();

        $this->assertNotSame($installer->getInstallPath($this->package('letterncms/forms', 'cms-kit')), $installer->getInstallPath($this->package('letterncms/forms')));
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
