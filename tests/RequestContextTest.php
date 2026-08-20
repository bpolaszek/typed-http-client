<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient\Tests;

use BenTools\TypedHttpClient\RequestContext;

describe('RequestContext', function () {
    it('exposes the method, the url and the options it was built with', function () {
        $context = new RequestContext('POST', 'https://example.com/todos', ['json' => ['foo' => 'bar']]);

        expect($context->method)->toBe('POST')
            ->and($context->url)->toBe('https://example.com/todos')
            ->and($context->options)->toBe(['json' => ['foo' => 'bar']])
        ;
    });

    it('defaults to an empty set of options', function () {
        expect((new RequestContext('GET', 'https://example.com/todos/1'))->options)->toBe([]);
    });
});
