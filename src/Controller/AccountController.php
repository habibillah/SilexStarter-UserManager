<?php

namespace Xsanisty\UserManager\Controller;

use Exception;
use InvalidArgumentException;
use Xsanisty\Admin\DashboardModule;
use Cartalyst\Sentry\Users\UserInterface;

class AccountController
{
    protected $user;

    public function __construct(UserInterface $user)
    {
        $this->user = $user;
    }

    /**
     * Display current user account information form.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function myAccount()
    {
        Event::fire(DashboardModule::INIT);

        Menu::get('admin_breadcrumb')->createItem(
            'my-account',
            [
                'label' => 'My Account',
                'icon'  => 'user',
                'url'   => Url::to('usermanager.my_account')
            ]
        );

        return Response::view(
            '@silexstarter-usermanager/account/my_account',
            [
                'userdata'      => $this->user,
                'groups'        => $this->user->getGroups(),
                'permissions'   => $this->user->getMergedPermissions(),
                'title'         => 'My Account',
                'page_title'    => 'My Account',
                'success'       => Session::getFlash('success'),
                'error'         => Session::getFlash('error')
            ]
        );
    }

    /**
     * Perform update information on current user based on submitted form.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updateAccount()
    {
        try {
            $password   = Request::get('password');

            if ($password && $password != Request::get('confirm_password')) {
                throw new InvalidArgumentException("Password and confirmation password not match", 1);
            }

            if (trim($password)) {
                $this->user->password = $password;
            }

            $this->user->first_name   = Request::get('first_name');
            $this->user->last_name    = Request::get('last_name');

            $this->user->save();

            Session::flash('success', 'Your information is updated!');

        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(Url::to('usermanager.my_account'));
    }
}
