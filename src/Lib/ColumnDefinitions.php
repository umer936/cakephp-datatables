<?php

namespace DataTables\Lib;

use Traversable;
use ArrayAccess;
use IteratorAggregate;
use Countable;
use InvalidArgumentException;
use BadMethodCallException;
use ArrayIterator;

/**
 * A convenience class to manage DataTables column definitions
 */
class ColumnDefinitions implements \JsonSerializable, ArrayAccess, IteratorAggregate, Countable
{
    /** @var ColumnDefinition[] List of columns */
    protected array $columns = [];

    /** @var array<string, int> Index of columns by name */
    protected array $index = [];

    /**
     * Add a new column to the collection.
     *
     * @param array|string $column Name or pre-filled array for the column
     * @param string|null $fieldname ORM field this column is based on
     * @return ColumnDefinition The added column definition
     */
    public function add(array|string $column, ?string $fieldname = null): ColumnDefinition
    {
        if (is_string($column)) {
            $column = [
                'name' => $column,
                'data' => $column, // Default to name for data field
            ];
        }

        if ($fieldname) {
            $column['field'] = $fieldname;
        }

        $columnDefinition = new ColumnDefinition($column, $this);
        $this->store($columnDefinition);

        return $columnDefinition;
    }

    /**
     * Set titles for all columns in the order they appear.
     *
     * @param string[] $titles Array of titles
     * @throws InvalidArgumentException When the count of titles does not match the columns
     */
    public function setTitles(array $titles): void
    {
        $columnCount = count($this->columns);

        if (count($titles) !== $columnCount) {
            throw new InvalidArgumentException(
                sprintf('Expected %d titles, but %d given.', $columnCount, count($titles))
            );
        }

        foreach ($titles as $i => $title) {
            if (!empty($title)) {
                $this->columns[$i]['title'] = $title;
            }
        }
    }

    /**
     * Serialize the column definitions to an array for JSON encoding.
     *
     * @return array Serialized column definitions
     */
    public function jsonSerialize(): array
    {
        return array_values($this->columns);
    }

    /**
     * Check if a column exists by offset (index or name).
     *
     * @param mixed $offset Column index or name
     * @return bool True if the column exists, false otherwise
     */
    public function offsetExists($offset): bool
    {
        return is_numeric($offset)
            ? isset($this->columns[$offset])
            : isset($this->index[$offset]);
    }

    /**
     * Get a column definition by offset (index or name).
     *
     * @param mixed $offset Column index or name
     * @return ColumnDefinition
     * @throws InvalidArgumentException If the column does not exist
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (is_numeric($offset)) {
            return $this->columns[$offset];
        }

        if (!isset($this->index[$offset])) {
            throw new InvalidArgumentException(sprintf('Column with name "%s" does not exist.', $offset));
        }

        return $this->columns[$this->index[$offset]];
    }

    /**
     * Prevent direct setting of columns.
     *
     * @param mixed $offset Ignored
     * @param mixed $value Ignored
     * @throws BadMethodCallException Always
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Direct setting is not supported. Use add() instead.');
    }

    /**
     * Prevent unsetting of columns.
     *
     * @param mixed $offset Ignored
     * @throws BadMethodCallException Always
     */
    public function offsetUnset(mixed $offset): void
    {
        /* We do not allow splicing because DataTables uses a column's index
           for the ordering command. So the order of columns needs to stay
           consistent from the Controller down to the table displayed. */
        throw new BadMethodCallException('Unset operation is not supported.');
    }

    /**
     * Get an iterator for the columns.
     *
     * @return Traversable Iterator for the columns
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->columns);
    }

    /**
     * Get the number of columns.
     *
     * @return int Number of columns
     */
    public function count(): int
    {
        return count($this->columns);
    }

    /**
     * Store a column definition in the collection.
     *
     * Keep track of where we stored it.
     * Note: our array is only growing! No splicing!
     *
     * @param ColumnDefinition $column Column definition to store
     */
    protected function store(ColumnDefinition $column): void
    {
        $this->columns[] = $column;
        /* Keep track of where we stored it.
           Note: our array is only growing! No splicing! */
        $this->index[$column['name']] = count($this->columns) - 1;
    }
}
