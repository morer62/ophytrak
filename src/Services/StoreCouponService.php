<?php
namespace App\Services;
use App\Repositories\StoreCouponCustomersRepository;
use App\Repositories\StoreCouponRedemptionsRepository;
use App\Repositories\StoreCouponsRepository;
class StoreCouponService {
    public function validateAndCalculate(int $owner,string $code,float $subtotal,string $mode,?int $userId=null,?string $email=null): array {
        $repo=new StoreCouponsRepository();$coupon=$repo->getByOwnerAndCode($owner,$code);
        if(!$coupon||strtoupper((string)$coupon->status)!==StoreCouponsRepository::STATUS_ACTIVE)return ['ok'=>false,'message'=>'Coupon not found or inactive.'];
        $now=date('Y-m-d H:i:s');if($coupon->starts_at&&$coupon->starts_at>$now)return ['ok'=>false,'message'=>'Coupon is not active yet.'];if($coupon->expires_at&&$coupon->expires_at<$now)return ['ok'=>false,'message'=>'Coupon has expired.'];
        if(strtoupper((string)$coupon->purchase_mode)!==strtoupper($mode))return ['ok'=>false,'message'=>'Coupon is not valid for this purchase type.'];
        if((float)$coupon->min_order_total>$subtotal)return ['ok'=>false,'message'=>'Minimum order total was not reached.'];
        if((int)$coupon->max_total_uses>0&&(int)$coupon->total_uses>=(int)$coupon->max_total_uses)return ['ok'=>false,'message'=>'Coupon usage limit was reached.'];
        if(strtoupper((string)$coupon->scope)==='CUSTOMER'&&!(new StoreCouponCustomersRepository())->isAllowedForCoupon((int)$coupon->id,$userId,$email))return ['ok'=>false,'message'=>'Coupon is not assigned to this customer.'];
        if((int)$coupon->max_uses_per_customer>0&&(new StoreCouponRedemptionsRepository())->countByCouponAndCustomer((int)$coupon->id,$userId,$email)>=(int)$coupon->max_uses_per_customer)return ['ok'=>false,'message'=>'Customer coupon usage limit was reached.'];
        $discount=strtoupper((string)$coupon->discount_type)==='FIXED'?(float)$coupon->discount_value:$subtotal*((float)$coupon->discount_value/100);
        $discount=min($subtotal,round($discount,2));return ['ok'=>true,'coupon'=>$coupon,'code'=>$repo->normalizeCode($code),'discount'=>$discount,'total'=>round($subtotal-$discount,2)];
    }
}
