<?php

namespace App\Http\Controllers\v1_api;

use App\Acme\Interfaces\BankInterface;
use App\Acme\Classes\Mellat;
use App\Coupon;
use App\Credits;
use App\Events\UserBoughtAProduct;
use App\InvoiceArticle;
use App\InvoiceHeader;
use App\Meta;
use App\Product;
use App\User;
use App\UserCoupon;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\Activitylog\ActivitylogFacade as Activity;
use Symfony\Component\HttpFoundation\Session\Session;

class GateWayController extends Controller
{

    /**
     * @var InvoiceHeader
     */
    private $invoiceHeader;

    /**
     * @var InvoiceArticle
     */
    private $invoiceArticle;

    /**
     * @var Carbon
     */
    private $carbon;

    /**
     * @var \Alopeyk
     */
    private $alopeyk;

    /**
     * @var User
     */
    private $user;
    /**
     * @var Product
     */
    private $product;
    /**
     * @var BankInterface
     */
    private $bank;
    /**
     * @var Credits
     */
    private $credits;
    /**
     * @var Meta
     */
    private $meta;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var UserCoupon
     */
    private $userCoupon;
    /**
     * @var Coupon
     */
    private $coupon;

    /**
     * GateWayController constructor.
     * @param Request $request
     * @param InvoiceHeader $invoiceHeader
     * @param InvoiceArticle $invoiceArticle
     * @param Meta $meta
     * @param UserCoupon $userCoupon
     * @param Carbon $carbon
     * @param User $user
     * @param Product $product
     * @param BankInterface $bank
     * @param Credits $credits
     * @param Session $session
     * @param Coupon $coupon
     */
    function __construct(Request $request, InvoiceHeader $invoiceHeader, InvoiceArticle $invoiceArticle, Meta $meta, UserCoupon $userCoupon,
                         Carbon $carbon, User $user, Product $product, BankInterface $bank, Credits $credits, Session $session, Coupon $coupon)
    {
        parent::__construct($request);
        $this->invoiceHeader = $invoiceHeader;
        $this->invoiceArticle = $invoiceArticle;
        $this->carbon = $carbon;
        $this->alopeyk = new \Alopeyk();
        $this->user = $user;
        $this->product = $product;
        $this->bank = $bank;
        $this->credits = $credits;
        $this->meta = $meta;
        $this->session = $session;
        $this->userCoupon = $userCoupon;
        $this->coupon = $coupon;
    }

    /**
     * create a new invoice and generate peyk token
     *
     * @return array
     */
    public function postToken(){

        // catch the request params
        $input = $this->request->only(['amount', 'address', 'coupon', 'send_date', 'products',
            'send_price', 'address', 'origin', 'send_date', 'send_time']);

        $carbon = new Carbon();

        // effect the coupon on the product
        list($product_prices, $discounted_total_price, $total_price, $coupon) = $this->effectTheCoupon($this->request->coupon, $input['products']);
        if(!$coupon) {
            unset($input['coupon']);
        }

        // getting the confectionery meta
        $confectionery_meta = $this->product->whereId($input['products'][0]['product_id'])->first()->user->meta;
        $confectionery_location = ['lat' =>$confectionery_meta->lat, 'lang' =>$confectionery_meta->lng];

        // convert amount and send_price to integer and rial
        $send_price = $this->session->get($confectionery_meta->user_id.'-'.$this->auth->id. '-delivery');

        $convert_send_date = explode('/', $input['send_date']);
        $send_date_greg = mds_to_gregorian($convert_send_date[0], $convert_send_date[1], $convert_send_date[2]);

        // create invoice header
        $created_invoice_header = $this->invoiceHeader->createNewInvoice($input, $this->auth->id, $total_price, $discounted_total_price,
            $send_price, $send_date_greg, $confectionery_location,
            $this->auth->location, gregorian_to_mds($carbon->year, $carbon->month, $carbon->day));

        // create invoice article
        foreach($input['products'] as $key => $product) {
            $this->invoiceArticle->createNewArticle($product, $created_invoice_header, $product_prices[$product['product_id']]);
        }

        // call the bank api to generate the gateway
        if( $this->request->pay_type === 'online'){

            Activity::log(' خرید آنلاین ' . 'ایجاد شده است .' .$this->auth->name. ' ' .$this->auth->family. '  توسط ' .$created_invoice_header->code. 'فاکتوری با شماره کد ');
            $token = $this->bank->generatePayment(['amount' =>1000, 'order_id'=>$created_invoice_header->code, 'call_back_url'=>url()->to('/'). '/order-purchased']);
            return ['ref_id' =>$token, 'amount' =>1000, 'action'=>'https://bpm.shaparak.ir/pgwchannel/startpay.mellat',
                'invoiceNo' =>$created_invoice_header->code];
        } else {

            Activity::log(' خرید اعتباری ' . 'ایجاد شده است .' .$this->auth->name. ' ' .$this->auth->family. '  توسط ' .$created_invoice_header->code. 'فاکتوری با شماره کد ');
            return ['amount' =>1000, 'invoiceNo' =>$created_invoice_header->code];
        }
    }


