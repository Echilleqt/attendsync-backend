<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' && !getenv('OPENSSL_CONF')) {
            $xamppOpenSsl = 'C:\\xampp\\php\\extras\\ssl\\openssl.cnf';
            if (file_exists($xamppOpenSsl)) {
                putenv("OPENSSL_CONF={$xamppOpenSsl}");
            }
        }

        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function opensslConfig(array $options = []): array
    {
        if (DIRECTORY_SEPARATOR === '\\' && !isset($options['config'])) {
            $xamppOpenSsl = 'C:\\xampp\\php\\extras\\ssl\\openssl.cnf';
            if (file_exists($xamppOpenSsl)) {
                $options['config'] = $xamppOpenSsl;
            }
        }

        return $options;
    }
}
