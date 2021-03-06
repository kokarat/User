<?php namespace  Lavalite\User\Repositories\Eloquent;

use Input;
use Event;
use Sentry;
use Session;
use Redirect;
use Validator;
use Lavalite\User\Models\User as User;
use Lavalite\User\Interfaces\UserInterface;

class UserRepository extends BaseRepository implements UserInterface
{
    protected $sentry;

    /**
     * Construct a new SentryUser Object
     */
    public function __construct(User $user)
    {

        $this->model  = $user;
        // Get the Throttle Provider
        $this->throttleProvider = Sentry::getThrottleProvider();

        // Enable the Throttling Feature
        $this->throttleProvider->enable();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function create($data, $group = array())
    {

        // Set Validation Rules
        $result = array();
        if(isset($data['username'])){
            
            $rules = array(
            'email'                     => 'required|min:4|max:32|email',
            'password'                  => 'required|min:6|confirmed',
            'password_confirmation'     => 'required',
            );

        } else {
        
            $rules = array(
            'username'                  => 'required|min:4|max:32',
            'password'                  => 'required|min:6|confirmed',
            'password_confirmation'     => 'required',
            );
        }
       
        //Run input validation
        $v = Validator::make($data, $rules);

        if ($v->fails()) {
            // Validation has failed
            $result['success']  = false;
            $result['message']  = trans('user::user.invalidinputs');

        } else {

            try {
                $user                   = array();
                $user['first_name']     = e($data['first_name']);
                $user['last_name']      = e($data['last_name']);
                if(isset($data['username'])){
                    $user['username']   = e($data['username']);
                }  
                $user['email']          = e($data['email']);
                $user['password']       = e($data['password']);
                $user['activated']      = true;
                $user['permissions']    = ['admin' => 1];

                //Attempt to register the user.
                $user      = Sentry::createUser($user);

                if (isset($group) && is_array($group)) {
                    foreach($group as $g) {
                        $userGroup = Sentry::findGroupByName($g);
                        $user->addGroup($userGroup);
                    }
                }

                //success!
                $result['success'] = true;
                $result['message'] = trans('user::user.created');

                Event::fire('user.signup', array(
                    'email' => e($data['email']),
                    'userId' => $user->getId(),
                    'activationCode' => $user->GetActivationCode()
                    ));

            } catch (\Cartalyst\Sentry\Users\LoginRequiredException $e) {
                $result['success'] = false;
                $result['message'] = trans('user::user.loginreq');
            } catch (\Cartalyst\Sentry\Users\UserExistsException $e) {
                $result['success'] = false;
                $result['message'] = trans('user::user.exists');
            }

        }

        $result['errors']   = $v;
        return $result;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function register($data, $group = array('user'))
    {

        // Set Validation Rules
        $result = array();
        if(isset($data['username'])){

            $rules = array(
               
                'username'                  => 'required|min:4|max:32',
                'password'                  => 'required|min:6|confirmed',
                'password_confirmation'     => 'required',
            );
        } else {
            
             $rules = array(
                'email'                     => 'required|min:4|max:32|email',
                'password'                  => 'required|min:6|confirmed',
                'password_confirmation'     => 'required',
            );

        }
    
        //Run input validation
        $v = Validator::make($data, $rules);

        if ($v->fails()) {
            // Validation has failed
            $result['success']  = false;
            $result['message']  = trans('user::user.invalidinputs');

        } else {

            try {
                $user                   = array();
                $user['first_name']     = e($data['first_name']);
                $user['last_name']      = e($data['last_name']);
                if(isset($data['username'])){
                    $user['username']   = e($data['username']);
                } 
                $user['email']          = e($data['email']);
                $user['password']       = e($data['password']);
                $user['facebook']       = e($data['facebook']);
                $user['twitter']        = e($data['twitter']);
                $user['linkedin']       = e($data['linkedin']);
                $user['google']         = e($data['google']);
                //Attempt to register the user.
                $user      = Sentry::register($user);

                if (isset($group) && is_array($group)) {
                    foreach($group as $g) {
                        $userGroup = Sentry::findGroupByName($g);
                        $user->addGroup($userGroup);
                    }
                }

                //success!
                $result['success'] = true;
                $result['message'] = trans('user::user.created');

                Event::fire('user.signup', array(
                    'email' => e($data['email']),
                    'userId' => $user->getId(),
                    'activationCode' => $user->GetActivationCode()
                    ));

            } catch (\Cartalyst\Sentry\Users\LoginRequiredException $e) {
                $result['success'] = false;
                $result['message'] = trans('user::user.loginreq');
            } catch (\Cartalyst\Sentry\Users\UserExistsException $e) {
                $result['success'] = false;
                $result['message'] = trans('user::user.exists');
            }

        }

        $result['errors']   = $v;
        return $result;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  array    $data
     * @return Response
     */
    public function update($id, $data)
    {
        $result = array();
        try {
            // Find the user using the user id
            $user = Sentry::findUserById($id);

            // Only Admins should be able to change group memberships.
            $operator = Sentry::getUser();

            if ($operator->hasAccess('admin') && isset($data['groups'])) {
                // Update group memberships
                $allGroups = Sentry::getGroupProvider()->findAll();
                foreach ($allGroups as $group) {
                    if (isset($data['groups'][$group->id])) {
                        //The user should be added to this group
                        $user->addGroup($group);
                    } else {
                        // The user should be removed from this group
                        $user->removeGroup($group);
                    }
                }

                unset($data['groups']);
            }

            // Update the user details
            $user->fill($data);

            // Update the user
            if ($user->save()) {
                // User information was updated
                $result['success'] = true;
                $result['message'] = trans('user::user.updated');
            } else {
                // User information was not updated
                $result['success'] = false;
                $result['message'] = trans('user::user.notupdated');
            }
        } catch (\Cartalyst\Sentry\Users\UserExistsException $e) {
            $result['success'] = false;
            $result['message'] = trans('user::user.exists');
        } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
            $result['success'] = false;
            $result['message'] = trans('user::user.notfound');
        }

        return $result;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function destroy($id)
    {   
       
        try {
            // Find the user using the user id
            $user = Sentry::findUserById($id);

            // Delete the user
            $user->delete();
        } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * Attempt activation for the specified user
     * @param  int    $id
     * @param  string $code
     * @return bool
     */
    public function activate($id, $code)
    {
        $result = array();
        try {
            // Find the user using the user id
            $user = Sentry::findUserById($id);

            // Attempt to activate the user
            if ($user->attemptActivation($code)) {
                // User activation passed
                $result['success'] = true;
                $url = route('login');
                // Log the user in
                \Sentry::login($user, false);

                $result['message'] = trans('user::user.activated', array('url' => $url));
            } else {
                // User activation failed
                $result['success'] = false;
                $result['message'] = trans('user::user.notactivated');
            }
        } catch (\Cartalyst\Sentry\Users\UserAlreadyActivatedException $e) {
            $result['success'] = false;
            $result['message'] = trans('user::user.alreadyactive');
        } catch (\Cartalyst\Sentry\Users\UserExistsException $e) {
            $result['success'] = false;
            $result['message'] = trans('user::user.exists');
        } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
            $result['success'] = false;
            $result['message'] = trans('user::user.notfound');
        }

        return $result;
    }


    /**
     * Resend the activation email to the specified email address
     * @param  Array    $data
     * @return Response
     */
    public function resend($data)
    {
        $result = array();
        $input = array(

            'email' => Input::get('email'),
            );

        // Set Validation Rules
        $rules = array (

            'email' => 'required|min:4|max:32|email',

            );

        //Run input validation
        $v = Validator::make($input, $rules);

        if ($v->fails()) {

            $result['success']  = false;

        } else {
            try {
                    //Attempt to find the user.
                $user = Sentry::getUserProvider()->findByLogin(e($data['email']));

                if (!$user->isActivated()) {
                        //success!
                    $result['success'] = true;
                    $result['message'] = trans('user::user.emailconfirm');
                    $result['mailData']['activationCode'] = $user->GetActivationCode();
                    $result['mailData']['userId'] = $user->getId();
                    $result['mailData']['email'] = e($data['email']);
                } else {
                    $result['success'] = false;
                    $result['message'] = trans('user::user.alreadyactive');
                }

            } catch (\Cartalyst\Sentry\Users\UserAlreadyActivatedException $e) {
                $result['success'] = false;
                $result['message'] = trans('user::user.alreadyactive');
            } catch (\Cartalyst\Sentry\Users\UserExistsException $e) {
                $result['success'] = false;
                $result['message'] = trans('user::user.exists');
            } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
                $result['success'] = false;
                $result['message'] = trans('user::user.notfound');
            }
        }

        $result['errors']   = $v;
        return $result;
    }

    /**
     * Handle a password reset rewuest
     * @param  Array $data
     * @return Bool
     */
    public function forgotPassword($data)
    {
        $result = array();

        $input = array(

            'email' => Input::get('email'),
            );

        // Set Validation Rules
        $rules = array (

            'email' => 'required|min:4|max:32|email',

            );

        //Run input validation
        $v = Validator::make($input, $rules);

        if ($v->fails()) {
            $result['success']  = false;
            $result['message']  = trans('user::user.invalidinputs');

        } else {

            try {
                $user = Sentry::getUserProvider()->findByLogin(e($data['email']));

                $result['success'] = true;
                $result['message'] = trans('user::user.emailinfo');

                Event::fire('user.forgot', array(
                    'email'     => e($data['email']),
                    'userId'    => $user->getId(),
                    'resetCode' => $user->getResetPasswordCode()
                    ));

            } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
                $result['success'] = false;
                $result['message'] = trans('users.notfound');
            }
        }

        $result['errors']   = $v;
        return $result;
    }

    /**
     * Process the password reset request
     * @param  int    $id
     * @param  string $code
     * @return Array
     */
    public function resetPassword($id, $code)
    {
        $result = array();
        try {
            // Find the user
            $user = Sentry::getUserProvider()->findById($id);
            $newPassword = $this->_generatePassword(8,8);

            // Attempt to reset the user password
            if ($user->attemptResetPassword($code, $newPassword)) {
                // Email the reset code to the user
                $result['success'] = true;
                $result['message'] = trans('user::user.emailpassword');
                $result['mailData']['newPassword'] = $newPassword;
                $result['mailData']['email'] = $user->getLogin();
            } else {
                // Password reset failed
                $result['success'] = false;
                $result['message'] = trans('users.problem');
            }
        } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
            $result['success'] = false;
            $result['message'] = trans('users.notfound');
        }

        return $result;
    }

