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
use CoreDevBoxScripts\Command\Options\Db as DbOptions;
use MagentoDevBox\Command\Options\Magento as MagentoOptions;
use MagentoDevBox\Command\Options\RabbitMq as RabbitMqOptions;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for Magento installation
 */
class MagentoInstallNew extends CommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('magento2:install:new')
            ->setDescription(
                'Install Fresh magento2: [Code Download]-> INSTALLATION [FRESH DB]->[Magento finalisation]'
            )
            ->setHelp('This command allows you to install Magento.');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->commandTitle($io, 'Magento Setup: Fresh Installation');

        $this->executeWrappedCommands(
            [
                'core:setup:code',
                'core:setup:permissions'
            ],
            $input,
            $output
        );

        $mPath = EnvConfig::getValue('WEBSITE_APPLICATION_ROOT') ?: EnvConfig::getValue('WEBSITE_DOCUMENT_ROOT');

        $this->executeCommands(
            sprintf('cd %s && rm -rf var/* pub/static/* app/etc/env.php app/etc/config.php', $mPath),
            $output
        );

        $magentoHost = EnvConfig::getValue('WEBSITE_HOST_NAME');
        $magentoBackendPath = $this->requestOption(MagentoOptions::BACKEND_PATH, $input, $output);
        $magentoAdminUser = $this->requestOption(MagentoOptions::ADMIN_USER, $input, $output);
        $magentoAdminPassword = $this->requestOption(MagentoOptions::ADMIN_PASSWORD, $input, $output);

        $projectName = EnvConfig::getValue('PROJECT_NAME');

        $mysqlHost = EnvConfig::getValue('CONTAINER_MYSQL_NAME');
        $mysqlHost = $projectName . '_' . $mysqlHost;

        $dbName = EnvConfig::getValue('CONTAINER_MYSQL_DB_NAME');
        $dbUser = 'root';
        $dbPassword = EnvConfig::getValue('CONTAINER_MYSQL_ROOT_PASS');

        if (!$mysqlHost || !$dbName || !$dbPassword) {
            $output->writeln('<comment>Some of required data are missed</comment>');
            $output->writeln('<comment>Reply on:</comment>');

            $mysqlHost = $input->getOption(DbOptions::HOST);
            $dbUser = $input->getOption(DbOptions::USER);
            $dbPassword = $input->getOption(DbOptions::PASSWORD);
            $dbName = $input->getOption(DbOptions::NAME);
        }

        $headers = ['Parameter', 'Value'];
        $rows = [
            ['DB Name', $dbName],
            ['Admin Backend Path', $magentoBackendPath],
            ['Admin User', $magentoAdminUser],
            ['Admin Password', $magentoAdminPassword],
        ];

        $io->table($headers, $rows);

        $command = sprintf(
            'cd %s && php bin/magento setup:install'
            . ' --base-url=http://%s/ --db-host=%s --db-name=%s'
            . ' --db-user=%s --db-password=%s --admin-firstname=Magento --admin-lastname=User'
            . ' --admin-email=user@example.com --admin-user=%s --admin-password=%s'
            . ' --language=en_US --currency=USD --timezone=America/Chicago --use-rewrites=1'
            . ' --backend-frontname=%s',
            $mPath,
            $magentoHost,
            //$webserverHomePort,
            $mysqlHost,
            $dbName,
            $dbUser,
            $dbPassword,
            $magentoAdminUser,
            $magentoAdminPassword,
            $magentoBackendPath
        );

        if (!is_dir($mPath. DIRECTORY_SEPARATOR . 'pub/media')) {
            $command1 = 'mkdir ' . $mPath . DIRECTORY_SEPARATOR . 'pub/media';
            $this->executeCommands(
                [
                    $command1
                ],
                $output
            );
        }

        if (!is_dir($mPath. DIRECTORY_SEPARATOR . 'pub/static')) {
            $command1 = 'mkdir ' . $mPath . DIRECTORY_SEPARATOR . 'pub/static';
            $this->executeCommands(
                [
                    $command1
                ],
                $output
            );
        }

        if ($this->requestOption(RabbitMqOptions::SETUP, $input, $output)) {
            $amqpModuleExist = exec(
                sprintf('cd %s && php bin/magento module:status | grep Magento_Amqp', $mPath)
            );

            if ($amqpModuleExist) {
                $rabbitmqHost = $this->requestOption(RabbitMqOptions::HOST, $input, $output);
                $rabbitmqPort = $this->requestOption(RabbitMqOptions::PORT, $input, $output);

                $command .= sprintf(
                    ' --amqp-virtualhost=/ --amqp-host=%s --amqp-port=%s --amqp-user=guest --amqp-password=guest',
                    $rabbitmqHost,
                    $rabbitmqPort
                );
            }
        }

        $this->executeCommands($command, $output);

        $composerAuthSourcePath = '/home/magento2/.composer/auth.json';
        $composerHomePath = sprintf('%s/var/composer_home', $mPath);
        $composerAuthPath = sprintf('%s/auth.json', $composerHomePath);

        if (!file_exists($composerAuthPath) && file_exists($composerAuthSourcePath)) {
            if (!file_exists($composerHomePath)) {
                mkdir($composerHomePath, 0777, true);
            }

            copy($composerAuthSourcePath, $composerAuthPath);
        }

        if ($this->requestOption(MagentoOptions::SAMPLE_DATA_INSTALL, $input, $output)) {
            $this->executeCommands(
                [
                    sprintf('cd %s && php bin/magento sampledata:deploy', $mPath),
                    sprintf('cd %s && php bin/magento setup:upgrade', $mPath)
                ],
                $output
            );
        }

        Registry::setData(
            [
                MagentoOptions::HOST => $magentoHost,
                MagentoOptions::BACKEND_PATH => $magentoBackendPath,
                MagentoOptions::ADMIN_USER => $magentoAdminUser,
                MagentoOptions::ADMIN_PASSWORD => $magentoAdminPassword
            ]
        );

        $this->executeWrappedCommands(
            [
                'magento2:setup:finalize',
                'magento2:setup:redis',
                'core:setup:permissions'
            ],
            $input,
            $output
        );

        /* use $e for Exception variable */
        if (!isset($e)) {
            $io->success('Magento has been installed');
            return true;
        } else {
            $io->warning('Some issues appeared during magento installation');
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
            MagentoOptions::BACKEND_PATH => MagentoOptions::get(MagentoOptions::BACKEND_PATH),
            MagentoOptions::ADMIN_USER => MagentoOptions::get(MagentoOptions::ADMIN_USER),
            MagentoOptions::ADMIN_PASSWORD => MagentoOptions::get(MagentoOptions::ADMIN_PASSWORD),
            MagentoOptions::SAMPLE_DATA_INSTALL => MagentoOptions::get(MagentoOptions::SAMPLE_DATA_INSTALL),
            RabbitMqOptions::SETUP => RabbitMqOptions::get(RabbitMqOptions::SETUP),
            RabbitMqOptions::HOST => RabbitMqOptions::get(RabbitMqOptions::HOST),
            RabbitMqOptions::PORT => RabbitMqOptions::get(RabbitMqOptions::PORT)
        ];
    }
}
