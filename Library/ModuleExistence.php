<?php
/**
 * @author    Ewave <https://ewave.com/>
 * @copyright 2018-2019 NASKO TRADING PTY LTD
 * @license   https://ewave.com/wp-content/uploads/2018/07/eWave-End-User-License-Agreement.pdf BSD Licence
 */

namespace MagentoDevBox\Library;

/**
 * Class for module existence check
 */
class ModuleExistence
{
    /**
     * @param string $path
     * @param string $moduleName
     * @return bool
     */
    public static function isModuleExists($path, $moduleName)
    {
        $moduleExist = exec(
            sprintf(
                'cd %s && php bin/magento module:status | grep %s',
                $path,
                $moduleName
            )
        );

        return !$moduleExist ? false : true;
    }
}
