<?php

declare(strict_types=1);

namespace CoinBet\Shared\Domain\Exception;

use Exception;

abstract class DomainException extends Exception
{
    public function statusCode(): int
    {
        return 422;
    }
}