    /**
     * check if the pay is done by credit or gateway
     *
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function postCheckPayment(){


        $invoice = $this->invoiceHeader->whereCode($this->request->SaleOrderId);

        // check if the invoice does exists
        if(!$pre_check = $this->preCheck($invoice)) {
            return $pre_check;
        }


        // if the pay type is online check the transaction and
        // verify payment and update the invoice tref and pay_type
        if($this->request->pay_type === 'online'){
            if( !$this->bank->checkTransaction(['order_id' =>$this->request->SaleOrderId, 'sale_reference_id' =>$this->request->SaleReferenceId]) &&
                !$verify_payment = $this->bank->verifyPayment(['order_id' =>$this->request->SaleOrderId, 'sale_reference_id' =>$this->request->SaleReferenceId]) ){

                Activity::log($this->auth->name.' '.$this->auth->family.'توسط کاربر '.$this->request->SaleReferenceId.'و شماره ریفرنس '.$this->request->SaleOrderId.'خطا در پرداخت - خرید با شماره فاکتور ');
                return makeResponse(422, 'خطا در پرداخت .');
            }
            else{

                $invoice->update(['pay_type' =>'online', 'tref'=>$this->request->SaleReferenceId]);
                $invoice = $invoice->first();
                $this->auth->credit()->create(['debt' =>0, 'credit' =>$invoice->total_amount,
                    'comment' =>$invoice->code. 'اعتبار توسط سیستم - پرداخت آنلاین شماره فاکتور ', 'invoice_id' =>$invoice->id, 'invoice_code' =>$invoice->code]);
                $this->auth->credit()->create(['debt' =>$invoice->total_amount, 'credit' =>0, 'comment' => 'خرید محصول',
                    'invoice_id' =>$invoice->id, 'invoice_code' =>$invoice->code]);
            }
        }

        // credit pay type
        else {
            $invoice->update(['pay_type' =>'credit']);
            $invoice = $invoice->first();
            if($this->auth->credits >= $invoice->total_amount){
                $this->auth->credit()->create(['debt' =>$invoice->total_amount, 'credit'=>0, 'comment' => 'خرید محصول', 'invoice_id' =>$invoice->id, 'invoice_code' =>$invoice->code]);
                $invoice->update(['status' => 'paid']);
            }
            else{
                $invoice->update(['status' => 'cancelled']);
                Activity::log(' پرداخت نشد . ' .$this->request->SaleOrderId.'به دلیل اعتبار ناکافی کاربر '.$this->auth->name. ' ' .$this->auth->family.' ُ فاکتور با شماره کد ');
                return makeResponse(422, 'اعتبار شما ناکافی است .');
            }
        }

        // create the response
        $confirm_result = ['track-id' =>$invoice->id, 'order_time' =>explode(' ', $invoice->created_at)[1],
            'order_date' =>shamsiDate($invoice->created_at),
            'address' =>$invoice->address, 'products' =>$invoice->articles];

        // update the coupon code to used
        $this->userCoupon->whereCouponCode($invoice->discount_code)->whereUserId($this->auth->id)->update(['used' => '1']);

        // we need to create notification, email, sms object
        // send the notifications to the buyer and product owner
        $object = new \stdClass();
        $object->notifications = $this->createNotificationObject($invoice);
        $object->client = $this->createEmailObject($invoice);
        $object->sms = $this->createSmsObject($invoice);
        $object->invoice = $invoice;
        event(new UserBoughtAProduct($object));


        // return the result
        return $confirm_result;
    }


    /**
     * @param $invoice
     * @return bool|\Illuminate\Http\JsonResponse
     */
    private function preCheck($invoice){

        // if not invoice has found
        if($invoice->first() === null) {
            return makeResponse(422, 'چنین شماره فاکتوری وجود ندارد .');
        }

        // if status was paid
        if($invoice->first()->status === 'paid') {
            return makeResponse(422, 'این فاکتور قبلا پرداخت شده است .');
        }

        return true;
    }

    /**
     * Create notification object
     *
     * @param $invoice
     * @return array
     */
    private function createNotificationObject($invoice): array
    {

        $buyer = $this->user->whereId($invoice->buyer_id)->first();
        $confectionery = $invoice->articles->first()->product->user;
        $confectionery->text = 'محصول شما با موفقیت ایجاد شد .';
        $confectionery->notification_from = $buyer->id;

        $notification = json_decode($this->meta->whereMetaKey('config')->whereMetaPrefix('notification')->first()->meta_value);
        $admin = $this->user->whereIn('id', $notification->confectionery->create_product->notification)->get()->each(function($row) use(&$buyer){
            $row->text = 'محصول شما با موفقیت ایجاد شد .';
            $row->notification_from = $buyer->id;
        });


        $buyer->text = 'محصول شما با موفقیت ایجاد شد .';
        $buyer->notification_from = $admin->first()->id;

        return [$buyer, $confectionery, $admin];
    }

