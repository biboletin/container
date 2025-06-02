<?php

namespace Biboletin\Container;

use Biboletin\Container\Exceptions\ContainerException;
use Biboletin\Container\Exceptions\NotFoundException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;

class Container implements ContainerInterface
{
    protected array $bindings = [];
    protected array $instances = [];
    protected array $parameters = [];

    public function set(string $id, callable $concrete, bool $singleton = false): void
    {
        $this->bindings[$id] = compact('concrete', 'singleton');
    }

    public function singleton(string $id, callable $concrete): void
    {
        $this->set($id, $concrete, true);
    }

    public function getParameter(string $id)
    {
        return $this->parameters[$id] ?? null;
    }

    public function setParameter(string $id, $value): void
    {
        $this->parameters[$id] = $value;
    }

    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            $object = $binding['concrete']($this);

            if ($binding['singleton']) {
                $this->instances[$id] = $object;
            }

            return $object;
        }

        return $this->autowire($id);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || class_exists($id);
    }

    protected function autowire(string $id): object
    {
        try {
            $reflector = new ReflectionClass($id);

            if (!$reflector->isInstantiable()) {
                throw new ContainerException("Class $id is not instantiable.");
            }

            $constructor = $reflector->getConstructor();

            if (!$constructor) {
                return new $id();
            }

            $dependencies = [];

            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();

                if ($type === null) {
                    throw new ContainerException("Cannot resolve parameter \${$param->getName()} in $id");
                }

                if ($type->isBuiltin()) {
                    $name = $param->getName();
                    $dependencies[] = $this->getParameter($name);
                } else {
                    $dependencies[] = $this->get($type->getName());
                }
            }

            return $reflector->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new NotFoundException("Class '" . $id . "' not found.", 0, $e);
        }
    }

    public function __set(string $id, callable $concrete): void
    {
        $this->set($id, $concrete);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __get(string $id)
    {
        return $this->get($id);
    }

    public function __isset(string $id): bool
    {
        return $this->has($id);
    }

    public function __unset(string $id): void
    {
        unset($this->bindings[$id], $this->instances[$id]);
    }

    /**
     * @throws ContainerException
     */
    public function __call(string $method, array $args)
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$args);
        }

        throw new ContainerException("Method '$method' does not exist in the container.");
    }
}
