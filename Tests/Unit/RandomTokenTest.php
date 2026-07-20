<?php

namespace Tests\Unit;

use Codeception\Test\Unit;

class RandomTokenTest extends Unit
{
    private \Auth\RandomToken $token;

    protected function _before()
    {
        $this->token = new \Auth\RandomToken();
    }

    public function testGenerateLength()
    {
        $result = $this->token->generate(10);
        $this->assertEquals(10, strlen($result));
    }

    public function testGenerateLengthVarious()
    {
        foreach ([1, 5, 20, 100] as $length) {
            $result = $this->token->generate($length);
            $this->assertEquals($length, strlen($result), "Length should be $length");
        }
    }

    public function testGenerateUsesDefaultCharset()
    {
        // Default charset excludes 'e', 'i', 'l' (confusable chars)
        $default = "0123456789abcdfghjkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ";

        // Generate many strings and check all chars are from default set
        for ($i = 0; $i < 10; $i++) {
            $result = $this->token->generate(50);
            for ($j = 0; $j < strlen($result); $j++) {
                $char = $result[$j];
                $this->assertStringContainsString(
                    $char,
                    $default,
                    "Character '$char' should be in default charset"
                );
            }
        }
    }

    public function testGenerateCustomCharset()
    {
        $charset = 'ABC';
        $result = $this->token->generate(20, $charset);

        for ($i = 0; $i < strlen($result); $i++) {
            $char = $result[$i];
            $this->assertStringContainsString(
                $char,
                $charset,
                "Character '$char' should be in custom charset"
            );
        }
    }

    public function testGenerateIsRandom()
    {
        // Two calls should produce different results (statistically)
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->token->generate(20);
        }
        $unique = array_unique($results);
        $this->assertCount(10, $unique, 'All 10 random strings should be unique');
    }
}
