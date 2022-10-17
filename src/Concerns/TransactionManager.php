<?php

namespace Jenssegers\Mongodb\Concerns;

use Closure;
use MongoDB\Driver\Session;
use function MongoDB\with_transaction;

trait TransactionManager
{
    /**
     * A list of transaction session.
     * @var Session|null
     */
    protected ?Session $session;

    /**
     * Get the existing session or null.
     */
    public function getSession(): ?Session
    {
        return $this->session ?? null;
    }

    /**
     * Use the existing or create new session and start a transaction in session.
     *
     * In version 4.0, MongoDB supports multi-document transactions on replica sets.
     * In version 4.2, MongoDB introduces distributed transactions, which adds support for multi-document transactions on sharded clusters and incorporates the existing support for multi-document transactions on replica sets.
     *
     * @see https://docs.mongodb.com/manual/core/transactions/
     * @param array $options
     * @return void
     */
    public function beginTransaction(array $options = []): void
    {
        $session = $this->getSession();

        if ($session === null) {
            $session = $this->connection->startSession();
            $this->session = $session;
        }

        $session->startTransaction($options);
    }

    /**
     * Commit transaction in this session and close this session.
     * @return void
     */
    public function commit(): void
    {
        $session = $this->getSession();

        $session?->commitTransaction();
    }

    /**
     * Rollback transaction in this session and close this session.
     * @param null $toLevel
     * @return void
     */
    public function rollBack($toLevel = null): void
    {
        $session = $this->getSession();

        $session?->abortTransaction();
    }

    /**
     * Static transaction function realize the with_transaction functionality provided by MongoDB.
     *
     * @param Closure $callback
     * @param int $attempts
     * @param array $options
     */
    public function transaction(Closure $callback, $attempts = 1, array $options = []): mixed
    {
        $attemptsLeft = $attempts;
        $callbackResult = null;
        $session = $this->getSession();

        if ($session === null) {
            $session = $this->connection->startSession();
            $this->session = $session;
        }

        $callbackFunction = function (Session $session) use ($callback, &$attemptsLeft, &$callbackResult) {
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
