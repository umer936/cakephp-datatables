<?php

namespace DataTables\Lib;

/**
 * A convenience wrapper for managing a single column definition.
 *
 * @method ColumnDefinition visible()
 * @method ColumnDefinition notVisible()
 * @method ColumnDefinition orderable()
 * @method ColumnDefinition notOrderable()
 * @method ColumnDefinition searchable()
 * @method ColumnDefinition notSearchable()
 */
class ColumnDefinition implements \JsonSerializable, \ArrayAccess
{
    /** @var array<string, mixed> Stores all column properties */
    public array $content = [];

    /** @var ColumnDefinitions Reference to the owner column definitions */
    protected ColumnDefinitions $owner;

    /** @var string[] List of positive toggleable properties */
    protected array $switchesPositive = ['visible', 'orderable', 'searchable'];

    /** @var string[] List of negative toggleable properties */
    protected array $switchesNegative = [];

    /**
     * Constructor.
     *
     * @param array $template Initial properties for the column
     * @param ColumnDefinitions $owner Reference to the owning ColumnDefinitions object
     */
    public function __construct(array $template, ColumnDefinitions $owner)
    {
        $this->content = $template;
        $this->owner = $owner;

        // Generate negative counterparts for switches
        $this->switchesNegative = array_map(
            static fn($property) => 'not' . ucfirst($property),
            $this->switchesPositive
        );
    }

    /**
     * Adds a new column via the owner.
     *
     * @param mixed ...$args Arguments for the owner's add method
     * @return ColumnDefinition The added column
     */
    public function add(...$args): ColumnDefinition
    {
        return $this->owner->add(...$args);
    }

    /**
     * Sets one or more properties for the column.
     *
     * @param array|string $key Array of key-value pairs or a single key
     * @param mixed|null $value Value to set if $key is a string
     * @return ColumnDefinition
     * @throws \InvalidArgumentException If both an array and value are provided
     */
    public function set(array|string $key, mixed $value = null): ColumnDefinition
    {
        if (is_array($key)) {
            if ($value !== null) {
                throw new \InvalidArgumentException('Provide either an array or a key-value pair, not both.');
            }
            $this->content = array_merge($this->content, $key);
        } else {
            $this->content[$key] = $value;
        }
        return $this;
    }

    /**
     * Convenience method for toggling properties.
     *
     * @param string $name Name of the method called
     * @param array $arguments Arguments passed to the method
     * @return ColumnDefinition
     * @throws \InvalidArgumentException If arguments are provided to toggling methods
     */
    public function __call(string $name, array $arguments): ColumnDefinition
    {
        if (!empty($arguments)) {
            throw new \InvalidArgumentException("$name() does not accept any arguments.");
        }

        if (in_array($name, $this->switchesPositive)) {
            $this->content[$name] = true;
        } elseif (in_array($name, $this->switchesNegative)) {
            $name = lcfirst(substr($name, 3)); // Remove 'not' prefix and lowercase
            $this->content[$name] = false;
        } else {
            throw new \BadMethodCallException("Undefined method $name.");
        }

        return $this;
    }

    /**
     * Unsets a property by key.
     *
     * @param string $key Property key to remove
     * @return ColumnDefinition
     */
    public function unset(string $key): ColumnDefinition
    {
        unset($this->content[$key]);
        return $this;
    }

    /**
     * Sets a custom render function for the column.
     *
     * @param string $name Name of the render function
     * @param array $args Arguments for the render function
     * @return ColumnDefinition
     * @throws \JsonException
     */
    public function render(string $name, array $args = []): ColumnDefinition
    {
        $this->content['render'] = new CallbackFunction($name, $args);
        return $this;
    }

    /**
     * Serializes the column content to an array for JSON encoding.
     *
     * @return array The column's properties
     */
    public function jsonSerialize(): array
    {
        return $this->content;
    }

    /**
     * Checks if a property exists.
     *
     * @param mixed $offset Property key
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->content[$offset]);
    }

    /**
     * Gets a property by key.
     *
     * @param mixed $offset Property key
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->content[$offset];
    }

    /**
     * Sets a property by key.
     *
     * @param mixed $offset Property key
     * @param mixed $value Value to set
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->content[] = $value;
        } else {
            $this->content[$offset] = $value;
        }
    }

    /**
     * Unsets a property by key.
     *
     * @param mixed $offset Property key
     */
    public function offsetUnset($offset): void
    {
        unset($this->content[$offset]);
    }
}
