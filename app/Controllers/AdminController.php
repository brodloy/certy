<?php
/**
 * ADMIN — an example admin-only area. require_admin() at the top of each method
 * is the whole gate: guests go to /login, signed-in non-admins get a 403.
 * (The demo seed includes admin@example.com / password.)
 */
class AdminController
{
    public function users(): string
    {
        require_admin();

        $page = max(1, (int) input('page', '1'));
        $result = db()->paginate('User', '', [], $page, 15, 'ORDER BY `PK_UserID` ASC');

        return view('admin/users', [
            'title'  => 'Users',
            'result' => $result,
        ], 'app');
    }
}
