<?php

declare(strict_types=1);

namespace Auth;

/**
 * Outcome of IsLoggedIn::attemptPasswordLogin(). A bool could not tell the
 * login page "wrong password" from "stop trying for a while", and the page
 * needs to say different things for those.
 */
enum LoginResult
{
    case Success;
    case BadCredentials;
    case Throttled;
}
