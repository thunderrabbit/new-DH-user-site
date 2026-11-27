<?php

declare(strict_types=1);

namespace Config;

/**
 * The site's configuration is missing, unreadable or wrong.
 *
 * The message names the key, never its value: it is logged, and a value
 * may be the database password.
 */
class EConfigInvalid extends \Exception
{
}
