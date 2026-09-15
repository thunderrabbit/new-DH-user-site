<?php

/**
 * This file is part of "Modernizing Legacy Applications in PHP".
 *
 * @copyright 2014-2023 Paul M. Jones <pmjones88@gmail.com>
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace Mlaphp;

use DomainException;
use InvalidArgumentException;

/**
 * A data structure object to encapsulate superglobal references.
 *
 * This version of the Request object is for use on PHP 8.1 and later, but
 * should work back to as far as PHP 5.6 (or even earlier).
 *
 * PHP 5.3, which was the current version when the original Request object was
 * created, allowed passing of $GLOBALS by reference. However, PHP 8.1 does not.
 * That means the original Request object will not work on PHP 8.1 and later,
 * whereas this version does.
 *
 * Note that $_SESSION works slightly differently than from the original Request
 * object. The implementation differences should not have any practical effect
 * when using Request81 as vs. the original Request.
 *
 * @package mlaphp/mlaphp
 *
 * @property array<mixed> $session A reference to $_SESSION; see __get().
 */
class Request
{
    /**
     * A copy of $_COOKIE.
     *
     * @var array<mixed>
     */
    public array $cookie = [];

    /**
     * A copy of $_ENV.
     *
     * @var array<mixed>
     */
    public array $env = [];

    /**
     * A copy of $_FILES.
     *
     * @var array<mixed>
     */
    public array $files = [];

    /**
     * A copy of $_GET.
     *
     * @var array<mixed>
     */
    public array $get = [];

    /**
     * A copy of $_POST.
     *
     * @var array<mixed>
     */
    public array $post = [];

    /**
     * A copy of $_REQUEST.
     *
     * @var array<mixed>
     */
    public array $request = [];

    /**
     * A copy of $_SERVER.
     *
     * @var array<mixed>
     */
    public array $server = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->cookie = $_COOKIE;
        $this->env = $_ENV;
        $this->files = $_FILES;
        $this->get = $_GET;
        $this->post = $_POST;
        $this->request = $_REQUEST;
        $this->server = $_SERVER;
    }

    /**
     * Provides a magic **reference** to $_SESSION.
     *
     * @param string $name The property name; must be 'session'.
     * @return array<mixed> A reference to $_SESSION.
     * @throws InvalidArgumentException for any $name other than 'session'.
     * @throws DomainException when $_SESSION is not set.
     */
    public function &__get(string $name): array
    {
        if ($name != 'session') {
            throw new InvalidArgumentException($name);
        }

        if (!isset($_SESSION)) {
            throw new DomainException('$_SESSION is not set');
        }

        return $_SESSION;
    }

    /**
     * Provides magic isset() for $_SESSION and the related property.
     *
     * @param string $name The property name; must be 'session'.
     * @return bool
     */
    public function __isset(string $name): bool
    {
        if ($name != 'session') {
            throw new InvalidArgumentException();
        }

        return isset($_SESSION);
    }

    /**
     * Provides magic unset() for $_SESSION; unsets both the property and the
     * superglobal.
     *
     * @param string $name The property name; must be 'session'.
     */
    public function __unset(string $name): void
    {
        if ($name != 'session') {
            throw new InvalidArgumentException();
        }

        unset($_SESSION);
    }
}
