<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\QueuedTracking\tests\Integration;

use Piwik\Plugins\QueuedTracking\Queue\Backend\Redis;
use Piwik\Plugins\QueuedTracking\tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Translate;

/**
 * @group QueuedTracking
 * @group Redis
 * @group RedisTest
 * @group Queue
 * @group Tracker
 */
class RedisTest extends IntegrationTestCase
{
    /**
     * @var Redis
     */
    private $redis;
    private $emptyListKey = 'testMyEmptyListTestKey';
    private $listKey = 'testMyListTestKey';
    private $key = 'testKeyValueKey';

    public function setUp()
    {
        parent::setUp();

        $this->redis = $this->createRedisBackend();

        if (!$this->hasDependencies()) {
            $this->redis->delete($this->emptyListKey);
            $this->redis->delete($this->listKey);
            $this->redis->delete($this->key);
            $this->redis->appendValuesToList($this->listKey, array(10, 299, '34'));
        }
    }

    public function test_appendValuesToList_shouldNotAddAnything_IfNoValuesAreGiven()
    {
        $this->redis->appendValuesToList($this->emptyListKey, array());

        $this->assertNumberOfItemsInList($this->emptyListKey, 0);

        $verify = $this->redis->getFirstXValuesFromList($this->emptyListKey, 1);
        $this->assertEquals(array(), $verify);
    }

    public function test_appendValuesToList_shouldAddOneValue_IfOneValueIsGiven()
    {
        $this->redis->appendValuesToList($this->emptyListKey, array(4));

        $verify = $this->redis->getFirstXValuesFromList($this->emptyListKey, 1);

        $this->assertEquals(array(4), $verify);
    }

    public function test_appendValuesToList_shouldBeAbleToAddMultipleValues()
    {
        $this->redis->appendValuesToList($this->emptyListKey, array(10, 299, '34'));
        $this->assertFirstValuesInList($this->emptyListKey, array(10, 299, '34'));
    }

    public function test_getFirstXValuesFromList_shouldReturnAnEmptyArray_IfListIsEmpty()
    {
        $this->assertFirstValuesInList($this->emptyListKey, array());
    }

    public function test_getFirstXValuesFromList_shouldReturnOnlyValuesFromTheBeginningOfTheList()
    {
        $this->assertFirstValuesInList($this->listKey, array(), 0);
        $this->assertFirstValuesInList($this->listKey, array(10), 1);
        $this->assertFirstValuesInList($this->listKey, array(10, 299), 2);
        $this->assertFirstValuesInList($this->listKey, array(10, 299, '34'), 3);
        $this->assertFirstValuesInList($this->listKey, array(10, 299, '34'));
    }

    public function test_removeFirstXValuesFromList_shouldNotReturnAnything_IfNumValueToRemoveIsZero()
    {
        $this->redis->removeFirstXValuesFromList($this->listKey, 0);
        $this->assertFirstValuesInList($this->listKey, array(10, 299, '34'));
    }

    public function test_removeFirstXValuesFromList_shouldBeAbleToRemoveOneValueFromTheBeginningOfTheList()
    {
        $this->redis->removeFirstXValuesFromList($this->listKey, 1);
        $this->assertFirstValuesInList($this->listKey, array(299, '34'));
    }

    public function test_removeFirstXValuesFromList_shouldBeAbleToRemoveMultipleValuesFromTheBeginningOfTheList()
    {
        $this->redis->removeFirstXValuesFromList($this->listKey, 2);
        $this->assertFirstValuesInList($this->listKey, array('34'));

        // remove one more
        $this->redis->removeFirstXValuesFromList($this->listKey, 1);
        $this->assertFirstValuesInList($this->listKey, array());
    }

    public function test_removeFirstXValuesFromList_ShouldNotFail_IfListIsEmpty()
    {
        $this->redis->removeFirstXValuesFromList($this->emptyListKey, 1);
        $this->assertFirstValuesInList($this->emptyListKey, array());
    }

    public function test_getNumValuesInList_shouldReturnZero_IfListIsEmpty()
    {
        $this->assertNumberOfItemsInList($this->emptyListKey, 0);
    }

