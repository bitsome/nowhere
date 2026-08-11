<?php

test('the application shows the test page for guests', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});
