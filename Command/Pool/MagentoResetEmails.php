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
use CoreDevBoxScripts\Library\JsonConfig;
use MagentoDevBox\Command\Options\Magento as MagentoOptions;
use CoreDevBoxScripts\Command\Options\Db as DbOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for Magento final steps
 */
class MagentoResetEmails extends CommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('magento2:setup:reset-emails')
            ->setDescription('Postfix .reset-ewave.com will be added to email addresses.')
            ->setHelp('Reset Emails in DB. Postfix .reset-ewave.com will be added to email addresses.');

        $this->questionOnRepeat = 'Try to reset email again?';

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->executeRepeatedly('updateEmails', $input, $output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function updateEmails(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->commandTitle($io, 'Set Https');

        $updateAgr = $this->requestOption(MagentoOptions::EMAILS_UPDATE, $input, $output, true);
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

        if ($io->ask('Add postfix to email in database?', 'Yes') == 'Yes'){

            $qm = '
                drop procedure if exists change_emails;
                create procedure change_emails(IN postfix VARCHAR(255) , IN DB_NAME VARCHAR(255))
                BEGIN 
                 
                 DECLARE v_finished INTEGER DEFAULT 0;
                 DECLARE tbl_names VARCHAR(50);
                DECLARE tblname VARCHAR(50);
                 DECLARE column_name VARCHAR(50);
                DECLARE cur CURSOR FOR 
                 SELECT DISTINCT t.TABLE_NAME as n , t.COLUMN_NAME as c
                 FROM INFORMATION_SCHEMA.COLUMNS t
                 WHERE (t.COLUMN_NAME IN (\'email\',\'customer_email\') /*OR (t.COLUMN_NAME like \'%email%\')*/ )
                 AND TABLE_SCHEMA = DB_NAME;
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_finished = 1;
                DROP TEMPORARY TABLE IF EXISTS tblResults;
                 CREATE TEMPORARY TABLE IF NOT EXISTS tblResults(
                 id int,
                 tbl_name VARCHAR(250),
                 tbl_coloumn VARCHAR(250)
                 );
                OPEN cur;
                tables_loop: LOOP
                FETCH cur INTO tblname,column_name;
                 
                 IF v_finished = 1 THEN 
                 LEAVE tables_loop;
                 END IF;
                 
                 SET @s = CONCAT(\'UPDATE \', tblname , \' SET \' , column_name , \' = CONCAT(\' , column_name , \',\' , \'"\' , postfix , \'")\'); 
                 PREPARE stmt FROM @s; 
                 EXECUTE stmt;
                 
                 insert into tblResults(tbl_name , tbl_coloumn ) values (tblname , column_name);
                 
                 END LOOP;
                CLOSE cur;
                 SELECT * FROM tblResults;
                END;
                            
                            
                call change_emails(\'.reset-ewave.com\' , \'' . $dbName . '\');                            
                            
                ';

            $dbConnection->exec($qm);

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
            MagentoOptions::EMAILS_UPDATE => MagentoOptions::get(MagentoOptions::EMAILS_UPDATE),
            DbOptions::HOST => DbOptions::get(DbOptions::HOST),
            DbOptions::USER => DbOptions::get(DbOptions::USER),
            DbOptions::PASSWORD => DbOptions::get(DbOptions::PASSWORD),
            DbOptions::NAME => DbOptions::get(DbOptions::NAME),
        ];
    }
}
