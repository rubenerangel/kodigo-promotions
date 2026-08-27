<?php

namespace App\Exceptions;

use DomainException;

/**
 * Base for every rule that rejects an operation because of the promotion's
 * current state.
 *
 * The HTTP layer maps this single base class, so adding a new rule means
 * adding a subclass and nothing else. It carries no transport concern on
 * purpose: no status codes, no HTTP imports.
 */
abstract class PromotionStateConflict extends DomainException {}
