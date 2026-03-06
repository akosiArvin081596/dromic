<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class MessengerPageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Messenger/Index');
    }
}
