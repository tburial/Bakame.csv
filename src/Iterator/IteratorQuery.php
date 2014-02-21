<?php
/**
* Bakame.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/Bakame.csv
* @license http://opensource.org/licenses/MIT
* @version 4.2.0
* @package Bakame.csv
*
* MIT LICENSE
*
* Permission is hereby granted, free of charge, to any person obtaining
* a copy of this software and associated documentation files (the
* "Software"), to deal in the Software without restriction, including
* without limitation the rights to use, copy, modify, merge, publish,
* distribute, sublicense, and/or sell copies of the Software, and to
* permit persons to whom the Software is furnished to do so, subject to
* the following conditions:
*
* The above copyright notice and this permission notice shall be
* included in all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
* EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
* MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
* NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
* LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
* OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
* WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
namespace Bakame\Csv\Iterator;

use ArrayIterator;
use CallbackFilterIterator;
use InvalidArgumentException;
use RuntimeException;
use Iterator;
use LimitIterator;

/**
 *  A Trait to filter in a SQL-like manner Iterators
 *
 * @package Bakame.csv
 * @since  4.0.0
 *
 */
trait IteratorQuery
{
    /**
     * iterator Offset
     *
     * @var integer
     */
    private $offset = 0;

    /**
     * iterator maximum length
     *
     * @var integer
     */
    private $limit = -1;

    /**
     * Callable function to filter the iterator
     *
     * @var array
     */
    private $filter = [];

    /**
     * Callable function to sort the ArrayObject
     *
     * @var array
     */
    private $sortBy = [];

    /**
     * Set the Iterator filter method
     *
     * @param callable $filter
     *
     * @return self
     */
    public function setFilter(callable $filter)
    {
        $this->filter[] = $filter;

        return $this;
    }

    protected function applyFilter(&$iterator)
    {
        if (! $this->filter) {
            return $this;
        }
        foreach ($this->filter as $callable) {
            $iterator = new CallbackFilterIterator($iterator, $callable);
        }
        $this->filter = null;

        return $this;
    }

    /**
     * Set the ArrayObject sort method
     *
     * @param callable $sort
     *
     * @return self
     */
    public function setSortBy($columnIndex, $sort_order)
    {
        if (! $this->isValidInteger($columnIndex)) {
            throw new InvalidArgumentException('the columnIndex must be a positive integer or 0');
        } elseif (! in_array($sort_order, [SORT_ASC, SORT_DESC])) {
            throw new InvalidArgumentException('the sort_order must be a PHP sorting order flag');
        }
        $this->sortBy[] = [$columnIndex, $sort_order];

        return $this;
    }

    /**
     * Sort the Iterator
     *
     * @param \Iterator $iterator
     *
     * @return self
     */
    public function applySortBy(&$iterator)
    {
        if (! $this->sortBy) {
            return $this;
        }
        $res = iterator_to_array($iterator, false);
        $column_to_sort = [];
        foreach ($this->sortBy as $args) {
            $column_to_sort[] = $args[0];
        }
        $fields = $this->fetchSortingFields($res, $column_to_sort);
        $sort = [];
        foreach ($this->sortBy as $args) {
            $sort[] = $fields[$args[0]];
            $sort[] = $args[1];
        }
        $sort[] = &$res;
        call_user_func_array('array_multisort', $sort);
        $this->sortBy = [];

        $iterator = new ArrayIterator(array_pop($sort));

        return $this;
    }

    /**
     * Get a column data to use for sorting
     *
     * @param array $res            the multidimentional array
     * @param array $column_to_sort the column index to get
     *
     * @return array
     *
     * @throws \RuntimeException If the columnIndex is not present in one raw
     */
    protected function fetchSortingFields(array $res, array $column_to_sort)
    {
        $fields = array_fill_keys($column_to_sort, []);
        foreach ($res as $key => $values) {
            foreach ($column_to_sort as $columnIndex) {
                if (! array_key_exists($columnIndex, $values)) {
                    throw new RuntimeException("Row sizes are inconsistent in the CSV");
                }
                $fields[$columnIndex][$key] = $values[$columnIndex];
            }
        }

        return $fields;
    }

    /**
     * Validate a value to be a positive integer or equal to 0
     *
     * @param mixed $value
     *
     * @return boolean
     */
    protected function isValidInteger($value)
    {
        return false !== filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    }

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return self
     */
    public function setOffset($offset)
    {
        if (! $this->isValidInteger($offset)) {
            throw new InvalidArgumentException('the offset must be a positive integer or 0');
        }
        $this->offset = $offset;

        return $this;
    }

    /**
     * Set LimitInterator Count
     *
     * @param integer $limit
     *
     * @return self
     */
    public function setLimit($limit)
    {
        if (false === filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1]])) {
            throw new InvalidArgumentException('the limit must an integer greater or equals to -1');
        }
        $this->limit = $limit;

        return $this;
    }

    /**
     * apply offset and limit condition to the Iterator
     *
     * @param Iterator $iterator
     *
     * @return self
     */
    protected function applyInterval(&$iterator)
    {
        if (-1 == $this->limit && 0 == $this->offset) {
            return $this;
        }

        $offset = $this->offset;
        $limit = -1;
        if ($this->limit > 0) {
            $limit = $this->limit;
        }
        $this->limit = -1;
        $this->offset = 0;

        $iterator = new LimitIterator($iterator, $offset, $limit);

        return $this;
    }

    /**
     * Return a filtered Iterator based on the filtering settings
     *
     * @param Iterator $iterator The iterator to be filtered
     * @param callable $callable a callable function to be applied to each Iterator item
     *
     * @return Iterator
     */
    protected function execute(Iterator $iterator, callable $callable = null)
    {
        $this->applyFilter($iterator);
        $this->applySortBy($iterator);
        $this->applyInterval($iterator);

        if (! is_null($callable)) {
            return new MapIterator($iterator, $callable);
        }

        return $iterator;
    }
}
