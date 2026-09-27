<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The AI provider failed or answered with something that is not a usable article.
 */
class AiProviderException extends RuntimeException {}
