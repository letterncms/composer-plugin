<?php

declare(strict_types=1);

namespace Cms\ComposerPlugin\Tests;

use Cms\ComposerPlugin\Installer;
use Cms\ComposerPlugin\Plugin;
use Composer\IO\NullIO;
use Composer\Package\Package;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class PluginTest extends SiteCase
{
    private function stubs(string $event = ScriptEvents::POST_INSTALL_CMD): void
    {
        (new Plugin)->stubs(new Event($event, $this->composer(), new NullIO));
    }

    public function test_it_registers_the_installer_for_plugins(): void
    {
        $composer = $this->composer();
        (new Plugin)->activate($composer, new NullIO);

        $package = new Package('letterncms/forms', '1.0.0.0', '1.0.0');
        $package->setType('cms-plugin');

        $this->assertInstanceOf(Installer::class, $composer->getInstallationManager()->getInstaller('cms-plugin'));
        $this->assertSame($this->site.'/plugins/forms', $composer->getInstallationManager()->getInstallPath($package));
    }

    public function test_it_runs_after_install_and_after_update(): void
    {
        $this->assertSame([ScriptEvents::POST_INSTALL_CMD => 'stubs', ScriptEvents::POST_UPDATE_CMD => 'stubs'], Plugin::getSubscribedEvents());
    }

    public function test_a_new_site_gets_every_root_file(): void
    {
        foreach (['index.php', 'cms', 'setup.php', '.htaccess', 'public/.htaccess', 'storage/.htaccess'] as $file) {
            $this->stub($file, "stub of {$file}");
        }

        $this->stubs();

        foreach (['index.php', 'cms', 'setup.php', '.htaccess', 'public/.htaccess', 'storage/.htaccess'] as $file) {
            $this->assertSame("stub of {$file}", $this->read($file), $file);
        }
    }

    public function test_the_console_script_can_be_run(): void
    {
        $this->stub('cms', "#!/usr/bin/env php\n");

        $this->stubs();

        $this->assertSame(0o755, fileperms($this->site.'/cms') & 0o777);
    }

    public function test_the_front_controller_the_console_and_setup_follow_the_cms(): void
    {
        foreach (['index.php', 'cms', 'setup.php'] as $file) {
            $this->stub($file, 'new');
            $this->write($file, 'old');
        }

        $this->stubs(ScriptEvents::POST_UPDATE_CMD);

        foreach (['index.php', 'cms', 'setup.php'] as $file) {
            $this->assertSame('new', $this->read($file), $file);
        }
    }

    public function test_a_sites_own_htaccess_files_are_left_alone(): void
    {
        foreach (['.htaccess', 'public/.htaccess', 'storage/.htaccess'] as $file) {
            $this->stub($file, 'new');
            $this->write($file, 'the site changed this');
        }

        $this->stubs(ScriptEvents::POST_UPDATE_CMD);

        foreach (['.htaccess', 'public/.htaccess', 'storage/.htaccess'] as $file) {
            $this->assertSame('the site changed this', $this->read($file), $file);
        }
    }

    public function test_a_file_that_is_already_current_is_not_written_again(): void
    {
        $this->stub('index.php', 'same');
        $this->write('index.php', 'same');
        touch($this->site.'/index.php', 1_000_000);
        clearstatcache();

        $this->stubs();
        clearstatcache();

        $this->assertSame(1_000_000, filemtime($this->site.'/index.php'));
    }

    public function test_only_the_root_files_are_copied(): void
    {
        $this->stub('index.php', 'index');
        $this->stub('README.md', 'readme');
        $this->stub('composer.json', '{}');
        $this->stub('gitignore', '/vendor/');

        $this->stubs();

        $this->assertSame('index', $this->read('index.php'));
        $this->assertNull($this->read('README.md'));
        $this->assertNull($this->read('composer.json'));
        $this->assertNull($this->read('gitignore'));
    }

    public function test_a_stub_the_cms_does_not_ship_is_skipped(): void
    {
        $this->stub('index.php', 'index');

        $this->stubs();

        $this->assertNull($this->read('setup.php'));
        $this->assertNull($this->read('public/.htaccess'));
    }

    public function test_without_the_cms_nothing_happens(): void
    {
        $this->write('index.php', 'mine');

        $this->stubs();

        $this->assertSame('mine', $this->read('index.php'));
        $this->assertSame(['index.php', 'vendor'], array_values(array_diff(scandir($this->site) ?: [], ['.', '..'])));
    }
}
