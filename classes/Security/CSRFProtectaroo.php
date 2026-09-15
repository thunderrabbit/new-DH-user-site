<?php

namespace Security;

use Mlaphp\Request;

/**
 * CSRF protection: one synchronizer token per session.
 *
 * The token is minted on first use, lives in $_SESSION for the life of the
 * session, and is never consumed by a successful check. That is deliberate.
 * The first attempt at CSRF in this repo (d557b9f..50c54f6) issued a token
 * per form and burned it on validation, which broke the back button and a
 * second open tab, and needed a form name wired through page, template and
 * handler. A per-session token is what OWASP's synchronizer pattern asks
 * for; single-use tokens only help when the token has already leaked, and
 * a leak means XSS, at which point CSRF tokens are moot anyway.
 *
 * prepend.php instantiates this once and calls validateRequest() before any
 * page code runs, so a handler cannot forget the check. Templates emit the
 * hidden field with csrf_field(); fetch() callers send the X-CSRF-Token
 * header instead (see templates/admin/migrate_tables.tpl.php).
 */
class CSRFProtectaroo
{
    public const FIELD = 'csrf_token';
    public const HEADER = 'X-CSRF-Token';

    /** Methods that must not change state, so carry no token. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private Request $request)
    {
    }

    /**
     * The session's token, minted on first call.
     *
     * @throws \DomainException If $_SESSION is not set (Request refuses to
     *         hand out a session that was never started, so the token can
     *         never be generated without being stored).
     */
    public function getToken(): string
    {
        $existingToken = $this->request->session[self::FIELD] ?? null;

        if (!is_string($existingToken) || $existingToken === '') {
            $existingToken = bin2hex(random_bytes(32));
            $this->request->session[self::FIELD] = $existingToken;
        }

        return $existingToken;
    }

    /**
     * Timing-safe compare against the stored token. A missing stored token
     * (fresh session, expired session) fails closed.
     */
    public function validateToken(?string $submittedToken): bool
    {
        $storedToken = $this->request->session[self::FIELD] ?? null;

        return is_string($storedToken)
            && $storedToken !== ''
            && $submittedToken !== null
            && hash_equals($storedToken, $submittedToken);
    }

    /**
     * The token as presented by the current request: the form field first,
     * else the header. Anything that is not a string (csrf_token[]=x, say)
     * is treated as absent rather than fed to hash_equals.
     */
    public function submittedToken(): ?string
    {
        $fromPost = $this->request->post[self::FIELD] ?? null;
        if (is_string($fromPost)) {
            return $fromPost;
        }

        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', self::HEADER));
        $fromHeader = $this->request->server[$headerKey] ?? null;
        if (is_string($fromHeader)) {
            return $fromHeader;
        }

        return null;
    }

    /**
     * True for safe methods, and for unsafe ones only with a valid token.
     */
    public function validateRequest(): bool
    {
        $method = $this->request->server['REQUEST_METHOD'] ?? 'GET';
        $method = is_string($method) ? strtoupper($method) : '';
        if (in_array($method, self::SAFE_METHODS, true)) {
            return true;
        }

        return $this->validateToken($this->submittedToken());
    }

    /**
     * The hidden input for a POST form. Templates call csrf_field().
     */
    public function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="'
            . htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
