<?php
/**
 * HOME — the public landing page and a tiny health check.
 *
 * A controller is just a class with one method per page. Each method returns
 * the HTML to send (view(...) builds it). The router calls the method.
 */
class HomeController
{
    public function index(): string
    {
        return view('home', ['title' => config('app_name')]);
    }

    /** A simple JSON endpoint to confirm the app is alive: GET /health */
    public function health(): string
    {
        header('Content-Type: application/json');
        return json_encode(['status' => 'ok', 'time' => gmdate('c')]);
    }
}
