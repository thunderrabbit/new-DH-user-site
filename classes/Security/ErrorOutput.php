<?php

declare(strict_types=1);

namespace Security;

/**
 * Where PHP's errors and uncaught exceptions go: the log, unless a site asks
 * to see them.
 *
 * hide() is the first thing both entry points do after the autoloader, before
 * Config.php is read, so nothing is ever on screen by accident. show() undoes
 * it for a site whose Config sets $display_errors = true.
 *
 * Hidden, an uncaught exception is logged as one line (class, message, file
 * and line) and the visitor gets PAGE. The stack trace is left out of the log
 * on purpose: it carries function arguments.
 */
final class ErrorOutput
{
    public const string PAGE = '<h1>Something went wrong</h1>'
        . '<p>The error has been logged. Please try again later.</p>';

    public static function hide(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        set_exception_handler(self::handle(...));
    }

    public static function show(): void
    {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        // Back to PHP's own handler, which prints the exception.
        set_exception_handler(null);
    }

    public static function handle(\Throwable $e): void
    {
        error_log(self::logLine($e));
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo self::PAGE;
    }

    /**
     * One line for the error log, without the stack trace.
     */
    public static function logLine(\Throwable $e): string
    {
        return 'Uncaught ' . $e::class . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine();
    }
}
