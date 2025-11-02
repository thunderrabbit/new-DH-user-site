<?php
namespace Security;

use Mlaphp\Request;

/**
 * CSRF Protection class following MLAPHP principles
 *
 * Provides CSRF token generation and validation using dependency injection.
 * Tokens are stored in the session via the Request object and automatically
 * persisted to the database through the existing SessionHandler.
 *
 * Uses form-specific tokens stored in $_SESSION['csrf_tokens'] as an array
 * mapping form_name => token_value. This prevents cross-form token reuse.
 */
class CSRFProtectaroo {

    public function __construct(private Request $request) {}

    /**
     * Generate a new CSRF token for a specific form and store it in the session
     *
     * This method MUST store the token in the session. If the session is not
     * available, an exception will be thrown by the Request object, preventing
     * silent failures that would allow unvalidated requests.
     *
     * @param string $form_name Human-readable identifier for the form (e.g., "password_change_form")
     * @return string The generated token in format "form_name:token_value"
     * @throws \DomainException If $_SESSION is not set
     */
    private function generateToken(string $form_name): string {
        $token = bin2hex(random_bytes(32));

        // Store token in session array keyed by form_name - throw exception if session unavailable
        // This prevents silent failures that would bypass CSRF protection
        if (!isset($this->request->session['csrf_tokens'])) {
            $this->request->session['csrf_tokens'] = [];
        }
        $this->request->session['csrf_tokens'][$form_name] = $token;

        return $form_name . ':' . $token;
    }

    /**
     * Validate a submitted CSRF token against the stored token
     *
     * Parses the submitted token in format "form_name:token_value" and validates
     * against the stored token for that form_name. On successful validation,
     * removes the token to prevent replay attacks.
     *
     * @param string|null $submittedToken The token submitted with the form in format "form_name:token_value"
     *        We handle null tokens gracefully.
     * @return bool True if token is valid, false otherwise
     */
    public function validateToken(?string $submittedToken): bool {
        // Parse form_name and token from submitted string
        if ($submittedToken === null || strpos($submittedToken, ':') === false) {
            return false;
        }

        [$form_name, $submittedTokenValue] = explode(':', $submittedToken, 2);

        // Get stored token for this form_name
        $storedToken = $this->request->session['csrf_tokens'][$form_name] ?? null;

        // Validate using timing-safe comparison
        $isValid = $storedToken !== null &&
            hash_equals($storedToken, $submittedTokenValue);

        // Remove token after successful validation to prevent replay attacks
        // Only remove if validation succeeded to avoid token changes on failed attempts
        if ($isValid && isset($this->request->session['csrf_tokens'][$form_name])) {
            unset($this->request->session['csrf_tokens'][$form_name]);
        }

        return $isValid;
    }

    /**
     * Get the current CSRF token for a specific form, generating a new one if none exists
     *
     * @param string $form_name Human-readable identifier for the form (e.g., "password_change_form")
     * @return string The current token in format "form_name:token_value"
     */
    public function getToken(string $form_name): string {
        $existingToken = $this->request->session['csrf_tokens'][$form_name] ?? null;

        if ($existingToken === null) {
            return $this->generateToken($form_name);
        }

        return $form_name . ':' . $existingToken;
    }
}
