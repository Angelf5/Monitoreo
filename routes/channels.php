<?php

use Illuminate\Support\Facades\Broadcast;

// Los canales server.metrics y server.demo son públicos: no necesitan autorización.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
