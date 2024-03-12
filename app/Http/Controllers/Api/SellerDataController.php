<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\GuidHelper;
use Illuminate\Http\Request;
use App\Models\SellerData;
use App\Models\User;
use App\Models\UserBank;
use App\Models\SaveSeller;
use App\Models\Bank;
use App\Http\Requests\SellerDataRequest;
use App\Notifications\SellerDataNotify;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SellerDataController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return SellerData::get();
    }

    public function getBank()
    {
        return Bank::get();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
               
        return DB::transaction(function () use ($request) {
            $checkseller = SellerData::where('user_id', \Auth::user()->id)->delete();
            // $checkseller = SellerData::where('user_id', \Auth::user()->id)->first();
            // if($checkseller){
            //     return $this->genericResponse(false, "Store Already Exists!", 400);
            // }else{
                    $sellerData = new SellerData();
                    $sellerData->user_id = \Auth::user()->id;
                    $sellerData->country_id = $request->country_id;
                    $sellerData->state_id = $request->state_id;
                    $sellerData->city_id = $request->city_id;
                    $sellerData->fullname = $request->fullname;
                    $sellerData->email = $request->email;
                    $sellerData->phone = $request->phone;
                    $sellerData->address = $request->address;
                    $sellerData->zip = $request->zip;
                    // $sellerData->password = $request->password;
                    // $sellerData->password_confirmation = $request->password_confirmation;
                    $sellerData->guid = GuidHelper::getGuid();
                    $sellerData->save();
                    $user = User::where('id', \Auth::user()->id)->first();
                    $user->notify(new SellerDataNotify($user));
                    return $this->genericResponse(true, "Seller Register Successfully!", 200);
                
            // }
        }); 
        // return DB::transaction(function () use ($request) {
        //     $sellerData = new SellerData();
        //     $sellerData->user_id = \Auth::user()->id;
        //     $sellerData->country_id = $request->country;
        //     $sellerData->state_id = $request->state;
        //     $sellerData->city_id = $request->city;
        //     $sellerData->fullname = $request->name;
        //     $sellerData->email = $request->email;
        //     $sellerData->phone = $request->phone;
        //     $sellerData->address = $request->address;
        //     $sellerData->zip = $request->zip;
        //     // $sellerData->password = $request->password;
        //     // $sellerData->password_confirmation = $request->password_confirmation;
        //     $sellerData->guid = GuidHelper::getGuid();
        //     $sellerData->save();
        //     $user = User::where('id', \Auth::user()->id)->first();
        //     User::where('id', \Auth::user()->id)->update([
        //         "isTrustedSeller" => true
        //     ]);
        //     // $user->notify(new SellerDataNotify($user));
        //     return "You have SuccessFully Registered as Seller in NotNew";
        // });
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
      //
    }

    public function getShopDetails()
    {
        $seller = SellerData::where('user_id', \Auth::user()->id)->first();
        if($seller){
            return response()->json(['status'=> true,'data' =>$seller], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get Seller"], 400);       
        }
    }
    
    public function getShopDetail($id)
    {
        // $userId = \Auth::user()->id? \Auth::user()->id: $id;
        
        $seller = SellerData::where('user_id', \Auth::user()->id)->first();

        if($seller){
            return response()->json(['status'=> true,'data' =>$seller], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get  Shop"], 400);       
        }
        
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    public function updateSellerData(Request $request)
    {
       $sellerData = SellerData::where('guid', $request->get('guid'))
            ->update([
            'user_id' => $request->user_id,
            'country_id' => $request->country_id,
            'state_id' => $request->state_id,
            'city_id' => $request->city_id,
            'fullname' => $request->fullname,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'zip' => $request->zip
        ]);
        return "You have SuccessFully Update Shop Data!";
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    public function getSaveSeller(Request $request, $storeId){

        $saveseller = SaveSeller::where('shop_id', $storeId)
        // ->where('user_id', \Auth::user()->id)
        ->get();
        return $saveseller;
    }
    public function setBankData(Request $request)
    {
        return DB::transaction(function () use ($request) {
            UserBank::where('user_id',\Auth::user()->id)->delete();
            $userbank = new UserBank();
            $userbank->user_id = \Auth::user()->id;
            $userbank->bank_id = $request->bank_id;
            $userbank->accountName = $request->accountName;
            $userbank->accountNumber = $request->accountNumber;
            $userbank->bic_swift = $request->bic_swift;
            $userbank->guid = GuidHelper::getGuid();
            $userbank->save();
            User::where('id',\Auth::user()->id)->update(['isTrustedSeller'=>true]);
            $user = User::where('id',\Auth::user()->id)->first();
            return [
                "user" => $user,
                "message" =>"Your Info is Save!"
            ];
        });
    }

    public function saveSeller(Request $request){
        return DB::transaction(function () use ($request) {
            $saveseller = new SaveSeller();
            $saveseller->shop_id = $request->get('shop_id');
            $saveseller->user_id = \Auth::user()->id;
            $saveseller->save();
            return "You have successfully saved the Seller Details!";
        });
    }

    // public function getSaveSeller(Request $request, $storeId){
    //     $saveseller = SaveSeller::where('shop_id', $storeId)
    //     ->where('user_id', 27,)//\Auth::user()->id)
    //     ->get();
    //     return $saveseller;
    // }
    public function updateBank(Request $request){
        return DB::transaction(function () use ($request) {
            UserBank::where('user_id', \Auth::user()->id)
                ->update([
                    "bank_id" => $request->bank_id,
                    "accountName" => $request->accountName,
                    "accountNumber" => $request->accountNumber,
                    "bic_swift" => $request->bic_swift,
                ]);
            // $userbank = new UserBank();
            // $userbank->bank_id = $request->bank_id;
            // $userbank->accountName = $request->accountName;
            // $userbank->accountNumber = $request->accountNumber;
            // $userbank->bic_swift = $request->bic_swift;
            // $userbank->update();
            return "You have SuccessFully Update Bank Details!";
        });
    }
    public function getBankDetails(Request $request){
            return UserBank::where('user_id', \Auth::user()->id)->first();
    }

    public function getFeatured()
    {
        $seller = SellerData::where('active', true)
        ->where('featured', true)
        ->get();

        if($seller){
            return response()->json(['status'=> true,'data' =>$seller], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get Featured Stores"], 400);       
        }
        
    }
    
}