    /**
     * Process a change password request.
     * @return Array $data
     */
    public function changePassword($data)
    {
        $result = array();

        $input = array(

            'oldPassword'               => Input::get('oldPassword'),
            'newPassword'               => Input::get('newPassword'),
            'newPassword_confirmation'  => Input::get('newPassword_confirmation'),
            );

        // Set Validation Rules
        $rules = array (

            'oldPassword' => 'required|min:6',
            'newPassword' => 'required|min:6|confirmed',
            'newPassword_confirmation' => 'required'

            );

        //Run input validation
        $v = Validator::make($input, $rules);

        if ($v->fails()) {
            $result['success']  = false;
            $result['message']  = trans('users.invalidinputs');

        } else {

            try {
                $user = Sentry::getUserProvider()->findById($data['id']);

                if ($user->checkHash(e($data['oldPassword']), $user->getPassword())) {
                        //The oldPassword matches the current password in the DB. Proceed.
                    $user->password = e($data['newPassword']);

                    if ($user->save()) {
                            // User saved
                        $result['success'] = true;
                        $result['message'] = trans('users.passwordchg');
                    } else {
                            // User not saved
                        $result['success'] = false;
                        $result['message'] = trans('users.passwordprob');
                    }
                } else {
                        // Password mismatch. Abort.
                    $result['success'] = false;
                    $result['message'] = trans('users.oldpassword');
                }
            } catch (\Cartalyst\Sentry\Users\LoginRequiredException $e) {
                $result['success'] = false;
                $result['message'] = 'Login field required.';
            } catch (\Cartalyst\Sentry\Users\UserExistsException $e) {
                $result['success'] = false;
                $result['message'] = trans('users.exists');
            } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
                $result['success'] = false;
                $result['message'] = trans('users.notfound');
            }
        }

