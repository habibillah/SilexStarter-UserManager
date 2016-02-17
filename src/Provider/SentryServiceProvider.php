<?php

namespace Xsanisty\UserManager\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use SilexStarter\SilexStarter;
use Xsanisty\UserManager\Session\SentrySymfonySession;
use Cartalyst\Sentry\Cookies\NativeCookie;
use Cartalyst\Sentry\Groups\Eloquent\Provider as GroupProvider;
use Cartalyst\Sentry\Hashing\BcryptHasher;
use Cartalyst\Sentry\Hashing\NativeHasher;
use Cartalyst\Sentry\Hashing\Sha256Hasher;
use Cartalyst\Sentry\Hashing\WhirlpoolHasher;
use Cartalyst\Sentry\Sentry;
use Cartalyst\Sentry\Throttling\Eloquent\Provider as ThrottleProvider;
use Cartalyst\Sentry\Users\Eloquent\Provider as UserProvider;

class SentryServiceProvider implements ServiceProviderInterface
{
    protected $app;

    public function boot(Application $app)
    {
    }

    /**
     * Register the service provider.
     */
    public function register(Application $app)
    {
        $this->app = $app;
        $this->registerHasher();
        $this->registerUserProvider();
        $this->registerGroupProvider();
        $this->registerThrottleProvider();
        $this->registerSession();
        $this->registerCookie();
        $this->registerSentry();

        $app['user'] = $app->share(
            function (Application $app) {
                return $app['sentry']->getUser();
            }
        );

        if ($app instanceof SilexStarter) {
            $app->bind('Cartalyst\Sentry\Sentry', 'sentry');
            $app->bind('Cartalyst\Sentry\Users\UserInterface', 'user');
        }
    }

    /**
     * Register the hasher used by Sentry.
     */
    protected function registerHasher()
    {
        $this->app['sentry.hasher'] = $this->app->share(
            function (Application $app) {
                $hasher = $app['config']['@silexstarter-usermanager.sentry.hasher'];

                switch ($hasher) {
                    case 'native':
                        return new NativeHasher();
                    case 'bcrypt':
                        return new BcryptHasher();
                    case 'sha256':
                        return new Sha256Hasher();
                    case 'whirlpool':
                        return new WhirlpoolHasher();
                }

                throw new \InvalidArgumentException("Invalid hasher [$hasher] chosen for Sentry.");
            }
        );
    }

    /**
     * Register the user provider used by Sentry.
     */
    protected function registerUserProvider()
    {
        $this->app['sentry.user'] = $this->app->share(
            function (Application $app) {
                $model = $app['config']['@silexstarter-usermanager.sentry.users.model'];

                // We will never be accessing a user in Sentry without accessing
                // the user provider first. So, we can lazily set up our user
                // model's login attribute here. If you are manually using the
                // attribute outside of Sentry, you will need to ensure you are
                // overriding at runtime.
                if (method_exists($model, 'setLoginAttributeName')) {
                    $loginAttribute = $app['config']['@silexstarter-usermanager.sentry.users.login_attribute'];

                    forward_static_call_array(
                        [$model, 'setLoginAttributeName'],
                        [$loginAttribute]
                    );
                }

                // Define the Group model to use for relationships.
                if (method_exists($model, 'setGroupModel')) {
                    $groupModel = $app['config']['@silexstarter-usermanager.sentry.groups.model'];

                    forward_static_call_array(
                        [$model, 'setGroupModel'],
                        [$groupModel]
                    );
                }

                // Define the user group pivot table name to use for relationships.
                if (method_exists($model, 'setUserGroupsPivot')) {
                    $pivotTable = $app['config']['@silexstarter-usermanager.sentry.user_groups_pivot_table'];

                    forward_static_call_array(
                        [$model, 'setUserGroupsPivot'],
                        [$pivotTable]
                    );
                }

                return new UserProvider($app['sentry.hasher'], $model);
            }
        );
    }

    /**
     * Register the group provider used by Sentry.
     */
    protected function registerGroupProvider()
    {
        $this->app['sentry.group'] = $this->app->share(
            function (Application $app) {
                $model = $app['config']['@silexstarter-usermanager.sentry.groups.model'];

                // Define the User model to use for relationships.
                if (method_exists($model, 'setUserModel')) {
                    $userModel = $app['config']['@silexstarter-usermanager.sentry.users.model'];

                    forward_static_call_array(
                        [$model, 'setUserModel'],
                        [$userModel]
                    );
                }

                // Define the user group pivot table name to use for relationships.
                if (method_exists($model, 'setUserGroupsPivot')) {
                    $pivotTable = $app['config']['@silexstarter-usermanager.sentry.user_groups_pivot_table'];

                    forward_static_call_array(
                        [$model, 'setUserGroupsPivot'],
                        [$pivotTable]
                    );
                }

                return new GroupProvider($model);
            }
        );
    }

    /**
     * Register the throttle provider used by Sentry.
     */
    protected function registerThrottleProvider()
    {
        $this->app['sentry.throttle'] = $this->app->share(
            function (Application $app) {
                $model = $app['config']['@silexstarter-usermanager.sentry.throttling.model'];

                $throttleProvider = new ThrottleProvider($app['sentry.user'], $model);

                if ($app['config']['@silexstarter-usermanager.sentry.throttling.enabled'] === false) {
                    $throttleProvider->disable();
                }

                if (method_exists($model, 'setAttemptLimit')) {
                    $attemptLimit = $app['config']['@silexstarter-usermanager.sentry.throttling.attempt_limit'];

                    forward_static_call_array(
                        [$model, 'setAttemptLimit'],
                        [$attemptLimit]
                    );
                }
                if (method_exists($model, 'setSuspensionTime')) {
                    $suspensionTime = $app['config']['@silexstarter-usermanager.sentry.throttling.suspension_time'];

                    forward_static_call_array(
                        [$model, 'setSuspensionTime'],
                        [$suspensionTime]
                    );
                }

                // Define the User model to use for relationships.
                if (method_exists($model, 'setUserModel')) {
                    $userModel = $app['config']['@silexstarter-usermanager.sentry.users.model'];

                    forward_static_call_array(
                        [$model, 'setUserModel'],
                        [$userModel]
                    );
                }

                return $throttleProvider;
            }
        );
    }

    /**
     * Register the session driver used by Sentry.
     */
    protected function registerSession()
    {
        $this->app['sentry.session'] = $this->app->share(
            function (Application $app) {
                $key = $app['config']['@silexstarter-usermanager.sentry.cookie.key'];

                return new SentrySymfonySession($this->app['session'], $key);
            }
        );
    }

    /**
     * Register the cookie driver used by Sentry.
     */
    protected function registerCookie()
    {
        $this->app['sentry.cookie'] = $this->app->share(
            function (Application $app) {
                $key = $app['config']['@silexstarter-usermanager.sentry.cookie.key'];

                return new NativeCookie(['http_only' => true], $key);
            }
        );
    }

    /**
     * Takes all the components of Sentry and glues them
     * together to create Sentry.
     */
    protected function registerSentry()
    {
        $this->app['sentry'] = $this->app->share(
            function (Application $app) {
                $clientIp = ($app['request_stack']->getCurrentRequest()) ? $app['request_stack']->getCurrentRequest()->getClientIp() : '';
                return new Sentry(
                    $app['sentry.user'],
                    $app['sentry.group'],
                    $app['sentry.throttle'],
                    $app['sentry.session'],
                    $app['sentry.cookie'],
                    $clientIp
                );
            }
        );
    }
}
