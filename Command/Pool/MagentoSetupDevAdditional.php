<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

namespace MagentoDevBox\Command\Pool;

use CoreDevBoxScripts\Command\CommandAbstract;
use CoreDevBoxScripts\Library\Db;
use CoreDevBoxScripts\Library\EnvConfig;
use CoreDevBoxScripts\Command\Options\Db as DbOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for Magento final steps
 */
class MagentoSetupDevAdditional extends CommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('magento2:setup:common-commands')
            ->setDescription('Create admin, disable merge, turn off js/css sign etc.')
            ->setHelp('Create admin, disable merge, turn off js/css sign etc.');

        $this->questionOnRepeat = 'Try to run again?';

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->executeRepeatedly('doDevActions', $input, $output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function doDevActions(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->commandTitle($io, 'Do Dev Actions');

        $magentoHost = EnvConfig::getValue('WEBSITE_HOST_NAME');
        $magentoProtocol = EnvConfig::getValue('WEBSITE_PROTOCOL');
        $projectName = EnvConfig::getValue('PROJECT_NAME');
        $mysqlHost = EnvConfig::getValue('CONTAINER_MYSQL_NAME');
        $dbPassword = EnvConfig::getValue('CONTAINER_MYSQL_ROOT_PASS');
        $dbName = EnvConfig::getValue('CONTAINER_MYSQL_DB_NAME');
        $dbUser = 'root';
        $mysqlHost = $projectName . '_' . $mysqlHost;

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
            ['Project URL', $magentoHost],
            ['Project Protocol', $magentoProtocol],
            ['DB Host', $mysqlHost],
            ['DB Name', $dbName],
            ['DB User', $dbUser],
            ['DB Password', $dbPassword],
        ];
        $io->table($headers, $rows);

        $dbConnection = Db::getConnection(
            $mysqlHost,
            $input->getOption(DbOptions::USER),
            $dbPassword,
            $dbName
        );

        $output->writeln('<info>dev/static/sign -> 0 </info>');
        $q = 'UPDATE core_config_data set value=0 WHERE path = "dev/static/sign" ';
        $dbConnection->exec($q);

        $output->writeln('<info>merge [css, js] -> 0 </info>');
        $qm = 'UPDATE core_config_data set value=0 WHERE path = "dev/js/merge_files" ';
        $qm2 = 'UPDATE core_config_data set value=0 WHERE path = "dev/css/merge_css_files" ';
        $dbConnection->exec($qm);
        $dbConnection->exec($qm2);

        $output->writeln('<info>Delete custom  admin URL</info>');
        $qm = 'DELETE from core_config_data WHERE path = "admin/url/use_custom" ';
        $qm2 = 'DELETE from core_config_data WHERE path = "admin/url/custom" ';
        $qm3 = 'DELETE from core_config_data WHERE path = "admin/url/use_custom_path" ';
        $qm4 = 'DELETE from core_config_data WHERE path = "admin/url/custom_path" ';

        $dbConnection->exec($qm);
        $dbConnection->exec($qm2);
        $dbConnection->exec($qm3);
        $dbConnection->exec($qm4);

        $output->writeln('<info>Disable secure...</info>');

        $qm = 'UPDATE core_config_data set value="0" WHERE path = "web/secure/use_in_frontend" ';
        $qm2 = 'UPDATE core_config_data set value="0" WHERE path = "web/secure/use_in_adminhtml" ';

        $dbConnection->exec($qm);
        $dbConnection->exec($qm2);
        $dbConnection->exec($qm3);
        $dbConnection->exec($qm4);

        $output->writeln('<info>Admin User created (admin / ewave123)...</info>');
        $destinationPath = EnvConfig::getValue('WEBSITE_APPLICATION_ROOT') ?: EnvConfig::getValue('WEBSITE_DOCUMENT_ROOT');
        $command = "cd $destinationPath && php bin/magento admin:user:create --admin-user='admin' --admin-password='ewave123' --admin-email='admin@test.ewave.com' --admin-firstname='LocalAdmin' --admin-lastname='LocalAdmin'";
        $this->executeCommands(
            $command,
            $output
        );

        if (!isset($e)) {
            $io->success('Dev actions has been completed');
        } else {
            $io->warning('Some issues appeared during DB updating');
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionsConfig()
    {
        return [
            DbOptions::HOST => DbOptions::get(DbOptions::HOST),
            DbOptions::USER => DbOptions::get(DbOptions::USER),
            DbOptions::PASSWORD => DbOptions::get(DbOptions::PASSWORD),
            DbOptions::NAME => DbOptions::get(DbOptions::NAME),
        ];
    }
}
