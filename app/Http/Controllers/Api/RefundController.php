<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\UserOrderDetails;
use App\Helpers\StripeHelper;
use App\Helpers\ArrayHelper;
use App\Helpers\GuidHelper;
use App\Helpers\StringHelper;
use App\Models\Product;
use App\Models\Refund;
use App\Models\UserOrder;
use App\Models\Media;
use App\Models\Fedex;
use App\Models\ShippingDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Stripe\StripeClient;
use Carbon\Carbon;
use App\Images;
use Image;

class RefundController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
    public function fileUpload(Request $request){
            $user = User::where('id',Auth::user()->id)->first();
            $productId = $request->get('product_id');
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $guid = GuidHelper::getGuid();
            // $path = User::getUploadPath($user->id) . $entity::MEDIA_UPLOAD;
            $name = "{$guid}.{$extension}";
            $path = 'images/refund/'.Auth::user()->id.'/'. $request->get('order_id') .'/'.$productId.'/'."{$guid}.{$extension}";
            $pathName = 'https://notnewbackend.testingwebsitelink.com/images/refund/'.Auth::user()->id.'/'.$productId.'/'. $request->get('order_id').'/'."{$guid}.{$extension}/"."{$guid}.{$extension}";
            $media = new Media();
            // $name = 'images/'.Product::MEDIA_UPLOAD.'/'.$user->id.'/'. $product->id.'/'."{$guid}.{$extension}";
            $properties = [
                'name' => $pathName,
                'extension' => $extension,
                'type' => 'refund',
                'user_id' => Auth::user()->id,
                'order_id' => $request->get('order_id'),
                'url' => $pathName,
                'active' => true
            ];

            $media->fill($properties);
            $media->save();
            // $path = User::getUploadPath(Auth::user()->id) . StringHelper::trimLower(Media::PRODUCT_IMAGES);
            $image = Image::make($file);
            $image->orientate();
            $image->resize(1024, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $image->stream();
            $file->move($path, "{$guid}.{$extension}");
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
            
            $user = User::where('id',Auth::user()->id)->first();
            /**
                     * For Images Uploading Start
                     */
                     $imageName = [];
                    if($request->hasFile('file')){
                        foreach ($request->file('file') as $file) {
                            $extension = $file->getClientOriginalExtension();
                            $guid = GuidHelper::getGuid();
                            // $path = User::getUploadPath($user->id) . $entity::MEDIA_UPLOAD;
                            $name = "{$guid}.{$extension}";
                            $path = 'images/refund/'.Auth::user()->id.'/'. $request->get('order_id').'/'."{$guid}.{$extension}";
                            $pathName = 'https://notnewbackend.testingwebsitelink.com/images/refund/'.Auth::user()->id.'/'. $request->get('order_id').'/'."{$guid}.{$extension}/"."{$guid}.{$extension}";
                            $media = new Media();
                            // $name = 'images/'.Product::MEDIA_UPLOAD.'/'.$user->id.'/'. $product->id.'/'."{$guid}.{$extension}";
                            $properties = [
                                'name' => $pathName,
                                'extension' => $extension,
                                'type' => 'refund',
                                'user_id' => Auth::user()->id,
                                'order_id' => $request->get('order_id'),
                                //'product_id'=> $productId,
                                'url' => $pathName,
                                'active' => true
                            ];
        
                            $media->fill($properties);
                            $media->save();
                            // $path = User::getUploadPath(Auth::user()->id) . StringHelper::trimLower(Media::PRODUCT_IMAGES);
                            $image = Image::make($file);
                            $image->orientate();
                            $image->resize(1024, null, function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });
                            $image->stream();
                            $file->move($path, "{$guid}.{$extension}");
                        }
                    }
                    
                   
                    /**
                     * For Images Uploading End
                     */
            // $productId = $request->get('product_id');
             $productIds = json_decode($request->get('product_id'));
             foreach($productIds as $productId){
                    //delete the previous Refunded
                    Refund::where('order_id', $request->get('order_id'))
                    ->where('product_id', $productId)->delete();
        
                    //insert new refunded
                    $refund = new Refund();
                    $refund->product_id = $productId;
                    $refund->order_id = $request->get('order_id');
                    $refund->reason = $request->get('reason');
                    $refund->comment = "seller comments ";//$request->get('comment');
                    $refund->status = Refund::STATUS_PENDING;
                    $refund->save();
                    UserOrderDetails::where('order_id', $request->get('order_id'))
                        ->where('product_id',$productId)
                        ->update(['refunded' => true]);//$refund->refunded = true;
                     
             }
            
            $userOrder = UserOrder::where('id',$request->get('order_id'))->update(
                [
                    'status'=> UserOrder::REFUND,
                ]
            );
            if($refund){
                return response()->json(['status'=>'true','data'=>"Order Has been Refunded!!"],200);
            }else{
                return response()->json(['status'=>'false','message'=>'Unable to Place Bid!'],403);
            }

        });
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $order = UserOrder::where('id',$id)->first();
        $order= UserOrder::where('id', $id)
                ->first();
        $refunds = Refund::where('order_id', $id)->get();
        $productIds = [];
        foreach($refunds as $refund){
            $product = Product::with('shop')->where('id', $refund->product_id)->first();
            array_push($productIds,$product->id);
        }
        // $orderdetails = UserOrderDetails::where('order_id',$id)->get(); 
        $orderdetails = UserOrderDetails::whereIn('product_id',$productIds)->where('order_id', $id)->get(); 
        $orderdetailscount = UserOrderDetails::where('order_id',$id)->count(); 
        $media = Media::where('order_id',$id)->get();
        $orderdtls = [];
        $ordtls="";
        foreach($orderdetails as $orderdetail){
            $products = Product::with('shop')->where('id', $orderdetail->product_id)->first();
            
            // // $refunds = Refund::where('product_id', $orderdetail->product_id)->first();
            // // if($refunds){
             $orderdetailData =[
                'id'=>$products->id,
                'seller'=>$products->shop->fullname,
                'name'=>$products->name,
                'producttotal'=>$products->price,
                'ordertotal'=>$orderdetail->price,
                'attributes' => $orderdetail->attributes,
                'quantity' => $orderdetail->quantity,
                'guid' => $orderdetail->guid,
                // 'media'=>$products->media[0],
                ];
                array_push($orderdtls,$orderdetailData);
                
            // }
            // array_push($orderdtls,$products);
        }
       $refundsReasons = Refund::where('order_id', $id)->first(['reason']);
       $refundsMedia = Media::where('order_id', $id)->get();
        $data=[
                'id'=>$order->id,
                'orderid'=>$order->orderid,
                'latitude'=>$order->latitude,
                'longitude'=>$order->longitude,
                'shipmentaddress'=>$order->billing_address,
                'phone'=>$order->phone,
                'name'=>$order->fullname,
                'status'=>$order->status,
                'products'=>$orderdtls,
                'totalItems'=> $orderdetailscount,
                'subtotal'=>$order->subtotal_cost,
                'shippingcost'=>$order->shipping_cost,
                'ordertotal'=>$order->order_total,
                'media'=>$media,
                'Reason'=>$refundsReasons->reason
            ];
        if($order){
            return response()->json(['status'=> true,'data' => $data], 200);       
        }else{
            return response()->json(['status'=> false,'message' => 'unable to get order Details'], 400);        
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
    public function update(Request $request, $id, $status)
    {
        $refund = Refund::where('id',$id)->first();
        $orderId = $refund->order_id;
        $order = Order::where('id',$orderId)->first();
        $buyer = User::where('id', $order->buyer_id)->first();
        $seller = User::where('id', $order->seller_id)->first();
        $product = Product::where('id', $order->product_id)->first();
        $buyer_shipping = ShippingDetail::where('id', $order->shipping_detail_id)->first();
        
        // if($request->status == 1){
        if($status == "accept"){
                
                $resp = array(
                    'labelResponseOptions' => "URL_ONLY",
                    'requestedShipment' => array(
                        'shipper' => array(
                            'contact' => array(
                                "personName" => $buyer->name,//"Shipper Name",
                                "phoneNumber" => '1234567890'// $seller->phone,//1234567890,
                                // "companyName" => "Shipper Company Name"
                            ),
                            'address' => array(
                                'streetLines' => array(
                                    $buyer_shipping->street_address,
                                ),
                                // 'streetLines'=> [
                                //     '10 FedEx Parkway',
                                //     'Suite 302'
                                // ],
                                "city" =>$buyer_shipping->city,//"HARRISON",
                                "stateOrProvinceCode" =>$buyer_shipping->state,//"AR",
                                "postalCode" => $buyer_shipping->zip,//72601,
                                "countryCode" => "US"
                            )
                        ),
                        'recipients' => array(
                            array(
                                'contact' => array(
                                    "personName" => $seller->name,//"BUYER NAME",
                                    "phoneNumber" => '1234567980'//$buyer->phone,//1234567890,
                                    // "companyName" => "Recipient Company Name"
                                ),
                                'address' => array(
                                    'streetLines' => array(
                                        $product->street_address,//"Recipient street address",
                                    ),
                                    // 'streetLines' => [
                                    //     '10 FedEx Parkway',
                                    //     'Suite 302'
                                    //   ],
                                    "city" => $product->city,//"Collierville"
                                    "stateOrProvinceCode" => $product->state,//"TN"
                                    "postalCode" => $product->zip,//38017
                                    "countryCode" => "US"
                                )
                            ),
                        ),
                        'shippingChargesPayment' => array(
                            "paymentType" => "SENDER"
                        ),
                        "shipDatestamp" => Carbon::today()->format('Y-m-d'),
                        "serviceType" => "FEDEX_GROUND",//"FEDEX_GROUND",//"STANDARD_OVERNIGHT",
                        "packagingType" => "YOUR_PACKAGING",//"YOUR_PACKAGING",//"FEDEX_PK",
                        "pickupType" => "USE_SCHEDULED_PICKUP",//"CONTACT_FEDEX_TO_SCHEDULE",//"USE_SCHEDULED_PICKUP",
                        // "carrierCode"=> "FXSP",
                        "blockInsightVisibility" => false,
                        'labelSpecification' => array(
                            "imageType" => "PDF",
                            "labelStockType" => "PAPER_85X11_TOP_HALF_LABEL"
                        ),
                        'requestedPackageLineItems' => array(
                            array(
                                'weight' => array(
                                    "value" => $product->weight,//10,
                                    "units" => "LB"
                                )
                            ),
                            // array(
                            //     'dimensions' => array(
                            //         "length" => "100",
                            //         "width" => "50",
                            //         "height" => "30",
                            //         "units"=> "CM"
                            //     )
                            // ),
                        ),
                    ),
                    'accountNumber' => array(
                        "value" => "740561073"
                    ),
                );
                
    
                // "trackingNumber": "794698404689",
                $fedex_shipment = Fedex::createShipment($resp);
                
                $req = $request->all();
                if (isset($fedex_shipment["errors"])) {
                    
                    // throw new \Exception($fedex_shipment["errors"][0]['message'], 1);
                    $req["tracking_id"] = "Nil";
                    // $req["fedex_shipping"] = "";
                    $order->fill($req);
                    $order->update();
                    $product->is_sold = false;
                    $product->update();
                    $stripe = new StripeClient(env('STRIPE_SK'));
                    $stripe->refunds->create(['payment_intent' => $order->payment_intent]);
                    
                } else if (isset($fedex_shipment["output"]["transactionShipments"][0]["masterTrackingNumber"])) {
                    $req["tracking_id"] = $fedex_shipment["output"]["transactionShipments"][0]["masterTrackingNumber"];
                    $req["fedex_shipping"] = json_encode($fedex_shipment);
                    $order->fill($req);
                    $order->update();
                    $product->is_sold = false;
                    $product->update();
                    $stripe = new StripeClient(env('STRIPE_SK'));
                    $stripe->refunds->create(['payment_intent' => $order->payment_intent]);
                    
                }

            $refund->status = Refund::STATUS_APPROVED;
            // }
        }else if($status == "reject"){
            $refund->status = Refund::STATUS_REJECTED;
        }
        $refund->update();
        return $refund;
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
}
