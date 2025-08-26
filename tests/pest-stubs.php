<?php

// Stub Pest globals for static analyzers/lints only.
// At runtime, Pest defines these; guards prevent redeclaration.

if (!function_exists('uses')) {
    function uses(...$classes)
    {
        return new class {
            public function in(...$dirs)
            {
                return null;
            }
        };
    }
}

if (!function_exists('test')) {
    function test(string $description, callable $closure = null)
    {
        return null;
    }
}

if (!function_exists('expect')) {
    function expect($value)
    {
        return new class($value) {
            public function __construct(private $v) {}
            public function toBe($other)
            {
                return null;
            }
            public function toBeTrue()
            {
                return null;
            }
            public function not()
            {
                return $this;
            }
            public function toBeNull()
            {
                return null;
            }
        };
    }
}
