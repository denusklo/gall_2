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
use Kreait\Firebase\ServiceAccount;
use Firebase\Auth\Token\Exception\InvalidToken;
use Firebase\Auth\Token;
use phpDocumentor\Reflection\Location;

class FirebaseAuthController extends Controller
{
    public $auth;

    public function __construct()
    {
        
        $this->auth = app('firebase.auth');
        
        // $factory = (new Factory)
        //     ->withProjectId(config('services.firebase.project_id'))
        //     ->withDatabaseUri(config('services.firebase.database_url'));
        // $this->auth = $factory->createAuth();

        // $serviceAccount = ServiceAccount::fromArray([
        //     "type" => "service_account",
        //     "project_id" => config('services.firebase.project_id'),
        //     "private_key_id" => config('services.firebase.private_key_id'),
        //     "private_key" => config('services.firebase.private_key'),
        //     "client_email" => config('services.firebase.client_email'),
        //     "client_id" => config('services.firebase.client_id'),
        //     "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
        //     "token_uri" => "https://oauth2.googleapis.com/token",
        //     "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
        //     "client_x509_cert_url" => config('services.firebase.client_x509_cert_url')
        // ]);

        // $this->firebase = (new Factory)
        //     ->withServiceAccount($serviceAccount)
        //     ->withDatabaseUri(config('services.firebase.database_url'))
        //     ->create();
    

    }

    public function register()
    {
        return view('auth.firebase.register');
    }
    public function showLoginForm()
    {
        return view('auth.firebase.login');
    }

    public function store(Request $request)
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

        try {
            $user = $auth->createUser($userProperties);
        } catch (Exception $e) {
            return redirect()->route('firebase.register')->withInput()->with('error', "User not created/registered. " . $e->getMessage());
        }

        if ($user) {
            return redirect()->route('firebase.register')->with('success', "User created/registered successfully");
        } else {
            $_SESSION['status'] = "User not created/registered";
            return redirect()->route('firebase.register');
        }
    }

    public function login(Request $request)
    {
        // dd($this->auth);
        $auth = $this->auth;

        try {
            $user = $auth->getUserByEmail($request->email);           
        } catch (Exception $e) {
            return redirect()->route('firebase.login.form')->withInput()->with('error', $e->getMessage());
        }

        try {
            $signInResult = $auth->signInWithEmailAndPassword($request->email, $request->password);
            $idTokenString = $signInResult->idToken();
        } catch (Exception $e) {
            return redirect()->route('firebase.login.form')->withInput()->with('error', "Wrong password.");
        }
        
        try {
            /** @var \Lcobucci\JWT\Token\Plain $verifiedIdToken */
            $verifiedIdToken = $auth->verifyIdToken($idTokenString);
            // $uid = $signInResult->firebaseUserId();

            $uid = $verifiedIdToken->claims()->get('sub');

            session()->put('displayName', $signInResult->data()["displayName"]);
            session()->put('verified_user_id', $uid);
            session()->put('idTokenString', $idTokenString);

            session()->flash('success', "Logged in successfully");

            return redirect()->route('user.home');
        } catch (InvalidToken $e) {
            return redirect()->route('firebase.login.form')->withInput()->with('error', 'The token is invalid: ' . $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('firebase.login.form')->withInput()->with('error', 'The token could not be parsed: ' . $e->getMessage());
        }
        // if ($signInResult) {
        //     return redirect()->route('createRequest');
        // }

    }

    public function logout(Request $request)
    {
        session()->forget('verified_user_id');
        session()->forget('idTokenString');
        session()->forget('displayName');

        if (isset($_SESSION['expiry_status'])) {
            session()->flash('error', "Session Expired");
        } else {
            session()->flash('success', "Logout successfully");
        }

        return redirect()->route('firebase.login.form');
    }

    public function authentication()
    {
        $auth = $this->auth;

        if (session()->get('verified_user_id')) {

            $uid = session()->get('verified_user_id');
            $idTokenString = session()->get('idTokenString');

            try {
                $verifiedIdToken = $auth->verifyIdToken($idTokenString);
            } catch (InvalidToken $e) {
                return false;
            } catch (\InvalidArgumentException $e) {
                return false;
            }
            return true;
        } 
        return false;
    }
}