    public function test_getNumValuesInList_shouldReturnNumberOfEntries_WhenListIsNotEmpty()
    {
        $this->redis->appendValuesToList($this->emptyListKey, array(12));
        $this->assertNumberOfItemsInList($this->emptyListKey, 1);

        $this->redis->appendValuesToList($this->emptyListKey, array(3, 99, '488'));
        $this->assertNumberOfItemsInList($this->emptyListKey, 4);
    }

    public function test_delete_ShouldNotWork_IfKeyDoesNotExist()
    {
        $success = $this->redis->delete('inVaLidKeyTest');
        $this->assertFalse($success);
    }

    public function test_delete_ShouldNotWork_ShouldBeAbleToDeleteAList()
    {
        $success = $this->redis->delete($this->listKey);
        $this->assertTrue($success);

        // verify
        $this->assertNumberOfItemsInList($this->listKey, 0);
    }

    public function test_delete_ShouldNotWork_ShouldBeAbleToDeleteARegularKey()
    {
        $this->redis->setIfNotExists($this->key, 'test');

        $success = $this->redis->delete($this->key);
        $this->assertTrue($success);
    }

    public function test_setIfNotExists_ShouldWork_IfNoValueIsSetYet()
    {
        $success = $this->redis->setIfNotExists($this->key, 'value');
        $this->assertTrue($success);
    }

    /**
     * @depends test_setIfNotExists_ShouldWork_IfNoValueIsSetYet
     */
    public function test_setIfNotExists_ShouldNotWork_IfValueIsAlreadySet()
    {
        $success = $this->redis->setIfNotExists($this->key, 'value');
        $this->assertFalse($success);
    }

    /**
     * @depends test_setIfNotExists_ShouldNotWork_IfValueIsAlreadySet
     */
    public function test_setIfNotExists_ShouldAlsoNotWork_IfTryingToSetDifferentValue()
    {
        $success = $this->redis->setIfNotExists($this->key, 'another val');
        $this->assertFalse($success);
    }

    /**
     * @depends test_setIfNotExists_ShouldAlsoNotWork_IfTryingToSetDifferentValue
     */
    public function test_setIfNotExists_ShouldWork_AsSoonAsKeyWasDeleted()
    {
        $this->redis->delete($this->key);
        $success = $this->redis->setIfNotExists($this->key, 'another val');
        $this->assertTrue($success);
    }

    public function test_expire_ShouldWork()
    {
        $success = $this->redis->setIfNotExists($this->key, 'test');
        $this->assertTrue($success);

        $success = $this->redis->expire($this->key, $seconds = 1);
        $this->assertTrue($success);

        // should not work as value still saved and not expired yet
        $success = $this->redis->setIfNotExists($this->key, 'test');
        $this->assertFalse($success);

        sleep($seconds + 1);

        // value is expired and should work now!
        $success = $this->redis->setIfNotExists($this->key, 'test');
        $this->assertTrue($success);
    }

    private function assertNumberOfItemsInList($key, $expectedNumber)
    {
        $numValus = $this->redis->getNumValuesInList($key);

        $this->assertSame($expectedNumber, $numValus);
    }

    private function assertFirstValuesInList($key, $expectedValues, $numValues = 999)
    {
        $verify = $this->redis->getFirstXValuesFromList($key, $numValues);

        $this->assertEquals($expectedValues, $verify);
    }

    public function test_checkConnectionDetails_shouldFailIfServerIsWrong()
    {
        $this->redis->setConfig('192.168.123.234', 6379, 0.2, null);
        $success = $this->redis->testConnection();
        $this->assertFalse($success);
    }

    public function test_checkConnectionDetails_shouldFailIfPortIsWrong()
    {
        $this->redis->setConfig('127.0.0.1', 6370, 0.2, null);
        $success = $this->redis->testConnection();
        $this->assertFalse($success);
    }

    public function test_checkConnectionDetails_shouldNotFailIfConnectionDataIsCorrect()
    {
        $success = $this->createRedisBackend()->testConnection();
        $this->assertTrue($success);
    }


}