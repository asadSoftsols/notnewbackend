<?php


namespace App\Http\Controllers\Api\Auth;
use App\Models\User;
use App\Models\Notifications;
use App\Models\Otp;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ForgetPasswordVerification;
use App\Notifications\SuccessfullRegister;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Helpers\StringHelper;

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

    public function check(Request $request){
        $user = User::where('email', '=', strtolower($request->email))->first();       
        if ($user) {
            Otp::where('email','=',strtolower($user->email))->delete();
            Notification::send($user, new ForgetPasswordVerification());
         }else{
            return $this->genericResponse(false, 'Email not found',500);
            // throw ValidationException::withMessages(['email' => trans($user)]);
         }
         return $this->genericResponse(true, 'Otp Send SuccessFully',200);
    }
    public function verifyOtp(Request $request){

        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            throw ValidationException::withMessages(['message' => "add All Feilds"]);
        }
        if (Otp::where('otp', $request->otp)->count() != 1) {
            throw ValidationException::withMessages(['message' => "Otp Is Incorrect"]);
        } 
        return $this->genericResponse(200, 'You can change your password.');
     
    }
    public function verifyAuthOtp(Request $request){
        
        return DB::transaction(function () use ($request) {  
            $validator = $this->validator($request->all());

            if ($validator->fails()) {
                throw ValidationException::withMessages(['message' => "add All Feilds"]);
            }
            if (Otp::where('otp', $request->otp)->count() != 1) {
                throw ValidationException::withMessages(['message' => "Otp Is Incorrect or You may already Registered!"]);
            }else{
                $opt = Otp::where('otp', $request->otp)->first();
                $user = User::where('email', $opt->email)->first();
                User::where('email', $opt->email)->update([
                    'email_verified_at' => Carbon::now()->toDateTimeString()
                ]);
                
                Notification::send($user, new SuccessfullRegister());
                $notification = new Notifications();
                $notification->id = StringHelper::createKey();
                $notification->type = "Successfull Register";
                $notification->notifiable_type = "Register";
                $notification->notifiable_id = $user->id;
                $notification->data = "You have Successfully Registered With Not New..";
                $notification->save();

                Otp::where('otp', $request->otp)->delete();
                return $this->genericResponse(200, 'You are registered SuccessFull!.');
            } 
        });
    }
    protected function validator(array $data)
    {
        return  Validator::make($data,[
            'otp' => 'required',
        ]);
    }
}