    /**
     * create email object
     *
     * @param $invoice
     * @return mixed
     */
    private function createEmailObject($invoice){

        // get the info for the client
        return $this->user->whereId($invoice->buyer_id)->first();

    }

    /**
     * @param $invoice
     * @return array
     */
    private function createSmsObject($invoice): array
    {

        // get the info for the confectionery
        // get the info for the client
        $confectionery = $invoice->articles->first()->product->user;

        $notification = json_decode($this->meta->whereMetaKey('config')
            ->whereMetaPrefix('notification')->first()->meta_value);
        $admin = $this->user->whereIn('id', $notification->confectionery->create_product->notification)->get();

        return [$confectionery, $admin];
    }

    /**
     * effect the coupon on the products
     *
     * @param $coupon_code
     * @param $products
     * @return array [product_all_prices, total_real_price, total_with_discount_price, coupon_effected]
     */
    private function effectTheCoupon($coupon_code, $products): array
    {

        $product_prices_without_special_price = [];
        $product_prices_with_special_price = [];
        $product_prices = [];
        $total_real_price = 0;
        $total_price = 0;
        $carbon = new Carbon();
        $coupon = false;

        // first we need to insure that the product is already exists.
        // then we need to resolve the price and check for the special price
        // if the product has a special price we can not effect the coupon on it.
        foreach($products as $product){

            $selected_product = $this->product->whereId($product['product_id'])->first();
            if($selected_product !== null){

                $product_price = json_decode($selected_product->price);

                if($product_price->special_price === 0 || $product_price->special_price === null || $product_price->special_price === 'null') {
                    $product_prices_without_special_price[] = $product_price->price;
                }
                else {
                    $product_prices_with_special_price[] = $product_price->special_price;
                }

                $product_prices[$selected_product->id]['price'] = $product_prices[$selected_product->id]['discounted'] = $product_price->price;
                $total_real_price += $product_price->price;
            }
        }

        // if the coupon is null then ...
        if($coupon_code === null) {
            return [$product_prices, $total_real_price, $total_real_price, $coupon];
        }


        // we need to insure that the coupon does exists, if so then we can call
        // the calculate discount method.
        $coupon_user_model = $this->userCoupon->whereCouponCode($coupon_code)->whereUserId($this->auth->id)->whereStatus('active')->whereUsed(0);
        if($coupon_user_model->count() > 0){

            $coupon_model = $coupon_user_model->first()->coupon()->where('from_date', '<=', $carbon->toDateTimeString())
                ->where('to_date', '>=', $carbon->toDateTimeString())->first();

            if( $coupon_model !== null){

                if($coupon_model->each_order !== 0){
                    list($total_price) = $this->calculateDiscount($coupon_model, $product_prices_without_special_price);
                    $total_price += array_sum($product_prices_with_special_price);
                }
                else {
                    list($total_price, $product_prices) = $this->calculateDiscount($coupon_model, $product_prices);
                }
                $coupon = true;

            } else {
                $coupon = false;
            }
        }

        return [$product_prices, ($total_price == 0) ? $total_real_price : $total_price, $total_real_price, $coupon];
    }

    /**
     * effect the discount on each product or on total of them
     *
     * @param $coupon
     * @param $prices
     * @return array
     */
    private function calculateDiscount($coupon, $prices): array
    {

        $total_price = 0;
        $discounted_price = [];
        if($coupon->each_order == 1){
            if($coupon->discount_type == 'rial') {
                $total_price = array_sum($prices) - $coupon->discount;
            }
            else {
                $total_price = array_sum($prices) - ($prices * $coupon->discount / 100);
            }
        } else {

            $coupon_product_id = json_decode($coupon->product_id);
            foreach($prices as $product_id => $price){

                if(in_array($product_id, $coupon_product_id)){
                    if($coupon->discount_type == 'rial'){
                        $discounted_price[$product_id]['discounted'] = $price['price'] - $coupon->discount;
                        $discounted_price[$product_id]['price'] = $price['price'];
                        $total_price += $price['price'] - $coupon->discount;
                    }
                    else{
                        $discounted_price[$product_id]['discounted'] = $price['price'] - ($price['price'] * $coupon->discount / 100);
                        $discounted_price[$product_id]['price'] = $price['price'];
                        $total_price += $price['price'] - ($price['price'] * $coupon->discount / 100);
                    }
                }
            }
        }

        return [$total_price, $discounted_price];

    }
}
