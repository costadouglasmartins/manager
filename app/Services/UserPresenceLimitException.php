<?php

namespace App\Services;

use RuntimeException;

/**
 * Signals that a tenant has no remaining online user capacity.
 **/
class UserPresenceLimitException extends RuntimeException
	{
	}
