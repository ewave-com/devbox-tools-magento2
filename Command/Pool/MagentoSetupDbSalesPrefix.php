<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

namespace MagentoDevBox\Command\Pool;

use CoreDevBoxScripts\Command\CommandAbstract;
use CoreDevBoxScripts\Command\Options\Db as DbOptions;
use CoreDevBoxScripts\Library\Db;
use CoreDevBoxScripts\Library\EnvConfig;
use CoreDevBoxScripts\Library\JsonConfig;
use MagentoDevBox\Command\Options\Magento as MagentoOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for Magento final steps
 */
class MagentoSetupDbSalesPrefix extends CommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('magento2:setup:db-sales-prefixes')
            ->setDescription('Change Sales prefixes for increment_id in main sales tables')
            ->setHelp('Change Sales prefix for sales tables');

        $this->questionOnRepeat = 'Try to change sales prefix again?';

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->executeRepeatedly('updateSalesPrefixes', $input, $output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function updateSalesPrefixes(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->commandTitle($io, 'Update sales prefixes in DB');

        $updateAgr = $this->requestOption(MagentoOptions::SALES_PREFIX_UPDATE, $input, $output, true);
        if (!$updateAgr) {
            $output->writeln('<comment>Sales prefixes updating skipped</comment>');
            return true;
        }

        $updateExistedOrders = $this->requestOption(MagentoOptions::SALES_PREFIX_ORDERS_UPDATE, $input, $output, true);

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

        $prefixesMap = JsonConfig::getConfig('sources->sales_prefix_mapping');

        if (!$prefixesMap) {
            $io->note('Sales prefixes mapping is not set in .env-projects.json. Default prefix "LOCAL_" will be applied');
            $prefixesMap = ["" => "LOCAL_"];
        }

        foreach ($prefixesMap as $fromPrefix => $toPrefix) {
            $queries = $this->getUpdateSalesPrefixQueries($fromPrefix, $toPrefix, $updateExistedOrders);

            $output->writeln("<info>Updating sales prefixes: '$fromPrefix' to '$toPrefix'</info>");
            foreach ($queries as $query) {
                try {
                    $output->writeln($query);
                    $dbConnection->exec($query);
                } catch (\Exception $e) {
                    $io->note(sprintf('An error occured during query execution. Error message: %s', $e->getMessage()));
                    continue;
                }
            }
        }

        if (!isset($e)) {
            $io->success('Sales prefixes have been updated');
        } else {
            $io->warning('Some issues appeared during DB updating');
            return false;
        }

        return true;
    }

    function getUpdateSalesPrefixQueries($fromPrefix = '', $toPrefix = '', $updateExistedOrders = false){
        $queries = [];

        if($fromPrefix) {
            $updateExpression = sprintf('REPLACE(`increment_id`, \'%s\', \'%s\')', $fromPrefix, $toPrefix);
            $queries[] = sprintf(
                'UPDATE sales_sequence_profile set prefix = REPLACE(prefix, \'%s\', \'%s\')',
                $fromPrefix,
                $toPrefix
            );
        } else {
            $updateExpression = sprintf('CONCAT(\'%s\', `increment_id`)', $toPrefix);
            $queries[] = sprintf('UPDATE sales_sequence_profile set prefix = CONCAT(\'%s\', `prefix`)', $toPrefix);
        }

        /**
         * Retrive all tables with increment_id column:
         * SELECT DISTINCT t.TABLE_NAME as n , t.COLUMN_NAME as c FROM INFORMATION_SCHEMA.COLUMNS t WHERE t.COLUMN_NAME = 'increment_id' AND TABLE_SCHEMA = '{{db_name}}';
         */
        if ($updateExistedOrders) {
            $queries[] = sprintf('UPDATE magento_rma set increment_id = %s', $updateExpression);
            $queries[] = sprintf('UPDATE magento_rma_grid set increment_id = %s', $updateExpression);
            $queries[] = sprintf('UPDATE sales_creditmemo set increment_id = %s', $updateExpression);
            $queries[] = sprintf('UPDATE sales_creditmemo_grid set increment_id = %s', $updateExpression);
            $queries[] = sprintf('UPDATE sales_invoice set increment_id = %s', $updateExpression);
            $queries[] = sprintf('UPDATE sales_invoice_grid set increment_id = %s', $updateExpression);
            $queries[] = sprintf('UPDATE sales_order set increment_id = %s', $updateExpression);
            $queries[] = sprintf('UPDATE sales_order_grid set increment_id = %s', $updateExpression);
            $queries[] = sprintf('UPDATE sales_shipment set increment_id = %s', $updateExpression);
            $queries[] = sprintf('UPDATE sales_shipment_grid set increment_id = %s', $updateExpression);
            //        $queries[] = sprintf('UPDATE magento_sales_creditmemo_grid_archive set increment_id = %s', $updateExpression);
            //        $queries[] = sprintf('UPDATE magento_sales_invoice_grid_archive set increment_id = %s', $updateExpression);
            //        $queries[] = sprintf('UPDATE magento_sales_order_grid_archive set increment_id = %s', $updateExpression);
            //        $queries[] = sprintf('UPDATE magento_sales_shipment_grid_archive set increment_id = %s', $updateExpression);
        }

        return $queries;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionsConfig()
    {
        return [
            MagentoOptions::SALES_PREFIX_UPDATE => MagentoOptions::get(MagentoOptions::SALES_PREFIX_UPDATE),
            MagentoOptions::SALES_PREFIX_ORDERS_UPDATE => MagentoOptions::get(MagentoOptions::SALES_PREFIX_ORDERS_UPDATE),
            DbOptions::HOST => DbOptions::get(DbOptions::HOST),
            DbOptions::USER => DbOptions::get(DbOptions::USER),
            DbOptions::PASSWORD => DbOptions::get(DbOptions::PASSWORD),
            DbOptions::NAME => DbOptions::get(DbOptions::NAME),
        ];
    }
}
