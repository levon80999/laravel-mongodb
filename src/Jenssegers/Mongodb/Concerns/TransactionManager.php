<?php

namespace Jenssegers\Mongodb\Concerns;

use Closure;
use Exception;
use MongoDB\Driver\Session;

use function MongoDB\with_transaction;

trait TransactionManager
{
    /**
     * Static transaction function realize the with_transaction functionality provided by MongoDB.
     *
     * @see https://www.mongodb.com/docs/manual/core/transactions/
     *
     * @param Closure $callback
     * @param int $attempts
     * @param array $options
     *
     * @return Exception|mixed|null
     * @throws Exception
     */
    public function transaction(Closure $callback, $attempts = 1, array $options = [])
    {
        $session = $this->connection->startSession();
        $attemptsLeft = $attempts;
        $callbackResult = null;

        $callbackFunction = function(Session $session) use ($callback, &$attemptsLeft, &$callbackResult) {
            $attemptsLeft--;

            if ($attemptsLeft < 0) {
                $session->abortTransaction();
                return;
            }

            $callbackResult = $callback();
        };

        with_transaction($session, $callbackFunction, $options);

        return $callbackResult;
    }
}
