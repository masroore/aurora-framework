<?php

namespace Aurora;

class Collection implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * The methods that can be proxied.
     */
    protected static array $proxies = [
        'average', 'avg', 'contains', 'each', 'every', 'filter', 'first',
        'flatMap', 'groupBy', 'keyBy', 'map', 'max', 'min', 'partition',
        'reject', 'sortBy', 'sortByDesc', 'sum', 'unique',
    ];

    /**
     * The items contained in the collection.
     */
    protected array $items = [];

    /**
     * Create a new collection.
     */
    public function __construct(array|self $items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * Results array of items from Collection or Arrayable.
     */
    protected function getArrayableItems($items): array
    {
        if (\is_array($items)) {
            return $items;
        }

        if ($items instanceof self) {
            return $items->all();
        }

        if ($items instanceof \JsonSerializable) {
            return $items->jsonSerialize();
        }

        if ($items instanceof \Traversable) {
            return iterator_to_array($items);
        }

        return (array)$items;
    }

    /**
     * Get all of the items in the collection.
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return array_map(static function ($value) {
            if ($value instanceof \JsonSerializable) {
                return $value->jsonSerialize();
            }

            return $value;
        }, $this->items);
    }

    /**
     * Create a new collection instance if the value isn't one already.
     */
    public static function make(self|array $items = []): static
    {
        return new static($items);
    }

    /**
     * Wrap the given value in a collection if applicable.
     */
    public static function wrap(self|array $value): static
    {
        return $value instanceof self
            ? new static($value)
            : new static(Arr::wrap($value));
    }

    /**
     * Get the underlying items from the given collection if applicable.
     *
     * @param array|static $value
     */
    public static function unwrap(self|array $value): array
    {
        return $value instanceof self ? $value->all() : $value;
    }

    /**
     * Create a new collection by invoking the callback a given amount of times.
     */
    public static function times(int $number, ?callable $callback = null): static
    {
        if ($number < 1) {
            return new static();
        }

        if (null === $callback) {
            return new static(range(1, $number));
        }

        return (new static(range(1, $number)))->map($callback);
    }

    /**
     * Run a map over each of the items.
     *
     * @return static
     */
    public function map(callable $callback)
    {
        $keys = array_keys($this->items);

        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Add a method to the list of proxied methods.
     *
     * @param string $method
     */
    public static function proxy($method): void
    {
        static::$proxies[] = $method;
    }

    /**
     * Convert the collection to its string representation.
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Get the collection of items as JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Dynamically access collection proxies.
     *
     * @throws \Exception
     */
    public function __get(string $key)
    {
        if (!\in_array($key, static::$proxies, true)) {
            throw new \Exception("Property [{$key}] does not exist on this collection instance.");
        }

        return new HigherOrderCollectionProxy($this, $key);
    }

    public function __set($key, $value): void
    {
    }

    public function __isset(string $key): bool
    {
        return \in_array($key, static::$proxies, true);
    }

    /**
     * Get the median of a given key.
     *
     * @param null $key
     */
    public function median($key = null)
    {
        $count = $this->count();

        if (0 === $count) {
            return;
        }

        $values = with(isset($key) ? $this->pluck($key) : $this)
            ->sort()->values();

        $middle = (int)($count / 2);

        if ($count % 2) {
            return $values->get($middle);
        }

        return (new static([
            $values->get($middle - 1), $values->get($middle),
        ]))->average();
    }

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        return \count($this->items);
    }

    /**
     * Reset the keys on the underlying array.
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * Sort through each item with a callback.
     *
     * @param ?callable $callback
     */
    public function sort(?callable $callback = null): static
    {
        $items = $this->items;

        $callback
            ? uasort($items, $callback)
            : asort($items);

        return new static($items);
    }

    /**
     * Get the values of a given key.
     *
     * @param array|string $value
     * @param string|null  $key
     *
     * @return static
     */
    public function pluck($value, $key = null)
    {
        return new static(Arr::pluck($this->items, $value, $key));
    }

    /**
     * Get an item from the collection by key.
     *
     * @param mixed|null $default
     */
    public function get($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->items[$key];
        }

        return value($default);
    }

    /**
     * Determine if an item exists at an offset.
     */
    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists($offset, $this->items);
    }

    /**
     * Alias for the "avg" method.
     *
     * @param callable|string|null $callback
     */
    public function average($callback = null)
    {
        return $this->avg($callback);
    }

    /**
     * Get the average value of a given key.
     *
     * @param callable|string|null $callback
     */
    public function avg($callback = null)
    {
        if ($count = $this->count()) {
            return $this->sum($callback) / $count;
        }
    }

    /**
     * Get the sum of the given values.
     *
     * @param callable|string|null $callback
     */
    public function sum($callback = null)
    {
        if (null === $callback) {
            return array_sum($this->items);
        }

        $callback = $this->valueRetriever($callback);

        return $this->reduce(static fn ($result, $item) => $result + $callback($item), 0);
    }

    /**
     * Get a value retrieving callback.
     *
     * @return callable
     */
    protected function valueRetriever(string $value): callable|string
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return static fn ($item) => data_get($item, $value);
    }

    /**
     * Determine if the given value is callable, but not a string.
     */
    protected function useAsCallable($value): bool
    {
        return !\is_string($value) && \is_callable($value);
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param mixed|null $initial
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Get the mode of a given key.
     *
     * @param mixed|null $key
     *
     * @return array|null
     */
    public function mode($key = null)
    {
        $count = $this->count();

        if (0 === $count) {
            return;
        }

        $collection = isset($key) ? $this->pluck($key) : $this;

        $counts = new self();

        $collection->each(static function ($value) use ($counts): void {
            $counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1;
        });

        $sorted = $counts->sort();

        $highestValue = $sorted->last();

        return $sorted->filter(static fn ($value) => $value === $highestValue)->sort()->keys()->all();
    }

    /**
     * Execute a callback over each item.
     *
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this->items as $key => $item) {
            if (false === $callback($item, $key)) {
                break;
            }
        }

        return $this;
    }

    /**
     * Get the last item from the collection.
     *
     * @param ?callable  $callback
     * @param mixed|null $default
     */
    public function last(?callable $callback = null, $default = null)
    {
        return Arr::last($this->items, $callback, $default);
    }

    /**
     * Get the keys of the collection items.
     *
     * @return static
     */
    public function keys()
    {
        return new static(array_keys($this->items));
    }

    /**
     * Run a filter over each of the items.
     *
     * @param ?callable $callback
     *
     * @return static
     */
    public function filter(?callable $callback = null)
    {
        if ($callback) {
            return new static(Arr::where($this->items, $callback));
        }

        return new static(array_filter($this->items));
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string     $key
     * @param mixed|null $value
     *
     * @return static
     */
    public function where($key, $operator, $value = null)
    {
        return $this->filter($this->operatorForWhere(...\func_get_args()));
    }

    /**
     * Get an operator checker callback.
     */
    protected function operatorForWhere(string $key, string $operator, mixed $value = null): \Closure
    {
        if (2 === \func_num_args()) {
            $value = $operator;

            $operator = '=';
        }

        return static function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);

            $strings = array_filter([$retrieved, $value], static fn ($value) => \is_string($value) || (\is_object($value) && method_exists($value, '__toString')));

            if (\count($strings) < 2 && 1 === \count(array_filter([$retrieved, $value], 'is_object'))) {
                return \in_array($operator, ['!=', '<>', '!=='], true);
            }

            switch ($operator) {
                default:
                case '=':
                case '==':
                    return $retrieved === $value;
                case '!=':
                case '<>':
                    return $retrieved !== $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
            }
        };
    }

    /**
     * Determine if an item exists in the collection using strict comparison.
     *
     * @param mixed|null $value
     *
     * @return bool
     */
    public function containsStrict($key, $value = null)
    {
        if (2 === \func_num_args()) {
            return $this->contains(static fn ($item) => data_get($item, $key) === $value);
        }

        if ($this->useAsCallable($key)) {
            return null !== $this->first($key);
        }

        return \in_array($key, $this->items, true);
    }

    /**
     * Determine if an item exists in the collection.
     *
     * @param mixed|null $operator
     * @param mixed|null $value
     *
     * @return bool
     */
    public function contains($key, $operator = null, $value = null)
    {
        if (1 === \func_num_args()) {
            if ($this->useAsCallable($key)) {
                $placeholder = new \stdClass();

                return $this->first($key, $placeholder) !== $placeholder;
            }

            return \in_array($key, $this->items, true);
        }

        return $this->contains($this->operatorForWhere(...\func_get_args()));
    }

    /**
     * Get the first item from the collection.
     *
     * @param ?callable  $callback
     * @param mixed|null $default
     */
    public function first(?callable $callback = null, $default = null)
    {
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * Cross join with the given lists, returning all possible permutations.
     *
     * @return static
     */
    public function crossJoin(...$lists)
    {
        return new static(Arr::crossJoin(
            $this->items,
            ...array_map([$this, 'getArrayableItems'], $lists)
        ));
    }

    /**
     * Dump the collection and end the script.
     *
     * @param array $args
     */
    public function dd(...$args): void
    {
        http_response_code(500);

        \call_user_func_array([$this, 'dump'], $args);

        exit(1);
    }

    /**
     * Dump the collection.
     *
     * @return $this
     */
    public function dump()
    {
        (new static(\func_get_args()))
            ->push($this)
            ->each(static function ($item): void {
                (new Dumper())->dump($item);
            });

        return $this;
    }

    /**
     * Push an item onto the end of the collection.
     *
     * @return $this
     */
    public function push($value)
    {
        $this->offsetSet(null, $value);

        return $this;
    }

    /**
     * Set the item at a given offset.
     */
    public function offsetSet($key, $value): void
    {
        if (null === $key) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Get the items in the collection that are not present in the given items.
     *
     * @return static
     */
    public function diff($items)
    {
        return new static(array_diff($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection that are not present in the given items.
     *
     * @return static
     */
    public function diffUsing($items, callable $callback)
    {
        return new static(array_udiff($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items.
     *
     * @return static
     */
    public function diffAssoc($items)
    {
        return new static(array_diff_assoc($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items.
     *
     * @return static
     */
    public function diffAssocUsing($items, callable $callback)
    {
        return new static(array_diff_uassoc($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @return static
     */
    public function diffKeys($items)
    {
        return new static(array_diff_key($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @return static
     */
    public function diffKeysUsing($items, callable $callback)
    {
        return new static(array_diff_ukey($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Execute a callback over each nested chunk of items.
     *
     * @return static
     */
    public function eachSpread(callable $callback)
    {
        return $this->each(static function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    /**
     * Determine if all items in the collection pass the given test.
     *
     * @param callable|string $key
     * @param mixed|null      $operator
     * @param mixed|null      $value
     *
     * @return bool
     */
    public function every($key, $operator = null, $value = null)
    {
        if (1 === \func_num_args()) {
            $callback = $this->valueRetriever($key);

            foreach ($this->items as $k => $v) {
                if (!$callback($v, $k)) {
                    return false;
                }
            }

            return true;
        }

        return $this->every($this->operatorForWhere(...\func_get_args()));
    }

    /**
     * Get all items except for those with the specified keys.
     *
     * @param \Illuminate\Support\Collection|mixed $keys
     *
     * @return static
     */
    public function except($keys)
    {
        if ($keys instanceof self) {
            $keys = $keys->all();
        } elseif (!\is_array($keys)) {
            $keys = \func_get_args();
        }

        return new static(Arr::except($this->items, $keys));
    }

    /**
     * Apply the callback if the value is falsy.
     *
     * @param bool $value
     */
    public function unless($value, callable $callback, ?callable $default = null)
    {
        return $this->when(!$value, $callback, $default);
    }

    /**
     * Apply the callback if the value is truthy.
     *
     * @param bool $value
     */
    public function when($value, callable $callback, ?callable $default = null)
    {
        if ($value) {
            return $callback($this, $value);
        }
        if ($default) {
            return $default($this, $value);
        }

        return $this;
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     *
     * @return static
     */
    public function whereStrict($key, $value)
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     *
     * @return static
     */
    public function whereInStrict($key, $values)
    {
        return $this->whereIn($key, $values, true);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string $key
     * @param bool   $strict
     *
     * @return static
     */
    public function whereIn($key, $values, $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->filter(static fn ($item) => \in_array(data_get($item, $key), $values, $strict));
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string $key
     *
     * @return static
     */
    public function whereNotInStrict($key, $values)
    {
        return $this->whereNotIn($key, $values, true);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string $key
     * @param bool   $strict
     *
     * @return static
     */
    public function whereNotIn($key, $values, $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->reject(static fn ($item) => \in_array(data_get($item, $key), $values, $strict));
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param callable|mixed $callback
     *
     * @return static
     */
    public function reject($callback)
    {
        if ($this->useAsCallable($callback)) {
            return $this->filter(static fn ($value, $key) => !$callback($value, $key));
        }

        return $this->filter(static fn ($item) => $item !== $callback);
    }

    /**
     * Filter the items, removing any items that don't match the given type.
     *
     * @param string $type
     *
     * @return static
     */
    public function whereInstanceOf($type)
    {
        return $this->filter(static fn ($value) => $value instanceof $type);
    }

    /**
     * Get the first item by the given key value pair.
     *
     * @param string     $key
     * @param mixed|null $value
     *
     * @return static
     */
    public function firstWhere($key, $operator, $value = null)
    {
        return $this->first($this->operatorForWhere(...\func_get_args()));
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param int $depth
     *
     * @return static
     */
    public function flatten($depth = \INF)
    {
        return new static(Arr::flatten($this->items, $depth));
    }

    /**
     * Flip the items in the collection.
     *
     * @return static
     */
    public function flip()
    {
        return new static(array_flip($this->items));
    }

    /**
     * Remove an item from the collection by key.
     *
     * @param array|string $keys
     *
     * @return $this
     */
    public function forget($keys)
    {
        foreach ((array)$keys as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /**
     * Unset the item at a given offset.
     *
     * @param string $key
     */
    public function offsetUnset($key): void
    {
        unset($this->items[$key]);
    }

    /**
     * Group an associative array by a field or using a callback.
     *
     * @param callable|string $groupBy
     * @param bool            $preserveKeys
     *
     * @return static
     */
    public function groupBy($groupBy, $preserveKeys = false)
    {
        if (\is_array($groupBy)) {
            $nextGroups = $groupBy;

            $groupBy = array_shift($nextGroups);
        }

        $groupBy = $this->valueRetriever($groupBy);

        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (!\is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                $groupKey = \is_bool($groupKey) ? (int)$groupKey : $groupKey;

                if (!\array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static();
                }

                $results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
            }
        }

        $result = new static($results);

        if (!empty($nextGroups)) {
            return $result->map->groupBy($nextGroups, $preserveKeys);
        }

        return $result;
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param callable|string $keyBy
     *
     * @return static
     */
    public function keyBy($keyBy)
    {
        $keyBy = $this->valueRetriever($keyBy);

        $results = [];

        foreach ($this->items as $key => $item) {
            $resolvedKey = $keyBy($item, $key);

            if (\is_object($resolvedKey)) {
                $resolvedKey = (string)$resolvedKey;
            }

            $results[$resolvedKey] = $item;
        }

        return new static($results);
    }

    /**
     * Determine if an item exists in the collection by key.
     *
     * @return bool
     */
    public function has($key)
    {
        $keys = \is_array($key) ? $key : \func_get_args();

        foreach ($keys as $value) {
            if (!$this->offsetExists($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Concatenate values of a given key as a string.
     *
     * @param string $value
     * @param string $glue
     *
     * @return string
     */
    public function implode($value, $glue = null)
    {
        $first = $this->first();

        if (\is_array($first) || \is_object($first)) {
            return implode($glue, $this->pluck($value)->all());
        }

        return implode($value, $this->items);
    }

    /**
     * Intersect the collection with the given items.
     *
     * @return static
     */
    public function intersect($items)
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Intersect the collection with the given items by key.
     *
     * @return static
     */
    public function intersectByKeys($items)
    {
        return new static(array_intersect_key(
            $this->items,
            $this->getArrayableItems($items)
        ));
    }

    /**
     * Determine if the collection is not empty.
     *
     * @return bool
     */
    public function isNotEmpty()
    {
        return !$this->isEmpty();
    }

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * Run a map over each nested chunk of items.
     *
     * @return static
     */
    public function mapSpread(callable $callback)
    {
        return $this->map(static function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    /**
     * Run a grouping map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @return static
     */
    public function mapToGroups(callable $callback)
    {
        $groups = $this->mapToDictionary($callback);

        return $groups->map([$this, 'make']);
    }

    /**
     * Run a dictionary map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @return static
     */
    public function mapToDictionary(callable $callback)
    {
        $dictionary = [];

        foreach ($this->items as $key => $item) {
            $pair = $callback($item, $key);

            $key = key($pair);

            $value = reset($pair);

            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }

            $dictionary[$key][] = $value;
        }

        return new static($dictionary);
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @return static
     */
    public function mapWithKeys(callable $callback)
    {
        $result = [];

        foreach ($this->items as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @return static
     */
    public function flatMap(callable $callback)
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static
     */
    public function collapse()
    {
        return new static(Arr::collapse($this->items));
    }

    /**
     * Map the values into a new class.
     *
     * @param string $class
     *
     * @return static
     */
    public function mapInto($class)
    {
        return $this->map(static fn ($value, $key) => new $class($value, $key));
    }

    /**
     * Get the max value of a given key.
     *
     * @param callable|string|null $callback
     */
    public function max($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        return $this->filter(static fn ($value) => null !== $value)->reduce(static function ($result, $item) use ($callback) {
            $value = $callback($item);

            return null === $result || $value > $result ? $value : $result;
        });
    }

    /**
     * Merge the collection with the given items.
     *
     * @return static
     */
    public function merge($items)
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Create a collection by using this collection for keys and another for its values.
     *
     * @return static
     */
    public function combine($values)
    {
        return new static(array_combine($this->all(), $this->getArrayableItems($values)));
    }

    /**
     * Union the collection with the given items.
     *
     * @return static
     */
    public function union($items)
    {
        return new static($this->items + $this->getArrayableItems($items));
    }

    /**
     * Get the min value of a given key.
     *
     * @param callable|string|null $callback
     */
    public function min($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        return $this->filter(static fn ($value) => null !== $value)->reduce(static function ($result, $item) use ($callback) {
            $value = $callback($item);

            return null === $result || $value < $result ? $value : $result;
        });
    }

    /**
     * Create a new collection consisting of every n-th element.
     *
     * @param int $step
     * @param int $offset
     *
     * @return static
     */
    public function nth($step, $offset = 0)
    {
        $new = [];

        $position = 0;

        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $new[] = $item;
            }

            ++$position;
        }

        return new static($new);
    }

    /**
     * Get the items with the specified keys.
     *
     * @return static
     */
    public function only($keys)
    {
        if (null === $keys) {
            return new static($this->items);
        }

        if ($keys instanceof self) {
            $keys = $keys->all();
        }

        $keys = \is_array($keys) ? $keys : \func_get_args();

        return new static(Arr::only($this->items, $keys));
    }

    /**
     * "Paginate" the collection by slicing it into a smaller collection.
     *
     * @param int $page
     * @param int $perPage
     *
     * @return static
     */
    public function forPage($page, $perPage)
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->slice($offset, $perPage);
    }

    /**
     * Slice the underlying collection array.
     *
     * @param int $offset
     * @param int $length
     *
     * @return static
     */
    public function slice($offset, $length = null)
    {
        return new static(\array_slice($this->items, $offset, $length, true));
    }

    /**
     * Partition the collection into two arrays using the given callback or key.
     *
     * @param callable|string $key
     * @param mixed|null      $operator
     * @param mixed|null      $value
     *
     * @return static
     */
    public function partition($key, $operator = null, $value = null)
    {
        $partitions = [new static(), new static()];

        $callback = 1 === \func_num_args()
            ? $this->valueRetriever($key)
            : $this->operatorForWhere(...\func_get_args());

        foreach ($this->items as $k => $item) {
            $partitions[(int)!$callback($item, $k)][$k] = $item;
        }

        return new static($partitions);
    }

    /**
     * Pass the collection to the given callback and return the result.
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
    }

    /**
     * Get and remove the last item from the collection.
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * Push an item onto the beginning of the collection.
     *
     * @param mixed|null $key
     *
     * @return $this
     */
    public function prepend($value, $key = null)
    {
        $this->items = Arr::prepend($this->items, $value, $key);

        return $this;
    }

    /**
     * Push all of the given items onto the collection.
     *
     * @param \Traversable $source
     *
     * @return $this
     */
    public function concat($source)
    {
        $result = new static($this);

        foreach ($source as $item) {
            $result->push($item);
        }

        return $result;
    }

    /**
     * Get and remove an item from the collection.
     *
     * @param mixed|null $default
     */
    public function pull($key, $default = null)
    {
        return Arr::pull($this->items, $key, $default);
    }

    /**
     * Put an item in the collection by key.
     *
     * @return $this
     */
    public function put($key, $value): static
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * Get one or a specified number of items randomly from the collection.
     */
    public function random(?int $number = null)
    {
        if (null === $number) {
            return Arr::random($this->items);
        }

        return new static(Arr::random($this->items, $number));
    }

    /**
     * Reverse items order.
     *
     * @return static
     */
    public function reverse()
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     *
     * @param bool $strict
     */
    public function search($value, $strict = false)
    {
        if (!$this->useAsCallable($value)) {
            return array_search($value, $this->items, $strict);
        }

        foreach ($this->items as $key => $item) {
            if (\call_user_func($value, $item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Get and remove the first item from the collection.
     */
    public function shift()
    {
        return array_shift($this->items);
    }

    /**
     * Shuffle the items in the collection.
     */
    public function shuffle(?int $seed = null): static
    {
        return new static(Arr::shuffle($this->items, $seed));
    }

    /**
     * Split a collection into a certain number of groups.
     */
    public function split(int $numberOfGroups): static
    {
        if ($this->isEmpty()) {
            return new static();
        }

        $groupSize = ceil($this->count() / $numberOfGroups);

        return $this->chunk($groupSize);
    }

    /**
     * Chunk the underlying collection array.
     */
    public function chunk(int $size): static
    {
        if ($size <= 0) {
            return new static();
        }

        $chunks = [];

        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Sort the collection in descending order using the given callback.
     */
    public function sortByDesc(callable|string $callback, int $options = \SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Sort the collection using the given callback.
     */
    public function sortBy(callable|string $callback, int $options = \SORT_REGULAR, bool $descending = false): static
    {
        $results = [];

        $callback = $this->valueRetriever($callback);

        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options)
            : asort($results, $options);

        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Sort the collection keys in descending order.
     *
     * @param int $options
     *
     * @return static
     */
    public function sortKeysDesc($options = \SORT_REGULAR)
    {
        return $this->sortKeys($options, true);
    }

    /**
     * Sort the collection keys.
     *
     * @param int  $options
     * @param bool $descending
     *
     * @return static
     */
    public function sortKeys($options = \SORT_REGULAR, $descending = false)
    {
        $items = $this->items;

        $descending ? krsort($items, $options) : ksort($items, $options);

        return new static($items);
    }

    /**
     * Splice a portion of the underlying collection array.
     */
    public function splice(int $offset, ?int $length = null, array $replacement = []): static
    {
        if (1 === \func_num_args()) {
            return new static(array_splice($this->items, $offset));
        }

        return new static(array_splice($this->items, $offset, $length, $replacement));
    }

    /**
     * Take the first or last {$limit} items.
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @return $this
     */
    public function tap(callable $callback): static
    {
        $callback(new static($this->items));

        return $this;
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @return $this
     */
    public function transform(callable $callback): static
    {
        $this->items = $this->map($callback)->all();

        return $this;
    }

    /**
     * Return only unique items from the collection array using strict comparison.
     */
    public function uniqueStrict(callable|string|null $key = null): static
    {
        return $this->unique($key, true);
    }

    /**
     * Return only unique items from the collection array.
     */
    public function unique(callable|string|null $key = null, bool $strict = false): static
    {
        $callback = $this->valueRetriever($key);

        $exists = [];

        return $this->reject(static function ($item, $key) use ($callback, $strict, &$exists) {
            if (\in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /**
     * Zip the collection together with one or more arrays.
     *
     * e.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]]
     */
    public function zip($items): static
    {
        $arrayableItems = array_map(fn ($items) => $this->getArrayableItems($items), \func_get_args());

        $params = array_merge([static fn () => new static(\func_get_args()), $this->items], $arrayableItems);

        return new static(\call_user_func_array('array_map', $params));
    }

    /**
     * Pad collection to the specified length with a value.
     */
    public function pad(int $size, $value): static
    {
        return new static(array_pad($this->items, $size, $value));
    }

    /**
     * Get the collection of items as a plain array.
     */
    public function toArray(): array
    {
        return array_map(static fn ($value) => $value instanceof Arrayable ? $value->toArray() : $value, $this->items);
    }

    /**
     * Get a CachingIterator instance.
     */
    public function getCachingIterator(int $flags = \CachingIterator::CALL_TOSTRING): \CachingIterator
    {
        return new \CachingIterator($this->getIterator(), $flags);
    }

    /**
     * Get an iterator for the items.
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Get a base Support collection instance from this collection.
     */
    public function toBase(): self
    {
        return new self($this);
    }

    /**
     * Get an item at a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }
}
