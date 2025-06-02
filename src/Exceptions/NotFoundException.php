<?php

namespace Biboletin\Container\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class NotFoundException extends Exception implements ContainerExceptionInterface
{
}
