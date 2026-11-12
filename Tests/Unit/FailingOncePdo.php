<?php

declare(strict_types=1);

namespace Tests\Unit;

/**
 * An in-memory SQLite whose first prepare() throws the exception it was given;
 * every later one works normally.
 *
 * It lets a test hand a class the error MySQL would raise (SQLite words the
 * same failures differently and uses SQLSTATE HY000 for all of them), and lets
 * it see whether the class tried again after an error it should not swallow.
 */
final class FailingOncePdo extends \PDO
{
    private bool $has_failed = false;

    public function __construct(private readonly \PDOException $failure)
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * A PDOException carrying a driver's SQLSTATE, which is a string and so
     * cannot go through the constructor's int $code.
     */
    public static function sqlState(string $sqlstate, string $message): \PDOException
    {
        return new class ($sqlstate, $message) extends \PDOException {
            public function __construct(string $sqlstate, string $message)
            {
                parent::__construct($message);
                $this->code = $sqlstate;
            }
        };
    }

    /**
     * @param array<mixed> $options
     */
    #[\Override]
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        if (!$this->has_failed) {
            $this->has_failed = true;
            throw $this->failure;
        }
        return parent::prepare($query, $options);
    }
}
