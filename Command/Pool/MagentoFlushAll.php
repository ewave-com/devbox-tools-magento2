<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

namespace MagentoDevBox\Command\Pool;

use CoreDevBoxScripts\Command\CommandAbstract;
use CoreDevBoxScripts\Library\EnvConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class MagentoFlushAll
 * @package MagentoDevBox\Command\Pool
 */
class MagentoFlushAll extends CommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('magento2:flush-all')
            ->setDescription(
                'Flushing of Magento cache, static, generated code and Redis.'
            )
            ->setHelp(
                'This command allows you to flush everything: Magento cache, static, generated code and Redis.'
            );
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $destinationPath = EnvConfig::getValue('WEBSITE_DOCUMENT_ROOT');
        $io = new SymfonyStyle($input, $output);
        $this->commandTitle($io, 'Flushing of Magento cache, static, generated code and Redis');

        $command = "cd $destinationPath && sudo rm -rf var/cache/* var/page_cache/* pub/static/* generated/*";
        $this->executeCommands(
            $command,
            $output
        );
        $io->comment('Magento cache flushed');
        $io->comment('Magento static flushed');
        $io->comment('Magento generated code flushed');

        /*
         * Redis flushing
         */
        $redisEnable = EnvConfig::getValue('REDIS_ENABLE');
        if ($redisEnable == 'yes') {
            $redisContainerName = EnvConfig::getValue('PROJECT_NAME') . '_'
                . EnvConfig::getValue('CONTAINER_REDIS_NAME');
            $command = "redis-cli -h $redisContainerName ping";
            $answer = exec($command);
            if ($answer == 'PONG') {
                $command = "redis-cli -h $redisContainerName flushall";
                $this->executeCommands(
                    $command,
                    $output
                );
                $io->comment('Redis flushed');
            }
        }

        /*
         * TODO: Add Varnish flushing.
         *
         * It must be implemented like the Redis flushing.
         * 1) Checking if the Varnish exists:
         * varnishadm ping
         * 2) If the answer is "PONG", run flushing of all Varnish cache:
         * varnishadm "ban req.url ~ /"
         *
         * PS: Add container name to commands.
         * Also, change command description and help after the Varnish flushing implementation.
         */

        return true;
    }
}
