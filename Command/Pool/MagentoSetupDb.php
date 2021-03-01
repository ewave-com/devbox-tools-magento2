<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

namespace MagentoDevBox\Command\Pool;

use CoreDevBoxScripts\Command\Options\Db as DbOptions;
use CoreDevBoxScripts\Command\Pool\CoreSetupDb;
use CoreDevBoxScripts\Library\Registry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Command for Magento final steps
 */
class MagentoSetupDb extends CoreSetupDb
{
    /**
     * @var array
     */
    private $optionsConfig;

    /**
     * {@inheritdoc}
     *
     * @throws CommandNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        Registry::set(static::CHAINED_EXECUTION_FLAG, true);

        $this->executeWrappedCommands(
            [
                'core:setup:update-db-data',
                'magento2:setup:dburls',
                'magento2:setup:reset-emails',
                'magento2:setup:db-sales-prefixes',
            ],
            $input,
            $output
        );
    }
}
