<?php

namespace Biboletin\Container;

use Biboletin\Container\Exceptions\ContainerException;
use Biboletin\Container\Exceptions\NotFoundException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Class Container
 * A simple dependency injection container implementation.
 */
class Container implements ContainerInterface
{
    /**
     * Bindings array to hold service definitions.
     * This array maps service identifiers to their concrete implementations.
     *
     * @var array 
     */
    protected array $bindings = [];

    /**
     * Instances array to hold singleton instances.
     * This array maps service identifiers to their instantiated objects.
     *
     * @var array 
     */
    protected array $instances = [];

    /**
     * Parameters array to hold configuration parameters.
     * This array maps parameter identifiers to their values.
     *
     * @var array 
     */
    protected array $parameters = [];

    /**
     * Set a binding in the container.
     * This method allows you to define a service identifier and its concrete implementation.
     * You can also specify if the binding should be a singleton.
     * 
     * @param string   $id
     * @param callable $concrete
     * @param bool     $singleton
     *
     * @return void
     */
    public function set(string $id, callable $concrete, bool $singleton = false): void
    {
        $this->bindings[$id] = compact('concrete', 'singleton');
    }

    /**
     * Register a service as a singleton.
     * This method is a convenience method to register a service that should only have one instance.
     *
     * @param string   $id
     * @param callable $concrete
     *
     * @return void
     */
    public function singleton(string $id, callable $concrete): void
    {
        $this->set($id, $concrete, true);
    }

    /**
     * Get a parameter from the container.
     * This method retrieves a configuration parameter by its identifier.
     *
     * @param string $id
     *
     * @return mixed|null
     */
    public function getParameter(string $id): mixed
    {
        return $this->parameters[$id] ?? null;
    }

    /**
     * Set a parameter in the container.
     * This method allows you to define a configuration parameter with its value.
     *
     * @param string $id
     * @param mixed  $value
     *
     * @return void
     */
    public function setParameter(string $id, mixed $value): void
    {
        $this->parameters[$id] = $value;
    }

    /**
     * Get a service from the container.
     * This method retrieves a service by its identifier. If the service is not bound,
     * it attempts to autowire it based on its class definition.
     *
     * @param string $id
     *
     * @return mixed
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function get(string $id): mixed
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

    /**
     * Check if a service is bound in the container.
     * This method checks if a service identifier exists in the bindings or if the class exists.
     *
     * @param string $id
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || class_exists($id);
    }

    /**
     * Autowire a class based on its constructor dependencies.
     * This method uses reflection to inspect the class constructor
     * and resolve its dependencies automatically.
     * It throws an exception if the class is not instantiable
     * or if a parameter cannot be resolved.
     *
     * @param string $id
     *
     * @return object
     *
     * @throws ContainerException
     * @throws ContainerExceptionInterface
     * @throws NotFoundException
     * @throws NotFoundExceptionInterface
     */
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

    /**
     * Magic method to set a binding in the container.
     * This allows you to use the container as an array-like structure.
     *
     * @param string   $id
     * @param callable $concrete
     *
     * @return void
     */
    public function __set(string $id, callable $concrete): void
    {
        $this->set($id, $concrete);
    }

    /**
     * Magic method to get a service from the container.
     * This allows you to use the container as an array-like structure.
     * It retrieves the service by its identifier.
     * If the service is not bound, it attempts to autowire it.
     * This method throws an exception if the service is not found.
     * It is useful for accessing services without explicitly calling the `get` method.
     * It is important to note that this method should not be used for services that require parameters,
     * as it will not handle parameter resolution.
     * It is recommended to use the `get` method for services that require parameters.
     * This method is also useful for accessing services that are registered as singletons,
     * as it will return the same instance every time it is called.
     * It is important to ensure that the service identifier is valid and that the service is properly registered in the container.
     * This method is a convenient way to access services in the container without having to call the `get` method explicitly.
     * 
     * @param string $id
     *
     * @return mixed
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __get(string $id)
    {
        return $this->get($id);
    }

    /**
     * Magic method to check if a service is bound in the container.
     * This allows you to use the container as an array-like structure.
     * It checks if the service identifier exists in the bindings.
     *
     * @param string $id
     *
     * @return bool
     */
    public function __isset(string $id): bool
    {
        return $this->has($id);
    }

    /**
     * Magic method to unset a service binding in the container.
     * This allows you to remove a service from the container.
     * It removes the service identifier from the bindings and instances arrays.
     *
     * @param string $id
     *
     * @return void
     */
    public function __unset(string $id): void
    {
        unset($this->bindings[$id], $this->instances[$id]);
    }

    /**
     * Magic method to call a method on the container.
     * This allows you to call methods on the container as if it were an object.
     * It checks if the method exists in the container and calls it with the provided arguments.
     * If the method does not exist, it throws a ContainerException.
     * This method is useful for accessing container methods dynamically,
     * such as when you want to call a method that is not explicitly defined in the container class.
     * @param string $method
     *                      The name of the method to call.
     *                      This method should be a valid method name that exists in the container class.
     *                      The method can be any public method defined in the container class.
     * @param array  $args
     *                    The arguments to pass to the method.
     *                    This should be an array of arguments that the method expects.
     *                    The arguments can be any type, including other services from the container.
     *                    This method is useful for dynamically calling methods on the container,
     *                    as it allows you to pass any number of arguments to the method.
     *                    This method is also useful for calling methods that require parameters,
     *                    as it allows you to pass the parameters directly to the method.
     *                    This method is not intended for calling methods that require specific parameters,
     *                    as it does not handle parameter resolution.
     *
     * @return mixed
     * @throws ContainerException
     */
    public function __call(string $method, array $args): mixed
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$args);
        }

        throw new ContainerException("Method '$method' does not exist in the container.");
    }
}
