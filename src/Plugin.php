<?php

declare(strict_types=1);

namespace Cms\ComposerPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/** Composer's half of "one site, however it got here": the same folders the store's installs use. */
final class Plugin implements EventSubscriberInterface, PluginInterface
{
    /** Replaced on every install: nobody edits these. */
    private const array ALWAYS = ['index.php', 'cms', 'setup.php'];

    /** Copied once: a site may have reasons to change them. */
    private const array ONCE = ['.htaccess', 'public/.htaccess', 'storage/.htaccess'];

    public function activate(Composer $composer, IOInterface $io): void
    {
        $composer->getInstallationManager()->addInstaller(new Installer($io, $composer));
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::POST_INSTALL_CMD => 'stubs', ScriptEvents::POST_UPDATE_CMD => 'stubs'];
    }

    public function stubs(Event $event): void
    {
        $vendor = (string) $event->getComposer()->getConfig()->get('vendor-dir');
        $stubs = $vendor.'/letterncms/cms/stubs/site';
        $site = dirname($vendor);

        if (! is_dir($stubs)) {
            return;
        }

        foreach ([...self::ALWAYS, ...self::ONCE] as $file) {
            $target = $site.'/'.$file;

            if (! is_file($stubs.'/'.$file) || (is_file($target) && (in_array($file, self::ONCE, true) || hash_file('sha256', $target) === hash_file('sha256', $stubs.'/'.$file)))) {
                continue;
            }

            is_dir(dirname($target)) || mkdir(dirname($target), 0o775, true);
            copy($stubs.'/'.$file, $target);

            if ($file === 'cms') {
                chmod($target, 0o755);
            }
        }
    }
}
