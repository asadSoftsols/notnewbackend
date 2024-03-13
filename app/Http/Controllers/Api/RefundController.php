<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $imageName = [];
            if($request->hasFile('file')){
                foreach ($request->file('file') as $file) {
                    $extension = $file->getClientOriginalExtension();    
                    $guid = GuidHelper::getGuid();
                    $path = User::getUploadPath() . StringHelper::trimLower(Media::REFUND_IMAGES);
                    $name = "{$path}/{$guid}.{$extension}";  
                    // $name = $file->getClientOriginalName();
                    array_push($imageName, $name);
                    $media = new Media();
                    $order = UserOrder::where('id', $request->get('order_id'))->first();
                    $product = json_decode(json_decode($order->orderItems));
                    
                    $media->fill([
                        'name' => $name,
                        'extension' => $extension,
                        'type' => Media::REFUND_IMAGES,
                        'user_id' => \Auth::user()->id,
                        // 'product_id' => $product[0]->id,
                        'order_id'=> $request->get('order_id'),
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
                        Storage::put('public/'. $name, $image->encode());
                }
            }

            //delete the previous Refunded
            Refund::where('order_id', $request->get('order_id'))->delete();

            //insert new refunded
            $refund = new Refund();
            // $refund->product_id = $product[0]->id;
            $refund->order_id = $request->get('order_id');
            $refund->reason = $request->get('reason');
            $refund->comment = " ";//$request->get('comment');
            $refund->status = Refund::STATUS_PENDING;
            $refund->save();
            
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
        //
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
