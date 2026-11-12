<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

class SchemaPathTest extends Unit
{
    public function testResolveRejectsEmptyVersion(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot be empty');
        new \Database\SchemaPath('/some/path')->resolve('');
    }

    public function testResolveRejectsPathTraversal(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('traversal not allowed');
        new \Database\SchemaPath('/some/path')->resolve('../etc/passwd');
    }

    public function testResolveRejectsInvalidFormat(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid migration path format');
        new \Database\SchemaPath('/some/path')->resolve('invalid_format.sql');
    }

    public function testResolveRejectsNoLeadingNumbers(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid migration path format');
        new \Database\SchemaPath('/some/path')->resolve('ab_name/create_table.sql');
    }

    public function testResolveValidFormatButMissingDir(): void
    {
        // Valid format but base directory doesn't exist
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('escapes base directory');
        new \Database\SchemaPath('/tmp')->resolve('00_test/create_users.sql');
    }

    public function testResolveRejectsAnythingBeforeTheVersionDirectory(): void
    {
        // The regex's leading anchor: the shape must be the whole string.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid migration path format');
        new \Database\SchemaPath(dirname(__DIR__, 2))->resolve('x/00_bedrock/create_users.sql');
    }

    public function testResolveRejectsAnythingAfterTheSqlExtension(): void
    {
        // The regex's trailing anchor.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid migration path format');
        new \Database\SchemaPath(dirname(__DIR__, 2))->resolve('00_bedrock/create_users.sql.bak');
    }

    public function testResolveRejectsAMissingFileInARealDirectory(): void
    {
        // db_schemas exists but the file does not: an \Exception, not a TypeError.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('escapes base directory');
        new \Database\SchemaPath(dirname(__DIR__, 2))->resolve('00_bedrock/no_such_file.sql');
    }

    public function testResolveRejectsASymlinkLeadingOutOfDbSchemas(): void
    {
        // A well-formed name can still point elsewhere through a symlinked
        // version directory. The target must sit under db_schemas itself,
        // not merely somewhere under the app.
        $app = sys_get_temp_dir() . '/schemapath_' . bin2hex(random_bytes(6));
        mkdir($app . '/db_schemas', 0700, true);
        mkdir($app . '/elsewhere');
        file_put_contents($app . '/elsewhere/secret.sql', 'SELECT 1;');
        symlink($app . '/elsewhere', $app . '/db_schemas/00_link');

        try {
            new \Database\SchemaPath($app)->resolve('00_link/secret.sql');
            $this->fail('a symlink out of db_schemas was resolved');
        } catch (\Exception $e) {
            $this->assertStringContainsString('escapes base directory', $e->getMessage());
        } finally {
            unlink($app . '/db_schemas/00_link');
            unlink($app . '/elsewhere/secret.sql');
            rmdir($app . '/elsewhere');
            rmdir($app . '/db_schemas');
            rmdir($app);
        }
    }

    public function testResolveWithRealFile(): void
    {
        // Use actual migration file from the project
        $appPath = dirname(__DIR__, 2);  // Go up from Tests/Unit to project root
        $result = new \Database\SchemaPath($appPath)->resolve('00_bedrock/create_users.sql');

        $this->assertStringEndsWith('create_users.sql', $result);
        $this->assertFileExists($result);
    }
}
