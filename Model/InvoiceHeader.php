<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Comment;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InvoiceHeader extends Model
{

    /**
     * @var string
     */
    protected $table = 'invoice_header';

    /**
     * @var array
     */
    protected $fillable = ['buyer_id', 'issue_date', 'address', 'send_price', 'arrived_comment',
        'origin', 'destination', 'pay_type', 'discount_code', 'payment_type', 'send_date',
        'discounted_amount', 'send_date_greg', 'amount', 'status', 'status_comment',
        'total_amount', 'send_type', 'code'];

    /**
     * @var array
     */
    protected $appends = ['buyer', 'total_weight', 'filter_total_price', 'status_per',
        'confectionery', 'order_date'];

    /**
     * @return string
     */
    public function getBuyerAttribute(): string
    {
        $user = User::whereId($this->buyer_id)->first();
        if( $user !== null ) {
            return $user->name . ' ' . $user->family;
        }

        return 'کاربر حذف شده است';
    }

    /**
     * @return int
     */
    public function getTotalWeightAttribute(): int
    {
        $total_weight = 0;
        InvoiceArticle::whereInvoiceId($this->id)->get()->each(function($row) use(&$total_weight){
            $total_weight += $row->weight;
        });

        return $total_weight;
    }

    /**
     * @return HasMany
     */
    public function articles(): HasMany
    {
        return $this->hasMany(InvoiceArticle::class, 'invoice_id', 'id');
    }

    /**
     * @return string
     */
    public function getFilterTotalPriceAttribute(): string
    {

        return number_format($this->total_amount);
    }

    /**
     * @return string
     */
    public function getStatusPerAttribute(){

        if( $this->status == 'waiting' ) {
            return 'در حال انتظار';
        }
        elseif($this->status == 'paid') {
            return 'پرداخت شده';
        }
        elseif($this->status == 'cancelled') {
            return 'کنسل شده';
        }

    }

    /**
     * @return mixed
     */
    public function getConfectioneryAttribute(){
        if($this->articles()->first() !== null) {
            return $this->articles()->first()->product->user->meta->pastry_name;
        }
    }

    /**
     * @return string
     */
    public function getOrderDateAttribute(): string
    {

        $order_date = shamsiDate($this->created_at);
        return $order_date[0]."/".$order_date[1]."/".$order_date[2];
    }


    /**
     * @param $input
     * @param $auth_id
     * @param $amount
     * @param $discounted_amount
     * @param $send_price
     * @param $send_date_greg
     * @param $origin
     * @param $destination
     * @param $issue_date
     * @return static
     */
    public function createNewInvoice($input, $auth_id, $amount, $discounted_amount, $send_price, $send_date_greg, $origin, $destination, $issue_date){

        $carbon = new Carbon();

        if($input['send_time'] === 'now'){
            $input['send_time'] = $carbon->hour;
        }

        return static::create([
            'buyer_id' =>$auth_id,
            'code' =>getNextInvoiceCode(),
            'send_date' =>$input['send_date']. ' ' .$input['send_time']. ':00:00',
            'status'=>'waiting',
            'issue_date' =>$issue_date[0]. '/' .$issue_date[1]. '/' .$issue_date[2]. ' ' .$carbon->hour. ':' .$carbon->minute. ':' .$carbon->second,
            'amount'=>$amount,
            'address' =>$input['address'],
            'discount_code' =>isset($input['coupon']) ? $input['coupon'] : '',
            'send_price' =>$send_price,
            'total_amount' =>$send_price + $discounted_amount,
            'discounted_amount' => $discounted_amount,
            'origin' =>json_encode($origin),
            'destination' =>json_encode($destination),
            'send_date_greg' =>implode('-', $send_date_greg). ' ' .$input['send_time']. ':00:00'
        ]);
    }

    /**
     * Get all of the post's comments.
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

}
