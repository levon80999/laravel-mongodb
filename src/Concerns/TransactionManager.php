<?php

namespace Jenssegers\Mongodb\Concerns;

use Closure;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Session;
use function MongoDB\with_transaction;

trait TransactionManager
{
    /**
     * A list of transaction session.
     */
    protected ?Session $session = null;

    /**
     * Get the existing session or null.
     */
    public function getSession(): ?Session
    {
        return $this->session;
    }

    private function getSessionOrThrow(): Session
    {
        $session = $this->getSession();

        if ($session === null) {
            throw new RuntimeException('There is no active session.');
        }

        return $session;
    }

    /**
     * Use the existing or create new session and start a transaction in session.
     *
     * In version 4.0, MongoDB supports multi-document transactions on replica sets.
     * In version 4.2, MongoDB introduces distributed transactions, which adds support for multi-document transactions on sharded clusters and incorporates the existing support for multi-document transactions on replica sets.
     *
     * @see https://docs.mongodb.com/manual/core/transactions/
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
     */
    public function commit(): void
    {
        $this->getSessionOrThrow()->commitTransaction();
    }

    /**
     * Rollback transaction in this session and close this session.
     */
    public function rollBack($toLevel = null): void
    {
        $this->getSessionOrThrow()->abortTransaction();
    }

    /**
     * Static transaction function realize the with_transaction functionality provided by MongoDB.
     *
     * @param int $attempts
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
