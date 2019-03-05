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
 * Command for Magento final steps
 */
class MagentoSetupFinalize extends CommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('magento2:setup:finalize')
            ->setDescription('Deploy mode set, DI:compile, Static-content, Grant file, Crontab, Cache clean')
            ->setHelp('Deploy mode set, DI:compile, Static-content, Grant file, Crontab, Cache clean');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->commandTitle($io, 'Finalization');

        $magentoPath = EnvConfig::getValue('WEBSITE_DOCUMENT_ROOT');
        $projectName = EnvConfig::getValue('PROJECT_NAME');
        $containerVarnishHost = EnvConfig::getValue('CONTAINER_VARNISH_NAME');
        $containerVarnishEnable = EnvConfig::getValue('VARNISH_ENABLE');

        $varnishHost = $projectName . '_' . $containerVarnishHost;

        $headers = ['Parameter', 'Value'];
        $rows = [
            ['Project source code folder', $magentoPath],
        ];
        $io->table($headers, $rows);

        if (!$magentoPath) {
            $magentoPath = $input->getOption(MagentoOptions::PATH);
        }

        $io->progressStart(6);
        $output->writeln([$io->progressAdvance(1),""]);

        try {
            if ($this->requestOption(MagentoOptions::DEV_MODE, $input, $output, true)) {
                $this->executeCommands(
                    sprintf('cd %s && php bin/magento deploy:mode:set developer', $magentoPath),
                    $output
                );
            }
        } catch (\Exception $e) {
            $io->warning([$e->getMessage(), 'Step skipped.']);
        }

        if (!file_exists("$magentoPath/app/etc/config.php")) {
            $this->executeCommands(
                sprintf('cd %s && php bin/magento module:enable --all', $magentoPath),
                $output
            );
        }

        $output->writeln([$io->progressAdvance(1),""]);

        if ($this->requestOption(MagentoOptions::STATIC_CONTENTS_DEPLOY, $input, $output, true)) {
            try {
                $this->executeCommands(
                    sprintf('cd %s && php bin/magento setup:static-content:deploy -f', $magentoPath),
                    $output
                );
            } catch (\Exception $e) {
                $io->note($e->getMessage());
                $io->note('Step skipped.');
            }
        }

        $output->writeln(["",$io->progressAdvance(1),""]);

        if ($this->requestOption(MagentoOptions::CRON_RUN, $input, $output, true)) {
            $crontab = implode(
                "\n",
                [
                    sprintf(
                        '1 */9 * * * /usr/local/bin/php %s/bin/magento cron:run | grep -v "Ran jobs by schedule"'
                        . ' >> %s/var/log/magento.cron.log',
                        $magentoPath,
                        $magentoPath
                    ),
                    sprintf(
                        '30 */7 * * * /usr/local/bin/php %s/update/cron.php >> %s/var/log/update.cron.log',
                        $magentoPath,
                        $magentoPath
                    ),
                    sprintf(
                        '50 */6 * * * /usr/local/bin/php %s/bin/magento setup:cron:run >> %s/var/log/setup.cron.log',
                        $magentoPath,
                        $magentoPath
                    )
                ]
            );
            file_put_contents("$magentoPath/crontab.sample", $crontab . "\n");
            $this->executeCommands(["crontab $magentoPath/crontab.sample", 'crontab -l'], $output);
        }

        $output->writeln(["",$io->progressAdvance(1),""]);

        if ($this->requestOption(MagentoOptions::DI_COMPILE, $input, $output, true)) {
            try {
                $this->executeCommands(sprintf('cd %s && php bin/magento setup:di:compile', $magentoPath), $output);
            } catch (\Exception $e) {
                $io->note($e->getMessage());
                $io->note('Step skipped.');
            }
        }

        if ($this->isTrue($containerVarnishEnable) && ($this->requestOption(MagentoOptions::HTTP_CACHE_HOSTS, $input, $output, true))) {
            try {
                $this->executeCommands(sprintf('cd %s && php bin/magento setup:config:set --http-cache-hosts=%s',
                    $magentoPath, $varnishHost), $output);
            } catch (\Exception $e) {
                $io->note($e->getMessage());
                $io->note('Step skipped.');
            }
        }

        $output->writeln(["",$io->progressAdvance(1),""]);

        $this->executeCommands(sprintf('cd %s && php bin/magento setup:upgrade', $magentoPath), $output);

        $output->writeln("<comment>Clening cache...</comment>");
        try {
            $this->executeCommands(sprintf('cd %s && php bin/magento cache:clean', $magentoPath), $output);
        } catch (\Exception $e) {
            $io->note($e->getMessage());
            $io->note('Step skipped.');
        }

        $io->progressFinish();

        if (!isset($e)) {
            $io->success('Finalisation steps are passed');
        } else {
            $io->warning('Some issues appeared during finalization steps');
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
            MagentoOptions::DEV_MODE => MagentoOptions::get(MagentoOptions::DEV_MODE),
            MagentoOptions::STATIC_CONTENTS_DEPLOY => MagentoOptions::get(MagentoOptions::STATIC_CONTENTS_DEPLOY),
            MagentoOptions::DI_COMPILE => MagentoOptions::get(MagentoOptions::DI_COMPILE),
            MagentoOptions::CRON_RUN => MagentoOptions::get(MagentoOptions::CRON_RUN),
            MagentoOptions::HTTP_CACHE_HOSTS => MagentoOptions::get(MagentoOptions::HTTP_CACHE_HOSTS),
        ];
    }
}