        $result['errors']   = $v;
        return $result;
    }

    /**
     * Suspend a user
     * @param  int   $id
     * @param  int   $minutes
     * @return Array
     */
    public function suspend($id, $minutes = 1)
    {
        $result = array();
        try {
            // Find the user using the user id
            $throttle = Sentry::findThrottlerByUserId($id);

            //Set suspension time
            $throttle->setSuspensionTime($minutes);

            // Suspend the user
            $throttle->suspend();

            $result['success'] = true;
            $result['message'] = trans('user::user.suspended', array('minutes' => $minutes));
        } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
            $result['success'] = false;
            $result['message'] = trans('user::user.notfound');
        }

        return $result;
    }

    /**
     * Remove a users' suspension.
     * @param  [type] $id [description]
     * @return [type] [description]
     */
    public function unSuspend($id)
    {
        $result = array();
        try {
            // Find the user using the user id
            $throttle = Sentry::findThrottlerByUserId($id);

            // Unsuspend the user
            $throttle->unsuspend();

            $result['success'] = true;
            $result['message'] = trans('users.unsuspended');
        } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
            $result['success'] = false;
            $result['message'] = trans('users.notfound');
        }

        return $result;
    }

    /**
     * Ban a user
     * @param  int   $id
     * @return Array
     */
    public function ban($id)
    {
        $result = array();
        try {
            // Find the user using the user id
            $throttle = Sentry::findThrottlerByUserId($id);

            // Ban the user
            $throttle->ban();

            $result['success'] = true;
            $result['message'] = trans('users.banned');
        } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
            $result['success'] = false;
            $result['message'] = trans('users.notfound');
        }

        return $result;
    }

    /**
     * Remove a users' ban
     * @param  int   $id
     * @return Array
     */
    public function unBan($id)
    {
        $result = array();
        try {
            // Find the user using the user id
            $throttle = Sentry::findThrottlerByUserId($id);

            // Unban the user
            $throttle->unBan();

            $result['success'] = true;
            $result['message'] = trans('users.unbanned');
        } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
            $result['success'] = false;
            $result['message'] = trans('users.notfound');
        }

        return $result;
    }

    /**
     * Return a specific user from the given id
     *
     * @param  integer $id
     * @return User
     */
    public function byId($id)
    {
        try {
            $user = Sentry::findUserById($id);
        } catch (\Cartalyst\Sentry\Users\UserNotFoundException $e) {
            return false;
        }

        return $user;
    }

    /**
     * Return all the registered users
     *
     * @return stdObject Collection of users
     */
    public function all()
    {
        $users = Sentry::findAllUsers();

        foreach ($users as $user) {
            if ($user->isActivated()) {
                $user->status = "Active";
            } else {
                $user->status = "Not Active";
            }

            //Pull Suspension & Ban info for this user
            $throttle = $this->throttleProvider->findByUserId($user->id);

            //Check for suspension
            if ($throttle->isSuspended()) {
                // User is Suspended
                $user->status = "Suspended";
            }

            //Check for ban
            if ($throttle->isBanned()) {
                // User is Banned
                $user->status = "Banned";
            }
        }

        return $users;
    }

    /**
     * Generate password - helper function
     * From http://www.phpscribble.com/i4xzZu/Generate-random-passwords-of-given-length-and-strength
     *
     */
    private function _generatePassword($length=9, $strength=4)
    {
        $vowels = 'aeiouy';
        $consonants = 'bcdfghjklmnpqrstvwxz';
        if ($strength & 1) {
         $consonants .= 'BCDFGHJKLMNPQRSTVWXZ';
     }
     if ($strength & 2) {
         $vowels .= "AEIOUY";
     }
     if ($strength & 4) {
         $consonants .= '23456789';
     }
     if ($strength & 8) {
         $consonants .= '@#$%';
     }

     $password = '';
     $alt = time() % 2;
     for ($i = 0; $i < $length; $i++) {
            if ($alt == 1) {
                $password .= $consonants[(rand() % strlen($consonants))];
                $alt = 0;
            } else {
                $password .= $vowels[(rand() % strlen($vowels))];
                $alt = 1;
            }
    }

    return $password;
    }

    public function profileedit($id, $data = array())
    {
        if( Input::all()){
        $input = Input::all(); } else {
        $input = $data;
        }

        // Set Validation Rules
        $rules = array (

            'first_name'    => 'alpha|required',
            'last_name'     => 'alpha|required',
            'email'         => 'numeric|email',
            

            );

        //Run input validation
        $v = \Validator::make($input, $rules);
        if ($v->fails())
        {
            $result['success']  = false;
            $result['message']  = trans('users.invalidinputs');
        }
        else
        {

            try
            {
                \Sentry::check();
                $currentUser = \Sentry::getUser();
                if ( $currentUser->hasAccess('user')  || $currentUser->getId() == $id){
                                // Either they are an admin, or they are changing their own password.
                                // Find the user using the user id
                    $user = \Sentry::getUserProvider()->findById($id);

                    foreach($input as $key=>$value){
                        if($key != '_token' && $key != 'id')
                            $user->$key = e($value);

                    }

                                // Update the user
                    if ($user->save())
                    {
                                    // User information was updated

                         $result['success'] = true;
                         $result['message'] = trans('user::user.profile_update');
                    }

                }

            }
            catch (Cartalyst\Sentry\Users\UserExistsException $e)
            {
                $result['success'] = true;
                $result['message'] = 'problem with your account';
            }


        }

        $result['errors']   = $v;
        return $result;


    }


    /**
     * Check social login status
     * @param $provider
     * @param $id
     * @param $email
     * @return bool
     */
    public function social($provider, $id, $email)
    {
        try {
            // Find the user using the user id
            $user = Sentry::findUserByLogin($email);
            if($user->$provider = $id) {
                Sentry::login($user);
                return true;
            }
        } catch (Exception $e) {
            throw $e;
        }
        return false;
    }


}
