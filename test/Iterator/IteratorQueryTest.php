<?php

namespace Bakame\Csv\Iterator;

use ArrayIterator;
use ReflectionClass;
use PHPUnit_Framework_TestCase;

/**
 * @group iterator
 */
class IteratorQueryTest extends PHPUnit_Framework_TestCase
{
    private $traitQuery;
    private $iterator;
    private $data = ['john', 'jane', 'foo', 'bar'];

    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    private function createTraitObject()
    {
        return $this->getObjectForTrait('\Bakame\Csv\Iterator\IteratorQuery');
    }

    public function setUp()
    {
        $this->traitQuery = $this->createTraitObject();
        $this->iterator = new ArrayIterator($this->data);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetLimit()
    {
        $this->traitQuery->setLimit(1);
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);
        $this->assertCount(1, $res);

        $this->traitQuery->setLimit(-4);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetOffset()
    {
        $this->traitQuery->setOffset(1);
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);
        $this->assertCount(3, $res);

        $this->traitQuery->setOffset('toto');
    }

    public function testIntervalLimitTooLong()
    {
        $this->traitQuery->setOffset(3);
        $this->traitQuery->setLimit(10);
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);
        $this->assertSame([3 => 'bar'], $res);
        $this->assertCount(1, $res);
    }

    public function testInterval()
    {
        $this->traitQuery->setOffset(1);
        $this->traitQuery->setLimit(1);
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);
        $this->assertCount(1, $res);
    }

    public function testFilter()
    {
        $func = function ($row) {
            return $row == 'john';
        };
        $this->traitQuery->setFilter($func);

        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);
        $this->assertCount(1, $res);
    }

    public function testSortBy()
    {
        $sortingData = [
            ['john', 'doe', 'john.doe@example.com'],
            ['son','of','son.of@example.com'],
            ['dana','doe','jane.doe@example.com'],
        ];

        $expectedData = [
            ['dana','doe','jane.doe@example.com'],
            ['john', 'doe', 'john.doe@example.com'],
            ['son','of','son.of@example.com'],
        ];

        $traitQuery = $this->createTraitObject();
        $iterator = new ArrayIterator($sortingData);

        $traitQuery->setSortBy(0, SORT_ASC);
        $iterator = $this->invokeMethod($traitQuery, 'execute', [$iterator]);
        $res = iterator_to_array($iterator, false);

        $this->assertSame($expectedData, $res);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInconsistentCSVSortBy()
    {
        $raw = [
            ['john', 1,],
            ['malick', 2, 'superman'],
            ['malick', 5, 'machete'],
            ['data', 3, 'bouba'],
        ];

        $traitQuery = $this->createTraitObject();
        $iterator = new ArrayIterator($raw);

        $traitQuery->setSortBy(2, SORT_ASC);
        $this->invokeMethod($traitQuery, 'execute', [$iterator]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidColumnIndexSetSortBy()
    {
        $this->traitQuery->setSortBy('annee', SORT_DESC);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidSortFlagSetSortBy()
    {
        $this->traitQuery->setSortBy(3, 'SORT_DESC');
    }

    public function testExecuteWithCallback()
    {
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator, function ($value) {
            return strtoupper($value);
        }]);
        $this->assertSame(array_map('strtoupper', $this->data), iterator_to_array($iterator));
    }
}
