<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

namespace MagentoDevBox\Command\Options;

use CoreDevBoxScripts\Command\Options\AbstractOptions;

/**
 * Container for RabbitMQ options
 */
class RabbitMq extends AbstractOptions
{
    const SETUP = 'rabbitmq-setup';
    const HOST = 'rabbitmq-host';
    const PORT = 'rabbitmq-port';

    /**
     * {@inheritdoc}
     */
    protected static function getOptions()
    {
        return [
            static::SETUP => [
                'boolean' => true,
                'default' => static::getDefaultValue('USE_RABBITMQ', false),
                'description' => 'Whether to install RabbitMQ.',
                'question' => 'Do you want to install RabbitMQ? %default%'
            ],
            static::HOST => [
                'requireValue' => false,
                'default' => 'rabbit',
                'description' => 'RabbitMQ host.',
                'question' => 'Please specify RabbitMQ host %default%'
            ],
            static::PORT => [
                'requireValue' => false,
                'default' => '5672',
                'description' => 'RabbitMQ port.',
                'question' => 'Please specify RabbitMQ port %default%'
            ]
        ];
    }
}
