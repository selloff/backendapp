<?php

// use Illuminate\Foundation\Testing\RefreshDatabase;

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
