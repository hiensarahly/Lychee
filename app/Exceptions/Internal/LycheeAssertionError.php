<?php

namespace App\Exceptions\Internal;

use App\Contracts\InternalLycheeException;

class LycheeAssertionError extends \AssertionError implements InternalLycheeException
{
	public function __construct(string $msg, \Throwable $previous = null)
	{
		parent::__construct($msg, $previous->getCode(), $previous);
	}

	public static function createFromUnexpectedException(\Throwable $previous): self
	{
		return new self('Unexpected exception: ' . get_class($previous), $previous);
	}
}
