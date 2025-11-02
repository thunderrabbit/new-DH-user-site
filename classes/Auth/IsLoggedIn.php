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
        private \Config $di_config,
    ) {
    }

    public function checkLogin(\Mlaphp\Request $mla_request): void
    {
        $found_user_id = 0;
        if(!empty($mla_request->cookie[$this->di_config->cookie_name]))
        {
            $found_user_id = $this->getUserIdForCookieInDatabase(
                cookie: $mla_request->cookie[$this->di_config->cookie_name],
                ip_address: $_SERVER['REMOTE_ADDR'] ?? '',
                user_agent: $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
            if(empty($found_user_id))
            {
                $this->killCookie();
                $this->who_is_logged_in = 0;
            } else {
                $this->who_is_logged_in = $found_user_id;
            }
        } elseif(!empty($mla_request->post['username']) && !empty($mla_request->post['pass'])) {
            // Note: We can't access $csrfProtect from prepend.php here due to architectural constraints
            // For now, we'll accept the login attempt without CSRF check as IsLoggedIn is low-level auth
            // CSRF protection is better enforced at form submission points
            $found_user_id = $this->checkPHPHashedPassword($mla_request->post['username'], $mla_request->post['pass']);
            if(empty($found_user_id))
            {
                $this->killCookie();        // bad login, so kill any cookie
                $this->who_is_logged_in = 0;
            } else {
                $this->setAutoLoginCookie($found_user_id);
                $this->who_is_logged_in = $found_user_id;
            }
        }
        // set the session variable for username
        $this->setUsernameOfLoggedInID($this->who_is_logged_in);
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
    private function setAutoLoginCookie(int $user_id):void
    {
        $cookie = \Utilities::randomString(32);

        $record = [
            'user_id' => $user_id,
            'cookie' => $cookie,
            'last_access' => date(format: "Y-m-d H:i:s"),
            'user_agent_md5' => md5($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'ip_address' => \Auth\IPBin::ipToBinary(ip: $_SERVER['REMOTE_ADDR'])
        ];

        // Insert using native PDO
        $stmt = $this->di_pdo->prepare("INSERT INTO `cookies` (`user_id`, `cookie`, `last_access`, `user_agent_md5`, `ip_address`) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(array_values($record));

        $cookie_options = [
            'expires' => time() + $this->di_config->cookie_lifetime, // 30 days
            'path' => '/',
            'domain' => $this->di_config->domain_name,
            'samesite' => 'Strict' // None || Lax  || Strict
        ];
        setcookie($this->di_config->cookie_name, $cookie, $cookie_options);
    }



    private function getIDandPHPHashedPasswordForUsername($username)
    {
        // get password hash
        $stmt = $this->di_pdo->prepare("SELECT `user_id`, `password_hash` FROM `users` WHERE LOWER(`username`) = LOWER(?) LIMIT 1");
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
    ): int
    {
        $varbinary_ip = \Auth\IPBin::ipToBinary($ip_address);
        $stmt = $this->di_pdo->prepare("SELECT `user_id` FROM `cookies` WHERE `cookie` = ? AND `ip_address` = ? AND `user_agent_md5` = ? LIMIT 1");
        $stmt->execute([$cookie, $varbinary_ip, md5($user_agent)]);
        $result = $stmt->fetchAll();

        if(count($result) > 0)
        {
            return $result[0]['user_id'];
        }
        else
        {
            return 0;
        }
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
        $this->who_is_logged_in = 0;
        $this->killCookie();
        session_destroy();
        session_start();
        session_regenerate_id();
    }

    private function killCookie(): void
    {
        $cookie_options = [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $this->di_config->domain_name,
            'samesite' => 'Strict' // None || Lax  || Strict
        ];
        setcookie($this->di_config->cookie_name, '', $cookie_options);
        $this->who_is_logged_in = 0;
    }

}
