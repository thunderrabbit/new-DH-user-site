<?php
namespace Security;

/**
 * Immutable CSRF token value type
 *
 * Provides type safety to distinguish CSRF tokens from other string values
 * in the Template constructor, particularly to avoid confusion with $markup.
 */
class CSRFToken {

    public function __construct(
        public string $value
    ) {}

    /**
     * String representation of the token
     */
    public function __toString(): string {
        return $this->value;
    }
}
