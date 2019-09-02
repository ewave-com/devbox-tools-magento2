<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

namespace MagentoDevBox\Command\Options;

use CoreDevBoxScripts\Command\Options\AbstractOptions;

/**
 * Container for Magento options
 */
class Magento extends AbstractOptions
{
    const M_CONFIGS_REUSE = 'magento-configs-reuse';
    const HOST = 'magento-host';
    const PORT = 'magento-port';
    const PATH = 'magento-path';
    const URLS_UPDATE = 'yes';
    const EMAILS_UPDATE = 'yes';
    const BACKEND_PATH = 'magento-backend-path';
    const ADMIN_USER = 'magento-admin-user';
    const ADMIN_PASSWORD = 'magento-admin-password';
    const SAMPLE_DATA_INSTALL = 'magento-sample-data-install';
    const STATIC_CONTENTS_DEPLOY = 'magento-static-contents-deploy';
    const DEV_MODE = 'develop';
    const DI_COMPILE = 'magento-di-compile';
    const CRON_RUN = 'magento-cron-run';
    const HTTP_CACHE_HOSTS = 'http-cache-hosts';

    /**
     * {@inheritdoc}
     */
    protected static function getOptions()
    {
        return [
            static::M_CONFIGS_REUSE => [
                'boolean' => true,
                'description' => 'Whether to use existing Magento config files',
                'question' => 'Do you want to update Env.php and Config.php files from source? %default%',
                'default' => 'yes'
            ],
            static::URLS_UPDATE => [
                'boolean' => true,
                'description' => 'Update database URLs',
                'question' => 'Do you want to update Urls in DB to Project values? %default%',
                'default' => 'yes'
            ],
            static::EMAILS_UPDATE => [
                'boolean' => true,
                'description' => 'Update Emails',
                'question' => 'Do you want to add postfix for customers and not only emails in DB? %default%',
                'default' => 'yes'
            ],
            static::HOST => [
                'default' => '127.0.0.1',
                'description' => 'Magento host.',
                'question' => 'Please enter Magento host %default%'
            ],
            static::PATH => [
                'default' => '/var/www/public_html',
                'description' => 'Path to source folder for Magento.',
                'question' => 'Please enter path to source folder for Magento %default%'
            ],
            static::BACKEND_PATH => [
                'default' => static::getDefaultValue('MAGENTO_BACKEND_PATH', 'admin'),
                'description' => 'Magento backend path.',
                'question' => 'Please enter backend path %default%'
            ],
            static::ADMIN_USER => [
                'default' => static::getDefaultValue('MAGENTO_ADMIN_USER', 'admin'),
                'description' => 'Magento admin username.',
                'question' => 'Please enter backend admin username %default%'
            ],
            static::ADMIN_PASSWORD => [
                'default' => static::getDefaultValue('MAGENTO_ADMIN_PASSWORD', 'ewave123'),
                'description' => 'Magento admin password.',
                'question' => 'Please enter backend admin password %default%'
            ],
            static::SAMPLE_DATA_INSTALL => [
                'boolean' => true,
                'default' => static::getDefaultValue('MAGENTO_SAMPLE_DATA_INSTALL', false),
                'description' => 'Whether to install Sample Data.',
                'question' => 'Do you want to install Sample Data? %default%'
            ],
            static::STATIC_CONTENTS_DEPLOY => [
                'boolean' => true,
                'default' => static::getDefaultValue('MAGENTO_STATIC_CONTENTS_DEPLOY', false),
                'description' => 'Whether to pre-deploy all static contents.',
                'question' => 'Do you want to pre-deploy all static assets? %default%'
            ],
            static::DEV_MODE => [
                'boolean' => true,
                'default' => true,
                'description' => 'Setup Developer mode',
                'question' => 'Do you want to developer mode? %default%'
            ],
            static::DI_COMPILE => [
                'boolean' => true,
                'default' => static::getDefaultValue('MAGENTO_DI_COMPILE', true),
                'description' => 'Whether to create generated files beforehand.',
                'question' => 'Do you want to compile dI? %default%'
            ],
            static::CRON_RUN => [
                'boolean' => true,
                'default' => static::getDefaultValue('MAGENTO_CRON_RUN', false),
                'description' => 'Whether to generate crontab file for Magento.',
                'question' => 'Do you want to generate crontab file for Magento? %default%'
            ],
            static::HTTP_CACHE_HOSTS => [
                'boolean' => true,
                'default' => static::getDefaultValue('HTTP_CACHE_HOSTS', false),
                'description' => 'Whether to setup Varnish host for Magento.',
                'question' => 'Do you want to setup http_cache_hosts [varnish host] for Magento? %default%'
            ],
        ];
    }
}
