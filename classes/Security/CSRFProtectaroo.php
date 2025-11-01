<?php
namespace Security;

use Mlaphp\Request;

/**
 * CSRF Protection class following MLAPHP principles
 *
 * Provides CSRF token generation and validation using dependency injection.
 * Tokens are stored in the session via the Request object and automatically
 * persisted to the database through the existing SessionHandler.
 */
class CSRFProtectaroo {

    public function __construct(private Request $request) {}

    /**
     * Generate a new CSRF token and store it in the session
     *
     * @return string The generated token
     */
    public function generateToken(): string {
        $token = bin2hex(random_bytes(32));

        if (isset($this->request->session)) {
            $this->request->session['csrf_token'] = $token;
        }

        return $token;
    }

    /**
     * Validate a submitted CSRF token against the stored token
     *
     * @param string|null $submittedToken The token submitted with the form
     *        We handle null tokens gracefully.
     * @return bool True if token is valid, false otherwise
     */
    public function validateToken(?string $submittedToken): bool {
        $storedToken = $this->request->session['csrf_token'] ?? null;
        // If null (or not a string) is sent, return false
        return $submittedToken !== null &&
            hash_equals($storedToken, $submittedToken);
    }

    /**
     * Get the current CSRF token, generating a new one if none exists
     *
     * @return string The current token
     */
    public function getToken(): string {
        $existingToken = $this->request->session['csrf_token'] ?? null;

        if ($existingToken === null) {
            return $this->generateToken();
        }

        return $existingToken;
    }

    /**
     * Regenerate the CSRF token (useful after sensitive operations)
     *
     * @return string The new token
     */
    public function regenerateToken(): string {
        return $this->generateToken();
    }

    /**
     * Get a new token for sensitive actions (always generates new token)
     *
     * @return string A new token for sensitive operations
     */
    public function getTokenForSensitiveAction(): string {
        return $this->generateToken();
    }

    /**
     * Regenerate token after a sensitive action has been completed
     *
     * @return string The new token
     */
    public function regenerateAfterSensitiveAction(): string {
        return $this->generateToken();
    }
}
