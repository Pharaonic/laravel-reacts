<?php

namespace Pharaonic\Laravel\Reacts\Tests;

use Pharaonic\Laravel\Reacts\Catalog\EmojiValidator;

/**
 * Free-form emoji need PCRE2 10.40 or later. PHP builds that bundle an older
 * PCRE (PHP 8.0 and 8.1 on Windows and in the official Docker images) skip
 * these tests, and run FallbackWithoutEmojiPropertiesTest instead.
 */
trait RequiresEmojiProperties
{
    protected function requireEmojiProperties(): void
    {
        if (! EmojiValidator::isSupported()) {
            $this->markTestSkipped('Free-form emoji need PCRE2 10.40 or later; this PHP has PCRE '.PCRE_VERSION.'.');
        }
    }
}
