<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\QueuedTracking\Queue;

use Piwik\Plugins\QueuedTracking\Queue;

/**
 * Interface for queue backends.
 */
interface Backend
{

    public function appendValuesToList($key, $values);

    public function getFirstXValuesFromList($key, $numValues);

    public function removeFirstXValuesFromList($key, $numValues);

    public function getNumValuesInList($key);

    public function setIfNotExists($key, $value);

    public function delete($key);

    public function expire($key, $ttlInSeconds);
}