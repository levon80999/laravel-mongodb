<?php

namespace Jenssegers\Mongodb\Concerns;

use Closure;
use Exception;
use MongoDB\Driver\Session;

use function MongoDB\with_transaction;

trait TransactionManager
{
    /**
     * create a session and start a transaction in session
     *
     * In version 4.0, MongoDB supports multi-document transactions on replica sets.
     * In version 4.2, MongoDB introduces distributed transactions, which adds support for multi-document transactions on sharded clusters and incorporates the existing support for multi-document transactions on replica sets.
     * To use transactions on MongoDB 4.2 deployments(replica sets and sharded clusters), clients must use MongoDB drivers updated for MongoDB 4.2.
     *
     * @see https://docs.mongodb.com/manual/core/transactions/
     * @return void
     */
    public function beginTransaction(?array $options = [])
    {
        $session = $this->getSession();

        if (!$session) {
            $this->session_key = uniqid();
            $session = $this->connection->startSession();
            $this->sessions[$this->session_key] = $session;
        }

        $session->startTransaction($options);
    }

    /**
     * commit transaction in this session and close this session
     * @return void
     */
    public function commit()
    {
        if ($session = $this->getSession()) {
            $session->commitTransaction();
            $this->setLastSession();
        }
    }

    /**
     * rollback transaction in this session and close this session
     * @return void
     */
    public function rollBack($toLevel = null)
    {
        if ($session = $this->getSession()) {
            $session->abortTransaction();
            $this->setLastSession();
        }
    }

    /**
     * close this session and get last session key to session_key
     * Why do it ? Because nested transactions
     * @return void
     */
    protected function setLastSession()
    {
        if ($session = $this->getSession()) {
            $session->endSession();
            unset($this->sessions[$this->session_key]);
            if (empty($this->sessions)) {
                $this->session_key = null;
            } else {
                end($this->sessions);
                $this->session_key = key($this->sessions);
            }
        }
    }

    /**
     * get now session if it has session
     * @return Session|null
     */
    public function getSession(): ?Session
    {
        return $this->sessions[$this->session_key] ?? null;
    }

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
