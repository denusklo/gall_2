<?php

namespace App\Http\Controllers;

// namespace Lcobucci\JWT;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Signature;
use Lcobucci\JWT\UnencryptedToken;

use Exception;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Factory;
use Firebase\Auth\Token\Exception\InvalidToken;
use Firebase\Auth\Token;
use phpDocumentor\Reflection\Location;

class FirebaseAuthController extends Controller
{
    public $auth;

    public function __construct()
    {
        // $this->auth = $auth;

        $this->auth = app('firebase.auth');
    }

    public function createUser(Request $request)
    {
        $auth = $this->auth;
        $userProperties = [
            'email' => $request->email,
            'emailVerified' => false,
            'phoneNumber' => $request->phone,
            'password' => $request->password,
            'displayName' => $request->name,
            'photoUrl' => '',
            'disabled' => false,
        ];

        $user = $auth->createUser($userProperties);

        if ($user) {
            $_SESSION['status'] = "User created/registered successfully";
            return redirect()->route('register');
        } else {
            $_SESSION['status'] = "User not created/registered";
            return redirect()->route('register');
        }
    }

    public function login(Request $request)
    {
        $auth = $this->auth;
        dd($request);

        try {
            $user = $auth->getUserByEmail($request->email);

            try {
                $signInResult = $auth->signInWithEmailAndPassword($request->email, $request->password);
                $idTokenString = $signInResult->idToken();

                try {
                    /** @var \Lcobucci\JWT\Token\Plain $verifiedIdToken */
                    $verifiedIdToken = $auth->verifyIdToken($idTokenString);
                    // $uid = $signInResult->firebaseUserId();

                    $uid = $verifiedIdToken->claims()->get('sub');

                    $_SESSION['displayName'] = $signInResult->data()["displayName"];
                    $_SESSION['verified_user_id'] = $uid;
                    $_SESSION['idTokenString'] = $idTokenString;

                    $_SESSION['status'] = "Logged in successfully";

                    return redirect()->route('userHome');
                } catch (InvalidToken $e) {
                    echo 'The token is invalid: ' . $e->getMessage();
                } catch (\InvalidArgumentException $e) {
                    echo 'The token could not be parsed: ' . $e->getMessage();
                }
                // if ($signInResult) {
                //     return redirect()->route('createRequest');
                // }
            } catch (Exception $e) {

                $_SESSION['status'] = "Wrong password";
                return redirect()->route('login');
            }
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            // echo $e->getMessage();
            $_SESSION['status'] = "Invalid Email Address";
            return redirect()->route('login');
        }
    }

    public function logout(Request $request)
    {
        unset($_SESSION['verified_user_id']);
        unset($_SESSION['idTokenString']);

        if (isset($_SESSION['expiry_status'])) {
            $_SESSION['status'] = "Session Expired";
        } else {
            $_SESSION['status'] = "Logout successfully";
        }

        return redirect()->route('login');
    }

    public function authentication()
    {
        $auth = $this->auth;

        if (isset($_SESSION['verified_user_id'])) {

            $uid = $_SESSION['verified_user_id'];
            $idTokenString = $_SESSION['idTokenString'];

            try {
                $verifiedIdToken = $auth->verifyIdToken($idTokenString);
            } catch (InvalidToken $e) {
                echo 'The token is invalid: ' . $e->getMessage();
                $_SESSION['expiry_status'] = "Token expired/invalid. Login again.";
                header('location: login');
                exit();
            } catch (\InvalidArgumentException $e) {
                echo 'The token could not be parsed: ' . $e->getMessage();
                $_SESSION['expiry_status'] = "Token expired/invalid. Login again.";
                header('location: login');
                exit();
            }
        } else {

            $_SESSION['status'] = "Login to access this page";
            header('location: login');
            exit();
        }
    }

    public function editUser(Request $request)
    {
        $auth = $this->auth;

        if (isset($_SESSION['uid'])) {
            $uid = $_SESSION['uid'];
            try {
                $user = $auth->getUser($uid);
                $_SESSION['display_name'] = $user->displayName;
                $_SESSION['phone'] = $user->phoneNumber;
                // return redirect()->route('editUser');
                header('location: editUser');
                exit();
            } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
                echo $e->getMessage();
            }
        }



        // $_SESSION[''];

        // echo $request->uid;
        return view('users.editUser');
    }
}
