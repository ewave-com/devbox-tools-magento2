<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

namespace MagentoDevBox\Command\Pool;

use CoreDevBoxScripts\Command\CommandAbstract;
use CoreDevBoxScripts\Library\EnvConfig;
use MagentoDevBox\Command\Options\Magento as MagentoOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for downloading Magento sources
 */
class MagentoSetupConfigs extends CommandAbstract
{
    /**
     * @var string
     */
    protected $configFile = '';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->configFile = EnvConfig::getValue('PROJECT_CONFIGURATION_FILE');
        $this->setName('magento2:setup:configs')
            ->setDescription(
                'Download Magento Configs Files [' . $this->configFile . ' file will be used as configuration]'
            )
            ->setHelp(
                'Download Magento Configs Files [' . $this->configFile . ' file will be used as configuration]'
            );

        $this->questionOnRepeat = 'Try to update configs again?';

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->executeRepeatedly('updateConfigs', $input, $output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function updateConfigs(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->commandTitle($io, 'Configuration files sync.');

        $useExistingSources = $this->requestOption(MagentoOptions::M_CONFIGS_REUSE, $input, $output, true);
        if (!$useExistingSources) {
            $output->writeln('<comment>Skipping this step.</comment>');
            return true;
        }

        $this->executeWrappedCommands(
            [
                'core:remote-files:download',
                'core:setup:permissions'
            ],
            $input,
            $output
        );

        $this->updateConfigsFiles($io, $input, $output);
        return true;
    }

    /**
     * @param SymfonyStyle $io
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    public function updateConfigsFiles($io, $input, $output)
    {
        $projectName = EnvConfig::getValue('PROJECT_NAME');
        $mysqlHost = EnvConfig::getValue('CONTAINER_MYSQL_NAME');
        $mysqlDbName = EnvConfig::getValue('CONTAINER_MYSQL_DB_NAME');
        $mysqlRootPasword = EnvConfig::getValue('CONTAINER_MYSQL_ROOT_PASS');
        $mysqlHost = $projectName . '_' . $mysqlHost;

        $mPath = EnvConfig::getValue('WEBSITE_DOCUMENT_ROOT');
        $destinationMagentoPath = $mPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'etc';
        if (!$destinationMagentoPath) {
            $destinationMagentoPath = $this->requestOption(MagentoOptions::PATH, $input, $output, true);
        }

        $configPath = sprintf('%s/env.php', $destinationMagentoPath);
        if (file_exists($configPath)) {
            $config = include $configPath;
        } else {
            $config = [
                'backend' => [
                    'frontName' => 'admin'
                ],
                'db' => [
                    'connection' => [
                        'indexer' => [
                            'model' => 'mysql4',
                            'engine' => 'innodb',
                            'initStatements' => 'SET NAMES utf8;',
                            'active' => '1',
                            'persistent' => null
                        ],
                        'default' => [
                            'model' => 'mysql4',
                            'engine' => 'innodb',
                            'initStatements' => 'SET NAMES utf8;',
                            'active' => '1'
                        ]
                    ],
                    'table_prefix' => ''
                ],
                'crypt' => [
                    'key' => '4cb80bdeaf528fe80e449f93598de7f7'
                ],
                'resource' => [
                    'default_setup' => [
                        'connection' => 'default'
                    ]
                ],
                'x-frame-options' => 'SAMEORIGIN',
                'MAGE_MODE' => 'developer',
                'session' => [
                    'save' => 'files'
                ],
                'cache_types' => [
                    'config' => 1,
                    'layout' => 1,
                    'block_html' => 1,
                    'collections' => 1,
                    'reflection' => 1,
                    'db_ddl' => 1,
                    'compiled_config' => 1,
                    'eav' => 1,
                    'customer_notification' => 1,
                    'config_integration' => 1,
                    'config_integration_api' => 1,
                    'target_rule' => 1,
                    'full_page' => 1,
                    'config_webservice' => 1,
                    'translate' => 1,
                ],
                'install' => [
                    'date' => 'Wed, 05 Dec 2018 11:25:22 +0000'
                ]
            ];
        }

        $config['db']['connection']['default']['host'] = $mysqlHost;
        $config['db']['connection']['default']['dbname'] = $mysqlDbName;
        $config['db']['connection']['default']['username'] = 'root';
        $config['db']['connection']['default']['password'] = $mysqlRootPasword;

        $config['db']['connection']['indexer']['host'] = $mysqlHost;
        $config['db']['connection']['indexer']['dbname'] = $mysqlDbName;
        $config['db']['connection']['indexer']['username'] = 'root';
        $config['db']['connection']['indexer']['password'] = $mysqlRootPasword;

        file_put_contents($configPath, sprintf("<?php\n return %s;", var_export($config, true)));

        if (!isset($e)) {
            $io->success('Configs have been copied');
            return true;
        } else {
            $io->warning('Some issues appeared during configs updating');
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionsConfig()
    {
        return [
            MagentoOptions::M_CONFIGS_REUSE => MagentoOptions::get(MagentoOptions::M_CONFIGS_REUSE),
            MagentoOptions::PATH => MagentoOptions::get(MagentoOptions::PATH),
        ];
    }
}
