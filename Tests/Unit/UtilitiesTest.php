<?php

namespace Tests\Unit;

use Codeception\Test\Unit;

class UtilitiesTest extends Unit
{
    // === randomString tests ===

    public function testRandomStringLength()
    {
        $result = \Utilities::randomString(10);
        $this->assertEquals(10, strlen($result));
    }

    public function testRandomStringLengthVarious()
    {
        foreach ([1, 5, 20, 100] as $length) {
            $result = \Utilities::randomString($length);
            $this->assertEquals($length, strlen($result), "Length should be $length");
        }
    }

    public function testRandomStringUsesDefaultCharset()
    {
        // Default charset excludes 'e', 'i', 'l' (confusable chars)
        $default = "0123456789abcdfghjkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ";

        // Generate many strings and check all chars are from default set
        for ($i = 0; $i < 10; $i++) {
            $result = \Utilities::randomString(50);
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

    public function testRandomStringCustomCharset()
    {
        $charset = 'ABC';
        $result = \Utilities::randomString(20, $charset);

        for ($i = 0; $i < strlen($result); $i++) {
            $char = $result[$i];
            $this->assertStringContainsString(
                $char,
                $charset,
                "Character '$char' should be in custom charset"
            );
        }
    }

    public function testRandomStringIsRandom()
    {
        // Two calls should produce different results (statistically)
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = \Utilities::randomString(20);
        }
        $unique = array_unique($results);
        $this->assertCount(10, $unique, 'All 10 random strings should be unique');
    }

    // === getSchemaFilePath tests ===

    public function testGetSchemaFilePathRejectsEmptyVersion()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot be empty');
        \Utilities::getSchemaFilePath('/some/path', '');
    }

    public function testGetSchemaFilePathRejectsPathTraversal()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('traversal not allowed');
        \Utilities::getSchemaFilePath('/some/path', '../etc/passwd');
    }

    public function testGetSchemaFilePathRejectsInvalidFormat()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid migration path format');
        \Utilities::getSchemaFilePath('/some/path', 'invalid_format.sql');
    }

    public function testGetSchemaFilePathRejectsNoLeadingNumbers()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid migration path format');
        \Utilities::getSchemaFilePath('/some/path', 'ab_name/create_table.sql');
    }

    public function testGetSchemaFilePathValidFormatButMissingDir()
    {
        // Valid format but base directory doesn't exist
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('escapes base directory');
        \Utilities::getSchemaFilePath('/tmp', '00_test/create_users.sql');
    }

    public function testGetSchemaFilePathWithRealFile()
    {
        // Use actual migration file from the project
        $appPath = dirname(__DIR__, 2);  // Go up from Tests/Unit to project root
        $result = \Utilities::getSchemaFilePath($appPath, '00_bedrock/create_users.sql');

        $this->assertStringEndsWith('create_users.sql', $result);
        $this->assertFileExists($result);
    }
}
