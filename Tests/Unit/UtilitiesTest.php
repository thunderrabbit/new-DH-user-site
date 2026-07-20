<?php

namespace Tests\Unit;

use Codeception\Test\Unit;

class UtilitiesTest extends Unit
{
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
