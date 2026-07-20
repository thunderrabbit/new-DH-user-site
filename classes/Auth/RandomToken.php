<?php

namespace Auth;

class RandomToken
{
    public function generate(
        int $length,
        string $possible = ""
    ): string {
        $randString = "";
        // define possible characters
        if (empty($possible)) {
            $possible = "0123456789abcdfghjkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ";
        }
        // add random characters
        for ($i = 0; $i < $length; $i++) {
            // pick a random character from the possible ones
            $char = substr($possible, random_int(0, strlen($possible) - 1), 1);
            $randString .= $char;
        }
        return $randString;
    }
}
