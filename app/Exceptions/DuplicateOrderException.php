<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

class DuplicateOrderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly Order $existingOrder
    ) {
        parent::__construct($message);
    }
}
