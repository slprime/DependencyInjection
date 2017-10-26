<?php

namespace slprime\DependencyInjection;

use stdClass, Exception, ArgumentCountError, ReflectionClass;

class Container {

    protected $services = [];

    protected $instances = [];

    /**
     * DependencyInjection constructor.
     * @param array|string $services
     * @throws Exception
     */
    public function __construct($services = []) {

        if (is_string($services)) {
            $this->import($services);
        } else if (!empty($services)) {

            foreach ($services as $service_name => $loader) {
                $this->register($service_name, $loader);
            }

        }

    }

    /**
     * @param string $service_name
     * @param string|object|callable|array $loader
     * @throws Exception
     */
    public function register($service_name, $loader){

        if (isset($this->services[$service_name])) {
            throw new Exception("The service '$service_name' is already registered");
        }

        if (!is_string($loader) && !is_callable($loader) && (!is_array($loader) || !isset($loader[0])) && !is_object($loader)) {
            throw new Exception("Incorrect format 'loader' of '$service_name'");
        }

        $this->services[$service_name] = $loader;

    }

    public function __clone() {
        $this->instances = [];
    }

    /**
     * @param string $file
     * @throws Exception
     */
    public function import(string $file) {

        if (!file_exists($file) || !is_file($file)) {
            throw new \InvalidArgumentException("file `$file` not found");
        }

        $services = include $file;

        foreach ($services as $service_name => $loader) {
            $this->register($service_name, $loader);
        }

    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws Exception
     */
    public function __set($name, $value) {
        throw new Exception('Property of DI is readonly');
    }

    /**
     * @param string $name
     * @throws Exception
     */
    public function __unset($name) {
        throw new Exception('Property of DI is readonly');
    }

    public function __isset($name) {
        return isset($this->services[$name]);
    }

    /**
     * @param string $name
     * @return mixed|stdClass
     * @throws ArgumentCountError
     * @throws Exception
     */
    public function __get($name) {

        if (!isset($this->instances[$name])) {

            if (!isset($this->services[$name])) {
                throw new Exception("Service '$name' does not exist");
            }

            $loader = $this->services[$name];

            if ($loader instanceof Constant) {
                $this->instances[$name] = $loader->value();
            } elseif (is_object($loader) && !is_callable($loader)) {
                $this->instances[$name] = $loader;
            } elseif (is_array($loader)) {
                $this->instances[$name] = $this->newInstanceArgs($loader[0], $loader);
            } else {
                $this->instances[$name] = $this->__call($name, []);
            }

        }

        return $this->instances[$name];
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|object
     * @throws ArgumentCountError
     * @throws Exception
     */
    public function __call($name, array $arguments = []) {

        if (!isset($this->services[$name])) {
            throw new Exception("Service '$name' does not exist");
        }

        $loader = $this->services[$name];

        if (is_array($loader)) {
            return $this->newInstanceArgs($loader[0], $loader);
        }elseif (is_string($loader)) {
            return $this->newInstanceArgs($loader, $arguments);
        } elseif (is_callable($loader)) {
            return call_user_func_array($loader->bindTo($this), $arguments);
        } else {
            throw new Exception("Service '$name' con`t be create");
        }

    }

    /**
     * @param string $className
     * @param array $arguments
     * @return object
     * @throws ArgumentCountError
     */
    private function newInstanceArgs($className, array &$arguments){
        $ref = new ReflectionClass($className);
        $constructor = $ref->getConstructor();

        if (!$constructor) {
            return new $className();
        }

        $params = $constructor->getParameters();
        $arg = [];

        $arguments['di'] = $this;

        foreach ($params as $param) {
            $name = $param->getName();

            if (isset($arguments[$name])) {

                if ($param->isPassedByReference()){
                    $arg[] = &$arguments[$name];
                } else {
                    $arg[] = $arguments[$name];
                }

            } elseif ($param->isOptional()) {
                $arg[] = $param->getDefaultValue();
            } else {
                throw new ArgumentCountError("Too few arguments to function $className::__construct(): not set '$name' parameter");
            }

        }

        return $ref->newInstanceArgs($arg);
    }

}