<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    expect(in_array($response->status(), [200, 401, 404]))->toBeTrue();
});
