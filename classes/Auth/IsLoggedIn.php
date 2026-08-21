<?php

/**
 * This file tries to simplify knowing if user is logged in.
 *
 *
 */

namespace Auth;

class IsLoggedIn
{
    private int $who_is_logged_in = 0;

    private string $loggedInUsername = 'YUNOset?'; // default value, should be overwritten if user is logged in
    public function __construct(
        private \PDO $di_pdo,
        private \Config\Config $di_config,
        private RandomToken $di_token,
        private LoginThrottle $di_throttle,
        private \Database\CookieRepository $di_cookies,
    ) {
    }

    /**
     * Who is this request from? Runs on EVERY request (prepend.php) and is
     * read-only apart from expiring a cookie the database no longer knows.
     * It never looks at credentials: that is attemptPasswordLogin()'s job,
     * and only the login page calls that.
     */
    public function resumeFromCookie(\Mlaphp\Request $mla_request): void
    {
        $cookie = $mla_request->cookie[$this->di_config->cookie_name] ?? '';
        if (!is_string($cookie) || $cookie === '') {
            return;
        }

        $found_user_id = $this->getUserIdForCookieInDatabase(
            cookie: $cookie,
            ip_address: $_SERVER['REMOTE_ADDR'] ?? '',
            user_agent: $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        if ($found_user_id <= 0) {
            $this->killCookie();
            return;
        }

        $this->who_is_logged_in = $found_user_id;
        $this->setUsernameOfLoggedInID($found_user_id);
    }

    /**
     * Password login. Called by /login/ on POST and nowhere else, so a stray
     * username/pass pair in some other form is just data, and a wrong
     * password can no longer log a user out of an unrelated page.
     *
     * BadCredentials has no side effects; the caller shows one generic
     * message for it so a guesser cannot learn which usernames exist.
     */
    public function attemptPasswordLogin(string $username, string $password): LoginResult
    {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($this->di_throttle->isThrottled($username, $ip_address)) {
            // Do not even look at the password: a throttled guess must cost
            // the attacker nothing in information and us nothing in bcrypt.
            return LoginResult::Throttled;
        }

        $user_id = $this->checkPHPHashedPassword($username, $password);
        if ($user_id <= 0) {
            $this->di_throttle->recordFailure($username, $ip_address);
            return LoginResult::BadCredentials;
        }

        $this->di_throttle->clearFailures($username);
        $this->establishSession($user_id);
        return LoginResult::Success;
    }

    /**
     * The one way to become logged in. EVERY authentication path (password
     * today; emailed sign-in links, OAuth, whatever comes next) must end by
     * calling this and nothing else, so no path can skip the fixation guard
     * or end up with different persistence from the others.
     */
    private function establishSession(int $user_id): void
    {
        // Fresh privilege level → fresh session id (fixation guard): a
        // pre-set session id must not survive the authentication boundary.
        session_regenerate_id(true);
        // regenerate_id keeps the session data, so a CSRF token minted (or
        // fixated) before login would survive it. Drop it; the next page
        // render mints a fresh one.
        unset($_SESSION[\Security\CSRFProtectaroo::FIELD]);
        $this->setAutoLoginCookie($user_id);
        $this->who_is_logged_in = $user_id;
        $this->setUsernameOfLoggedInID($user_id);
    }

    private function setUsernameOfLoggedInID(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }
        // set the session variable for username
        $stmt = $this->di_pdo->prepare("SELECT `username` FROM `users` WHERE `user_id` = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $result = $stmt->fetchAll();

        if (count($result) > 0) {
            $this->loggedInUsername = $result[0]['username'] ?? 'ummmmmm wtf';
        }
    }

    public function getLoggedInUsername(): string
    {
        return $this->loggedInUsername;
    }

    public function getUserRole(): string
    {
        if ($this->who_is_logged_in <= 0) {
            return '';
        }

        $stmt = $this->di_pdo->prepare("SELECT `role` FROM `users` WHERE `user_id` = ? LIMIT 1");
        $stmt->execute([$this->who_is_logged_in]);
        $result = $stmt->fetchAll();

        if (count($result) > 0) {
            return $result[0]['role'] ?? '';
        }
        return '';
    }

    public function isAdmin(): bool
    {
        return $this->getUserRole() === 'admin';
    }
    private function setAutoLoginCookie(int $user_id): void
    {
        $cookie = $this->di_token->generate(32);
        $expires_ts = time() + $this->di_config->cookie_lifetime; // 30 days

        // Store only the SHA-256 of the token: a leaked DB dump/backup must
        // not contain ready-to-use session tokens. The browser holds the
        // plaintext; lookups hash the presented value (see
        // getUserIdForCookieInDatabase). The row carries its own expires_at,
        // so the lifetime is enforced here and not only by the browser.
        $this->di_cookies->issue(
            user_id: $user_id,
            cookie_hash: hash('sha256', $cookie),
            ip_bin: \Auth\IPBin::ipToBinary($_SERVER['REMOTE_ADDR'] ?? ''),
            user_agent_md5: md5($_SERVER['HTTP_USER_AGENT'] ?? ''),
            lifetime_seconds: $this->di_config->cookie_lifetime,
        );

        $cookie_options = \Auth\CookieOptions::build(
            $this->di_config->domain_name,
            $expires_ts
        );
        setcookie($this->di_config->cookie_name, $cookie, $cookie_options);
    }



    private function getIDandPHPHashedPasswordForUsername($username)
    {
        // get password hash
        $stmt = $this->di_pdo->prepare(
            "SELECT `user_id`, `password_hash` FROM `users` WHERE LOWER(`username`) = LOWER(?) LIMIT 1"
        );
        $stmt->execute([$username]);
        $result = $stmt->fetchAll();

        if (count($result) > 0) {
            return $result[0];
        } else {
            return [];
        }
    }
    /**
     * Looks up hashed password for username, and checks it against the password provided
     * @param $username
     * @param $password
     * @return bool
     */
    private function checkPHPHashedPassword($username, $password): int
    {
        // get password hash
        $user_id_and_hash_array = $this->getIDandPHPHashedPasswordForUsername($username);
        if (!empty($user_id_and_hash_array)) {
            $hashed_password = $user_id_and_hash_array['password_hash'];
            // check it
            if (password_verify($password, $hashed_password)) {
                // password is correct, so this user_id has logged in properly
                $user_id = $user_id_and_hash_array['user_id'];
                // return user_id
                return $user_id;
            }
        } else {
            return 0;
        }

        return 0;
    }


    private function getUserIdForCookieInDatabase(
        string $cookie,
        string $ip_address,
        string $user_agent
    ): int {
        return $this->di_cookies->findUserId(
            cookie_hash: hash('sha256', $cookie),
            ip_bin: \Auth\IPBin::ipToBinary($ip_address),
            user_agent_md5: md5($user_agent),
        );
    }
    public function isLoggedIn(): bool
    {
        return $this->who_is_logged_in > 0;
    }

    public function loggedInID(): int
    {
        return $this->who_is_logged_in;
    }


    public function logout(): void
    {
        // Nobody is logged in, so there is nothing to revoke and no session of
        // ours to tear down. resumeFromCookie() has already cleared any cookie
        // that failed to resolve, so this is a no-op rather than a cleanup pass.
        if ($this->who_is_logged_in <= 0) {
            return;
        }

        // Revoke every session this user has, not only the browser that clicked
        // logout. Someone logging out because they think they were compromised
        // gets what they asked for, and a token captured from any of their
        // devices stops working now instead of at its expiry.
        $this->di_cookies->revokeAllForUser($this->who_is_logged_in);

        $this->who_is_logged_in = 0;
        $this->killCookie();
        session_destroy();
        session_start();
        session_regenerate_id();
    }

    private function killCookie(): void
    {
        $cookie_options = \Auth\CookieOptions::build(
            $this->di_config->domain_name,
            time() - 3600
        );
        setcookie($this->di_config->cookie_name, '', $cookie_options);
        $this->who_is_logged_in = 0;
    }
}
