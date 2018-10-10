<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Symfony\Component\Process\Process;

class PublishPlugin implements PluginInterface, EventSubscriberInterface
{
    // Extra key to point to publish command handler
    public const PUBLISH_CMD = 'publish-cmd';

    // Extra key to declare files and directories to publish
    public const PUBLISH_KEY = 'publish';

    /** @var Composer instance */
    private $composer;

    /** @var IOInterface */
    private $io;

    /**
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * This is the main function.
     *
     * @param Event $event
     */
    public function publishFiles(Event $event)
    {
        $cmd = $event->getComposer()->getPackage()->getExtra()[self::PUBLISH_CMD] ?? null;

        if (empty($cmd)) {
            $this->io->writeError('<comment>missing `publish-cmd` handler</comment>');
            return;
        }

        $this->io->write("<info>Publishing package files using `<comment>{$cmd}</comment>`</info>");

        $packages = $event->getComposer()->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $installationManager = $event->getComposer()->getInstallationManager();

        foreach ($packages as $package) {
            $publish = $package->getExtra()[self::PUBLISH_KEY] ?? null;

            if (empty($publish)) {
                continue;
            }

            foreach ($publish as $data => $options) {
                $this->publish($cmd, $installationManager->getInstallPath($package), $data, $options);
            }
        }
    }

    /**
     * @param string $cmd
     * @param string $path
     * @param string $data
     * @param string $type
     */
    protected function publish(string $cmd, string $path, string $data, string $type)
    {
        $publish = Publish::parse($path, $data, $type);

        $p = new Process(join(' ', [
            $cmd,
            escapeshellarg($publish->getType()),
            escapeshellarg($publish->getTarget()),
            escapeshellarg($publish->getSource()),
            escapeshellarg($publish->getMode())
        ]));
        $p->run();

        if (!$p->isSuccessful()) {
            $this->io->writeError($p->getErrorOutput());
            return;
        }

        if ($this->io->isVerbose()) {
            $this->io->write($p->getOutput());
        }
    }

    /**
     * @return array list of events
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'post-autoload-dump' => [['publishFiles', 0]],
        ];
    }
}