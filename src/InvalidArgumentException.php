<?php

declare(strict_types=1);

namespace Jasny\VarCache;

class InvalidArgumentException extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException
{
}
