<?php

namespace App\Http\Controllers\Api\Auth;

use App\Helpers\ArrayHelper;
use App\Helpers\GuidHelper;
use App\Helpers\StringHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrationRequest;
use App\Models\User;
use App\Models\Media;
use App\Models\State;
use App\Models\City;
use App\Models\Otp;
use App\Models\ShippingDetail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\JWTAuth;
use Stripe\StripeClient;
use Carbon\Carbon;
use App\Images;
use Image;


class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '';
    protected $auth;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(JWTAuth $auth)
    {
        $this->auth = $auth;
        // $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            // 'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password' => ['required', 'string'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        //Stripe Customer ID Created..
        $stripe = new \Stripe\StripeClient(
            env('STRIPE_SK')
          );
         $stripe_data = $stripe->customers->create([
            'description' => strtolower($data['email']),
          ]);
        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'guid' => $data['guid'],
            'customer_stripe_id' => $stripe_data->id,
        ]);
        $city = City::where('id', $data['city'])->first();
        $state = State::where('id', $data['state'])->first();
        // $accountLink = StripeHelper::createAccountLink($user);
        $shippingdetails = new ShippingDetail();
        $shippingdetails->user_id = $user->id;
        $shippingdetails->name = $user->name;
        $shippingdetails->street_address = $data['address'];
        // $shippingdetails->state = $state->name;
        // $shippingdetails->city = $city->name;
        $shippingdetails->zip = $data['zip'];
        $shippingdetails->save();

        return $user;
    }
    public function resendOtpEmailVerification(Request $request){
       
        try{
            $user =User::where('email', $request->get('email'))->first();
            $checkOtp = Otp::where('email', $request->get('email'))
                ->where('otp_type', 'EmailVerification')->first();
            if($checkOtp){
                Otp::where('email', $request->get('email'))
                    ->where('otp_type', 'EmailVerification')
                    ->delete();
            }
            $sendOtp = $user->sendEmailVerificationNotification();
            return response()->json(['status'=>'true','data'=>"OTP has been Resend!!"],200);
        }
        catch(Exception $e) {
            return response()->json(['status'=>'false','data'=>$e],500);
        }
    }
    /**
 * @param Request $request
     * @throws \Throwable
     */
    public function register(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = User::where('email', $request->get('email'))->first();
            $checkUser = $user ? $user:null;
            if($checkUser){
                return response()->json([
                    'status' => 'email',
                    'message' => "Email Already Exists"
                ]);
            }else{
                // $CheckUser = User::where('email', $request->get('email'))->first();
                // if($CheckUser){
                //     return response()->json([
                //         'status' => 'username',
                //         'message' => "User Name Already Exists Please Try Different!"
                //     ]); 
                // }else{
                    $validator = $this->validator($request->all());
                    if (!$validator->fails()) {
                        // dd(ArrayHelper::merge($request->all(),['guid'=>GuidHelper::getGuid()]));
        
                        event(new Registered($user = $this->create(ArrayHelper::merge($request->all(), ['guid' => GuidHelper::getGuid()]))));
                        
                        if($request->hasFile('file')){
                            $user = User::orderBy('id', 'desc')->first();
                            $file = $request->file('file');
                            $extension = $file->getClientOriginalExtension();    
                            $guid = GuidHelper::getGuid();
                            $path = User::getUploadPath($user->id) . StringHelper::trimLower(Media::USER);
                            $name = "{$path}/{$guid}.{$extension}";  
                            // $name = $file->getClientOriginalName();
                            // array_push($imageName, $name);
                            $media = new Media();
                            $media->fill([
                                'name' => $name,
                                'extension' => $extension,
                                'type' => Media::USER,
                                'user_id' => $user->id,
                                'active' => true,
                            ]);
                    
                            $media->save();
                            // $image = Image::make($file)->save(public_path('image/product/') . $name);
                            $image = Image::make($file);
                                $image->orientate();
                                $image->resize(1024, null, function ($constraint) {
                                    $constraint->aspectRatio();
                                    $constraint->upsize();
                            });
                                $image->stream();
                                Storage::put('/'. $name, $image->encode());
                        }
        //            $user = Auth::user();
        //            $token = $user->createToken('Personal Access Token')->accessToken;
                        return response()->json([
                            'success' => true,
                            'status' => 'registered',
        //                'data' => $user,
                            'message' => "Please verify your email"
                        ], 200);
                    }
                    return response()->json([
                        'success' => false,
                        'status' => 'fails',
                        'errors' => $this,
                        'message' => $validator->getMessageBag()
                    ], 401);
                // }
            }
        });
    }
}
