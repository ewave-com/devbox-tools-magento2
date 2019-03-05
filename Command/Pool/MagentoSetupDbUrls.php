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
use MagentoDevBox\Command\Options\Magento as MagentoOptions;
use CoreDevBoxScripts\Command\Options\Db as DbOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for Magento final steps
 */
class MagentoSetupDbUrls extends CommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('magento2:setup:dburls')
            ->setDescription('Change Urls in DB + protocol update (http <-> https)')
            ->setHelp('Urls Update / Set Https <-> Http');

        $this->questionOnRepeat = 'Try to change protocol again?';

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->executeRepeatedly('updateDatabase', $input, $output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function updateDatabase(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->commandTitle($io, 'Set Https');

        $updateAgr = $this->requestOption(MagentoOptions::URLS_UPDATE, $input, $output, true);
        if (!$updateAgr) {
            $output->writeln('<comment>Urls updating skipped</comment>');
            return true;
        }

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

        if (!trim($magentoProtocol)) {
            $commandsFormatted = ['http'=>'Use HTTP protocol','https' => 'Use HTTPS protocol (Secure)'];
            $magentoProtocol = $io->choice('Type the protocol', $commandsFormatted);
        }

        $magentoUrl = sprintf(
            '%s://%s/',
            $magentoProtocol,
            $magentoHost
        );

        $q = sprintf(
            'UPDATE core_config_data'
            . ' SET value = "%s" '
            . ' WHERE path = "web/unsecure/base_url" OR path = "web/secure/base_url";',
            $magentoUrl
        );

        $qv = sprintf(
            'UPDATE core_config_data'
            . ' SET value = 1 '
            . ' WHERE path = "system/full_page_cache/caching_application";',
            $magentoUrl
        );

        try {
            $output->writeln('<info>Updating Urls...</info>');
            $dbConnection->exec($q);
            $output->writeln('<info>Updating Cache config to use files instead of varnish ...</info>');
            $dbConnection->exec($qv);
            $output->writeln('<info>Database has been updated.<info>');
        } catch (\Exception $e) {
            $io->note($e->getMessage());
            $io->note('Step skipped. Not possible to continue with DB update.');
            return false;
        }

        if (!isset($e)) {
            $io->success('Urls has been updated');
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
            MagentoOptions::URLS_UPDATE => MagentoOptions::get(MagentoOptions::URLS_UPDATE),
            DbOptions::HOST => DbOptions::get(DbOptions::HOST),
            DbOptions::USER => DbOptions::get(DbOptions::USER),
            DbOptions::PASSWORD => DbOptions::get(DbOptions::PASSWORD),
            DbOptions::NAME => DbOptions::get(DbOptions::NAME),
        ];
    }
}
