<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

namespace MagentoDevBox\Command\Pool;

use CoreDevBoxScripts\Command\CommandAbstract;
use CoreDevBoxScripts\Library\Registry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Command for Magento final steps
 */
class MagentoInstallExisting extends CommandAbstract
{
    /**
     * @var array
     */
    private $optionsConfig;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('magento2:install:existing')
            ->setDescription(
                'Install existing magento Project : '
                    . '[Code Download]->[DB Download/Install/Configure]->[Configure env.php]->[Magento finalisation]'
            )
            ->setHelp('[Code Download]->[DB Download/Install/Configure]');
    }

    /**
     * Perform delayed configuration
     *
     * @return void
     */
    public function postConfigure()
    {
        parent::configure();
    }

    /**
     * {@inheritdoc}
     *
     * @throws CommandNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Registry::set(static::CHAINED_EXECUTION_FLAG, true);

        $this->executeWrappedCommands(
            [
                'core:setup:permissions',
                'core:setup:code',
                'core:setup:media',
                'magento2:setup:configs',
                'core:setup:db',
                'core:setup:update-db-data',
                'magento2:setup:dburls',
                'magento2:setup:reset-emails',
                'magento2:setup:common-commands',
                'magento2:setup:finalize',
                'magento2:setup:redis',
                'core:setup:permissions'
            ],
            $input,
            $output
        );
    }
}
