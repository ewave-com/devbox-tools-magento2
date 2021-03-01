<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

namespace MagentoDevBox\Command\Pool;

use CoreDevBoxScripts\Command\CommandAbstract;
use CoreDevBoxScripts\Library\Registry;
use CoreDevBoxScripts\Library\EnvConfig;
use MagentoDevBox\Command\Options\Magento as MagentoOptions;
use MagentoDevBox\Command\Options\Redis as RedisOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for Redis setup
 */
class MagentoSetupRedis extends CommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('magento2:setup:redis')
            ->setDescription('Setup Redis for Magento')
            ->setHelp('This command allows you to setup Redis for Magento.');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->commandTitle($io, 'Redis Configuration');

        $mPath = EnvConfig::getValue('WEBSITE_APPLICATION_ROOT') ?: EnvConfig::getValue('WEBSITE_DOCUMENT_ROOT');
        $destinationMagentoPath = $mPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'etc';
        if (!$destinationMagentoPath) {
            $destinationMagentoPath = $this->requestOption(MagentoOptions::PATH, $input, $output, true);
        }

        $projectName = EnvConfig::getValue('PROJECT_NAME');
        $redisContainer = EnvConfig::getValue('CONTAINER_REDIS_NAME');

        $host = $projectName . '_' . $redisContainer;
        $configPath = sprintf('%s/env.php', $destinationMagentoPath);
        if (file_exists($configPath)) {
            $config = include $configPath;
        } else {
            $io->warning('Magento is not installed!');
            return false;
        }

        if ($this->requestOption(RedisOptions::SESSION_SETUP, $input, $output, true)) {
            $config['session'] = [
                'save' => 'redis',
                'redis' => [
                    'host' => $host,
                    'port' => '6379',
                    'password' => '',
                    'timeout' => '2.5',
                    'persistent_identifier' => '',
                    'database' => '0',
                    'compression_threshold' => '2048',
                    'compression_library' => 'gzip',
                    'log_level' => '1',
                    'max_concurrency' => '6',
                    'break_after_frontend' => '5',
                    'break_after_adminhtml' => '30',
                    'first_lifetime' => '600',
                    'bot_first_lifetime' => '60',
                    'bot_lifetime' => '7200',
                    'disable_locking' => '0',
                    'min_lifetime' => '60',
                    'max_lifetime' => '2592000'
                ]
            ];
        } else {
            $config['session'] = ['save' => 'files'];
        }

        if ($this->requestOption(RedisOptions::CACHE_SETUP, $input, $output, true)) {
            $config['cache']['frontend']['default'] = [
                'backend' => 'Cm_Cache_Backend_Redis',
                'backend_options' => [
                    'server' => $host,
                    'port' => '6379'
                ]
            ];
        } else {
            unset($config['cache']['frontend']['default']);
        }

        if (!Registry::get(RedisOptions::FPC_INSTALLED)
            && $this->requestOption(RedisOptions::FPC_SETUP, $input, $output, true)
        ) {
            $config['cache']['frontend']['page_cache'] = [
                'backend' => 'Cm_Cache_Backend_Redis',
                'backend_options' => [
                    'server' => $host,
                    'port' => '6379',
                    'database' => '1',
                    'compress_data' => '0'
                ]
            ];

            Registry::set(RedisOptions::FPC_INSTALLED, true);
        } else {
            unset($config['cache']['frontend']['page_cache']);
        }

        file_put_contents($configPath, sprintf("<?php\n return %s;", var_export($config, true)));

        $output->writeln('<info>Cache clean...</info>');
        $this->executeCommands(sprintf('cd %s && php bin/magento cache:clean', $mPath), $output);

        /* use $e for Exception variable */
        if (!isset($e)) {
            $io->success('Redis configuration has been updated');
            return true;
        } else {
            $io->warning('Some issues appeared during redis configuration');
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionsConfig()
    {
        return [
            MagentoOptions::PATH => MagentoOptions::get(MagentoOptions::PATH),
            RedisOptions::FPC_SETUP => RedisOptions::get(RedisOptions::FPC_SETUP),
            RedisOptions::CACHE_SETUP => RedisOptions::get(RedisOptions::CACHE_SETUP),
            RedisOptions::SESSION_SETUP => RedisOptions::get(RedisOptions::SESSION_SETUP)
        ];
    }
}
