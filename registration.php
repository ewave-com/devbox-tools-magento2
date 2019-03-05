<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

\CoreDevBoxScripts\Framework\CommandRegistration::registerCommandPool(
    __DIR__ . '/Command/Pool',
    'MagentoDevBox\\Command\\Pool'
);
