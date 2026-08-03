<?php

/**
 * Test doubles for the framework statics cc.inc.php reaches for, so the pure helpers
 * in it can be EXECUTED in a test rather than described by greps over the source text.
 *
 * Only the pieces the pure helpers touch are stubbed: request variables and the
 * encrypt/decrypt pair. Anything that would talk to a database or Authorize.Net is
 * deliberately absent.
 *
 * Every definition is guarded so this file is safe to include from several test classes.
 */

namespace Detain\MyAdminAuthorizenet\Tests {
    /**
     * Mutable state shared with the stubs defined below.
     */
    final class FrameworkState
    {
        /** @var array<string, mixed> values seen through App::variables()->request */
        public static array $request = [];

        public static function reset(): void
        {
            self::$request = [];
        }
    }

    /**
     * Request-variables stand-in ($request is a public array in the real class too).
     */
    final class StubVariables
    {
        /** @var array<string, mixed> */
        public $request = [];
    }
}

namespace MyAdmin {
    if (!\class_exists(App::class, false)) {
        /**
         * Minimal stand-in for \MyAdmin\App.
         *
         * encrypt()/decrypt() are a reversible stand-in for the real cipher — the helpers
         * under test only care that decrypt(encrypt($x)) === $x, not how it is done.
         */
        class App
        {
            /** @return \Detain\MyAdminAuthorizenet\Tests\StubVariables */
            public static function variables()
            {
                $variables = new \Detain\MyAdminAuthorizenet\Tests\StubVariables();
                $variables->request = \Detain\MyAdminAuthorizenet\Tests\FrameworkState::$request;
                return $variables;
            }

            /**
             * @param string $plain
             * @return string
             */
            public static function encrypt($plain)
            {
                return 'enc:'.\base64_encode((string) $plain);
            }

            /**
             * @param string $cipher
             * @return string
             */
            public static function decrypt($cipher)
            {
                $cipher = (string) $cipher;
                if (\strpos($cipher, 'enc:') !== 0) {
                    return $cipher;
                }
                return (string) \base64_decode(\substr($cipher, 4), true);
            }
        }
    }
}

namespace {
    // cc.inc.php is not namespaced, so its helpers have to exist in global scope.
    if (!function_exists('myadmin_unstringify')) {
        function myadmin_unstringify($data)
        {
            $decoded = json_decode((string) $data, true);
            return $decoded === null ? [] : $decoded;
        }
    }

    if (!function_exists('myadmin_stringify')) {
        function myadmin_stringify($data)
        {
            return json_encode($data);
        }
    }
}
